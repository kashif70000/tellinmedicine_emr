<?php

namespace App\Services\Nppes;

use App\Models\NppesHealthcareLocation;
use App\Models\NppesImport;
use App\Services\Fax\FaxMatchService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use SplFileObject;
use ZipArchive;

class NppesBulkImportService
{
    public function __construct(
        protected FaxMatchService $matchService
    ) {}

    /**
     * Import or dry-run an NPPES dataset file (CSV or ZIP).
     *
     * @param string $filePath Absolute path to CSV or ZIP
     * @param array{type?: string, dry_run?: bool, batch_size?: int, force?: bool} $options
     * @param callable|null $progressCallback fn(int $scanned, int $relevant, int $batchCount)
     * @return array<string, mixed>
     */
    public function import(string $filePath, array $options = [], ?callable $progressCallback = null): array
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        $typeFilter = strtolower($options['type'] ?? 'all'); // all, pharmacy, laboratory
        $dryRun = !empty($options['dry_run']);
        $batchSize = (int) ($options['batch_size'] ?? config('nppes.bulk_batch_size', 1000));
        if ($batchSize < 1) {
            $batchSize = 1000;
        }

        $fileHash = hash_file('sha256', $filePath);
        $fileName = basename($filePath);

        // Check for duplicate import if not forced and not dry-run
        if (!$dryRun && empty($options['force'])) {
            $existing = NppesImport::where('file_hash', $fileHash)
                ->where('status', 'completed')
                ->first();

            if ($existing) {
                throw new RuntimeException("This NPPES source file has already been imported on {$existing->import_completed_at}. Use --force to reprocess.");
            }
        }

        $tempExtractionDir = null;
        $csvPath = $filePath;

        // Check if file is ZIP
        $isZip = strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'zip';
        if ($isZip) {
            $tempExtractionDir = storage_path('app/nppes_temp/' . uniqid('import_', true));
            $csvPath = $this->extractZipSafely($filePath, $tempExtractionDir);
        }

        $importRecord = null;
        if (!$dryRun) {
            $importRecord = NppesImport::create([
                'source_file' => $fileName,
                'file_hash' => $fileHash,
                'import_started_at' => now(),
                'status' => 'processing',
            ]);
        }

        try {
            $stats = $this->streamCsv(
                $csvPath,
                $fileName,
                $typeFilter,
                $dryRun,
                $batchSize,
                $progressCallback
            );

            if ($importRecord) {
                $importRecord->update([
                    'import_completed_at' => now(),
                    'rows_scanned' => $stats['rows_scanned'],
                    'rows_imported' => $stats['imported'],
                    'pharmacy_rows' => $stats['pharmacy_rows'],
                    'laboratory_rows' => $stats['laboratory_rows'],
                    'status' => 'completed',
                    'notes' => "Imported via CLI with type filter: {$typeFilter}",
                ]);
            }

            return $stats;
        } catch (Exception $e) {
            if ($importRecord) {
                $importRecord->update([
                    'status' => 'failed',
                    'notes' => 'Import failed: ' . substr($e->getMessage(), 0, 1000),
                ]);
            }
            throw $e;
        } finally {
            // Clean up temporary extracted files
            if ($tempExtractionDir && File::isDirectory($tempExtractionDir)) {
                File::deleteDirectory($tempExtractionDir);
            }
        }
    }

    /**
     * Extract ZIP safely into target temp directory.
     */
    protected function extractZipSafely(string $zipPath, string $targetDir): string
    {
        // Verify disk space if possible (at least 2 GB free recommended for large archives)
        $freeBytes = @disk_free_space(dirname($targetDir));
        if ($freeBytes !== false && $freeBytes < 1024 * 1024 * 1024) {
            throw new RuntimeException('Insufficient disk space for extracting NPPES archive (less than 1GB available).');
        }

        if (!File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Failed to open ZIP archive: {$zipPath}");
        }

        $zip->extractTo($targetDir);
        $zip->close();

        // Locate main data CSV (e.g. npidata_pfile_*.csv)
        $files = File::files($targetDir);
        $mainCsv = null;
        $maxSize = 0;

        foreach ($files as $file) {
            $name = $file->getFilename();
            if (strtolower($file->getExtension()) === 'csv') {
                // Ignore header-only CSVs
                if (stripos($name, 'fileheader') !== false) {
                    continue;
                }
                if (stripos($name, 'npidata_pfile') !== false) {
                    $mainCsv = $file->getRealPath();
                    break;
                }
                if ($file->getSize() > $maxSize) {
                    $maxSize = $file->getSize();
                    $mainCsv = $file->getRealPath();
                }
            }
        }

        if (!$mainCsv || !file_exists($mainCsv)) {
            throw new RuntimeException("Could not find main NPPES data CSV file inside ZIP archive.");
        }

        return $mainCsv;
    }

    /**
     * Stream CSV file row by row without loading whole file into memory.
     *
     * @return array<string, mixed>
     */
    protected function streamCsv(
        string $csvPath,
        string $sourceFileName,
        string $typeFilter,
        bool $dryRun,
        int $batchSize,
        ?callable $progressCallback
    ): array {
        $file = new SplFileObject($csvPath, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        // 1. Read and map headers
        $headerRow = $file->fgetcsv();
        if (!$headerRow || !is_array($headerRow)) {
            throw new InvalidArgumentException("Failed to read header row from CSV.");
        }

        $headerMap = $this->identifyHeaders($headerRow);

        $pharmacyTaxonomies = config('nppes.pharmacy_taxonomy_codes', []);
        $labTaxonomies = config('nppes.laboratory_taxonomy_codes', []);

        $stats = [
            'file' => $sourceFileName,
            'rows_scanned' => 0,
            'organizations' => 0,
            'pharmacy_rows' => 0,
            'laboratory_rows' => 0,
            'would_insert' => 0,
            'would_update' => 0,
            'imported' => 0,
            'skipped_invalid' => 0,
            'dry_run' => $dryRun,
        ];

        $batch = [];
        $batchHashes = [];

        while (!$file->eof()) {
            $row = $file->fgetcsv();
            if (!$row || count($row) < 2 || $row[0] === null) {
                continue;
            }

            $stats['rows_scanned']++;

            // Entity Type Code Check: 2 = Organization
            $entityType = trim((string) ($row[$headerMap['entity_type']] ?? ''));
            if ($entityType !== '2') {
                continue; // Skip individual practitioners
            }

            $stats['organizations']++;

            $npi = trim((string) ($row[$headerMap['npi']] ?? ''));
            if (!preg_match('/^\d{10}$/', $npi)) {
                $stats['skipped_invalid']++;
                continue;
            }

            // Organization Names
            $orgName = $this->cleanString($row[$headerMap['org_name']] ?? '');
            $otherOrgName = isset($headerMap['other_org_name']) ? $this->cleanString($row[$headerMap['other_org_name']] ?? '') : null;

            // Practice Location Address (CRITICAL: Do NOT use mailing address)
            $addr1 = $this->cleanString($row[$headerMap['practice_address_1']] ?? '');
            $addr2 = isset($headerMap['practice_address_2']) ? $this->cleanString($row[$headerMap['practice_address_2']] ?? '') : null;
            $city = isset($headerMap['practice_city']) ? $this->cleanString($row[$headerMap['practice_city']] ?? '') : null;
            $state = isset($headerMap['practice_state']) ? strtoupper(trim((string) ($row[$headerMap['practice_state']] ?? ''))) : null;
            $rawZip = isset($headerMap['practice_zip']) ? trim((string) ($row[$headerMap['practice_zip']] ?? '')) : null;
            $postalCode = $this->normalizePostalCode($rawZip);

            $rawPhone = isset($headerMap['practice_phone']) ? (string) ($row[$headerMap['practice_phone']] ?? '') : null;
            $rawFax = isset($headerMap['practice_fax']) ? (string) ($row[$headerMap['practice_fax']] ?? '') : null;

            $phone = $this->matchService->normalizePhoneNumber($rawPhone);
            $fax = $this->matchService->normalizeFaxNumber($rawFax);

            // Dates
            $enumDate = isset($headerMap['enumeration_date']) ? $this->parseDate($row[$headerMap['enumeration_date']] ?? null) : null;
            $lastUpdateDate = isset($headerMap['last_update_date']) ? $this->parseDate($row[$headerMap['last_update_date']] ?? null) : null;

            // Taxonomy Inspection: Check ALL taxonomy columns present
            $matchedCategory = null;
            $primaryTaxonomyCode = null;
            $primaryTaxonomyDesc = null;

            foreach ($headerMap['taxonomy_indices'] as $taxIdx) {
                $code = trim((string) ($row[$taxIdx] ?? ''));
                if (empty($code)) {
                    continue;
                }

                if (isset($pharmacyTaxonomies[$code])) {
                    if ($typeFilter === 'all' || $typeFilter === 'pharmacy') {
                        $matchedCategory = 'pharmacy';
                        $primaryTaxonomyCode = $code;
                        $primaryTaxonomyDesc = $pharmacyTaxonomies[$code];
                        break;
                    }
                } elseif (isset($labTaxonomies[$code])) {
                    if ($typeFilter === 'all' || $typeFilter === 'laboratory') {
                        $matchedCategory = 'laboratory';
                        $primaryTaxonomyCode = $code;
                        $primaryTaxonomyDesc = $labTaxonomies[$code];
                        break;
                    }
                }
            }

            if (!$matchedCategory) {
                continue; // Irrelevant taxonomy
            }

            if ($matchedCategory === 'pharmacy') {
                $stats['pharmacy_rows']++;
            } else {
                $stats['laboratory_rows']++;
            }

            // Normalization & Identity Hash
            $addr1Normalized = substr($addr1, 0, 191);
            $hashString = implode('|', [
                $npi,
                $orgName,
                $addr1Normalized,
                $city,
                $state,
                $postalCode,
                $primaryTaxonomyCode,
                $phone,
                $fax,
                $lastUpdateDate,
            ]);
            $sourceRowHash = hash('sha256', $hashString);

            $record = [
                'npi' => $npi,
                'entity_type' => $entityType,
                'organization_name' => substr($orgName, 0, 255),
                'other_organization_name' => $otherOrgName ? substr($otherOrgName, 0, 255) : null,
                'taxonomy_code' => $primaryTaxonomyCode,
                'taxonomy_description' => $primaryTaxonomyDesc,
                'address_line_1' => $addr1Normalized,
                'address_line_2' => $addr2 ? substr($addr2, 0, 255) : null,
                'city' => $city ? substr($city, 0, 255) : null,
                'state' => $state ? substr($state, 0, 50) : null,
                'postal_code' => $postalCode ? substr($postalCode, 0, 20) : null,
                'phone' => $phone ? substr($phone, 0, 30) : null,
                'fax' => $fax ? substr($fax, 0, 30) : null,
                'enumeration_date' => $enumDate,
                'last_update_date' => $lastUpdateDate,
                'source_file' => substr($sourceFileName, 0, 255),
                'source_row_hash' => $sourceRowHash,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];

            // Deduplicate within the same batch by unique identity
            $identityKey = "{$npi}_{$addr1Normalized}_{$postalCode}_{$primaryTaxonomyCode}";
            $batch[$identityKey] = $record;
            $batchHashes[$identityKey] = $sourceRowHash;

            if (count($batch) >= $batchSize) {
                $this->flushBatch($batch, $batchHashes, $dryRun, $stats);
                $batch = [];
                $batchHashes = [];

                if ($progressCallback) {
                    $progressCallback($stats['rows_scanned'], $stats['pharmacy_rows'] + $stats['laboratory_rows'], count($batch));
                }
            }
        }

        // Flush remaining records
        if (!empty($batch)) {
            $this->flushBatch($batch, $batchHashes, $dryRun, $stats);
            if ($progressCallback) {
                $progressCallback($stats['rows_scanned'], $stats['pharmacy_rows'] + $stats['laboratory_rows'], 0);
            }
        }

        return $stats;
    }

    /**
     * Flush a batch of normalized records to DB or simulate in dry-run.
     *
     * @param array<string, array<string, mixed>> $batch
     * @param array<string, string> $batchHashes
     * @param bool $dryRun
     * @param array<string, mixed> &$stats
     */
    protected function flushBatch(array $batch, array $batchHashes, bool $dryRun, array &$stats): void
    {
        if (empty($batch)) {
            return;
        }

        $records = array_values($batch);

        if ($dryRun) {
            // In dry run, check against existing records in DB to estimate inserts vs updates
            $npis = array_column($records, 'npi');
            $existingLocations = NppesHealthcareLocation::whereIn('npi', $npis)
                ->get()
                ->keyBy(function ($item) {
                    return "{$item->npi}_{$item->address_line_1}_{$item->postal_code}_{$item->taxonomy_code}";
                });

            foreach ($records as $r) {
                $key = "{$r['npi']}_{$r['address_line_1']}_{$r['postal_code']}_{$r['taxonomy_code']}";
                if ($existingLocations->has($key)) {
                    $existing = $existingLocations->get($key);
                    if ($existing->source_row_hash !== $r['source_row_hash']) {
                        $stats['would_update']++;
                    }
                } else {
                    $stats['would_insert']++;
                }
            }
            return;
        }

        // Live Mode: Perform safe batch upsert
        NppesHealthcareLocation::upsert(
            $records,
            ['npi', 'address_line_1', 'postal_code', 'taxonomy_code'],
            [
                'entity_type',
                'organization_name',
                'other_organization_name',
                'taxonomy_description',
                'address_line_2',
                'city',
                'state',
                'phone',
                'fax',
                'enumeration_date',
                'last_update_date',
                'source_file',
                'source_row_hash',
                'updated_at',
            ]
        );

        $stats['imported'] += count($records);
    }

    /**
     * Identify dynamic header column positions in CMS NPPES Version 2 CSV.
     *
     * @param array<int, string> $headers
     * @return array{
     *     npi: int,
     *     entity_type: int,
     *     org_name: int,
     *     other_org_name: ?int,
     *     practice_address_1: int,
     *     practice_address_2: ?int,
     *     practice_city: ?int,
     *     practice_state: ?int,
     *     practice_zip: ?int,
     *     practice_phone: ?int,
     *     practice_fax: ?int,
     *     enumeration_date: ?int,
     *     last_update_date: ?int,
     *     taxonomy_indices: array<int, int>
     * }
     */
    public function identifyHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $index => $col) {
            $key = strtolower(trim((string) $col));
            $key = str_replace(['"', "'", "\xEF\xBB\xBF"], '', $key); // remove UTF-8 BOM or quotes
            $normalized[$key] = $index;
        }

        $npiIdx = $this->findHeaderIndex($normalized, ['npi']);
        $entityTypeIdx = $this->findHeaderIndex($normalized, ['entity type code', 'entity_type_code', 'entity type']);
        $orgNameIdx = $this->findHeaderIndex($normalized, [
            'provider organization name (legal business name)',
            'provider organization name',
            'organization name',
        ]);
        $practiceAddr1Idx = $this->findHeaderIndex($normalized, [
            'provider first line business practice location address',
            'business practice location address 1',
            'practice address 1',
        ]);

        if ($npiIdx === null || $entityTypeIdx === null || $orgNameIdx === null || $practiceAddr1Idx === null) {
            throw new InvalidArgumentException(
                "Missing required NPPES headers in CSV. Detected headers: " . implode(', ', array_slice(array_keys($normalized), 0, 10))
            );
        }

        $taxonomyIndices = [];
        foreach ($normalized as $colName => $idx) {
            if (preg_match('/healthcare provider taxonomy code(_\d+)?$/i', $colName) ||
                preg_match('/^taxonomy_code(_\d+)?$/i', $colName) ||
                $colName === 'taxonomy code') {
                $taxonomyIndices[] = $idx;
            }
        }

        if (empty($taxonomyIndices)) {
            throw new InvalidArgumentException("Missing Healthcare Provider Taxonomy Code column(s) in CSV.");
        }

        return [
            'npi' => $npiIdx,
            'entity_type' => $entityTypeIdx,
            'org_name' => $orgNameIdx,
            'other_org_name' => $this->findHeaderIndex($normalized, [
                'provider other organization name',
                'other organization name',
            ]),
            'practice_address_1' => $practiceAddr1Idx,
            'practice_address_2' => $this->findHeaderIndex($normalized, [
                'provider second line business practice location address',
                'business practice location address 2',
            ]),
            'practice_city' => $this->findHeaderIndex($normalized, [
                'provider business practice location address city name',
                'practice city',
            ]),
            'practice_state' => $this->findHeaderIndex($normalized, [
                'provider business practice location address state name',
                'practice state',
            ]),
            'practice_zip' => $this->findHeaderIndex($normalized, [
                'provider business practice location address postal code',
                'practice postal code',
                'practice zip',
            ]),
            'practice_phone' => $this->findHeaderIndex($normalized, [
                'provider business practice location address telephone number',
                'practice telephone number',
                'practice phone',
            ]),
            'practice_fax' => $this->findHeaderIndex($normalized, [
                'provider business practice location address fax number',
                'practice fax number',
                'practice fax',
            ]),
            'enumeration_date' => $this->findHeaderIndex($normalized, [
                'provider enumeration date',
                'enumeration date',
            ]),
            'last_update_date' => $this->findHeaderIndex($normalized, [
                'last update date',
                'last updated date',
            ]),
            'taxonomy_indices' => $taxonomyIndices,
        ];
    }

    /**
     * Find index in normalized headers array by candidate key names.
     */
    protected function findHeaderIndex(array $normalized, array $candidates): ?int
    {
        foreach ($candidates as $cand) {
            if (isset($normalized[$cand])) {
                return $normalized[$cand];
            }
        }
        return null;
    }

    /**
     * Clean string by trimming and collapsing multiple spaces.
     */
    protected function cleanString(?string $val): string
    {
        if ($val === null) {
            return '';
        }
        return trim(preg_replace('/\s+/', ' ', $val));
    }

    /**
     * Normalize postal code (preserve leading zeros as string, support ZIP+4).
     */
    public function normalizePostalCode(?string $postalCode): ?string
    {
        if ($postalCode === null) {
            return null;
        }

        $clean = trim($postalCode);
        if (empty($clean)) {
            return null;
        }

        // Check if pure 9-digit or 5-digit number
        $digits = preg_replace('/\D/', '', $clean);
        if (strlen($digits) === 9) {
            return substr($digits, 0, 5) . '-' . substr($digits, 5, 4);
        }

        return $clean;
    }

    /**
     * Parse date string safely into YYYY-MM-DD.
     */
    protected function parseDate(?string $rawDate): ?string
    {
        if (empty($rawDate)) {
            return null;
        }

        try {
            return Carbon::parse(trim($rawDate))->format('Y-m-d');
        } catch (Exception) {
            return null;
        }
    }
}
