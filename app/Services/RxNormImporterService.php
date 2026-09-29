<?php

namespace App\Services;

use App\Models\RxnormImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use Exception;

class RxNormImporterService
{
    protected int $chunkSize = 2000;

    /**
     * Run the import process from a local zip file or extracted directory.
     *
     * @param string $sourcePath Path to zip or extracted directory
     * @param callable|null $output Progress callback function($message, $step, $total)
     * @return RxnormImport
     * @throws Exception
     */
    public function import(string $sourcePath, ?callable $output = null): RxnormImport
    {
        $startTime = microtime(true);
        $importRecord = RxnormImport::create([
            'release_version' => 'Unknown',
            'source' => $sourcePath,
            'status' => 'processing',
            'imported_at' => now(),
        ]);

        try {
            $extractedDir = $this->prepareSourceDirectory($sourcePath, $output);
            $releaseVersion = $this->detectReleaseVersion($extractedDir, $sourcePath);

            $importRecord->update([
                'release_version' => $releaseVersion,
            ]);

            $rrfDir = $this->findRrfDirectory($extractedDir);
            if (!$rrfDir) {
                throw new Exception("Could not find RRF directory containing RXNCONSO.RRF in {$extractedDir}");
            }

            $consoFile = $rrfDir . DIRECTORY_SEPARATOR . 'RXNCONSO.RRF';
            $relFile = $rrfDir . DIRECTORY_SEPARATOR . 'RXNREL.RRF';
            $satFile = $rrfDir . DIRECTORY_SEPARATOR . 'RXNSAT.RRF';

            if (!file_exists($consoFile)) {
                throw new Exception("Required file missing: {$consoFile}");
            }

            if ($output) {
                $output("Processing RXNCONSO.RRF concepts...", 0, 0);
            }
            $conceptsData = $this->parseAndStoreConso($consoFile, $output);

            if (file_exists($relFile)) {
                if ($output) {
                    $output("Processing RXNREL.RRF relationships...", 0, 0);
                }
                $this->parseAndStoreRel($relFile, $conceptsData, $output);
            }

            if (file_exists($satFile)) {
                if ($output) {
                    $output("Processing RXNSAT.RRF attributes...", 0, 0);
                }
                $this->parseAndStoreSat($satFile, $conceptsData, $output);
            }

            if ($output) {
                $output("Generating unified medicines dataset...", 0, 0);
            }
            $counts = $this->populateMedicinesTable($conceptsData, $output);

            $duration = round(microtime(true) - $startTime, 2);
            $importRecord->update([
                'status' => 'completed',
                'records_imported' => $counts['imported'],
                'records_updated' => $counts['updated'],
                'records_failed' => $counts['failed'],
                'notes' => "Successfully imported {$counts['imported']} medicine records in {$duration} seconds.",
            ]);

            return $importRecord;

        } catch (Exception $e) {
            Log::error("RxNorm import failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $importRecord->update([
                'status' => 'failed',
                'notes' => "Import error: " . $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Unzips source file if zipped, or returns directory path.
     */
    protected function prepareSourceDirectory(string $sourcePath, ?callable $output): string
    {
        if (is_dir($sourcePath)) {
            return $sourcePath;
        }

        if (!file_exists($sourcePath)) {
            throw new Exception("Source file does not exist: {$sourcePath}");
        }

        $targetDir = storage_path('app/rxnorm/extracted');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        if ($output) {
            $output("Extracting RxNorm package to {$targetDir}...", 0, 0);
        }

        $zip = new ZipArchive();
        if ($zip->open($sourcePath) === true) {
            $zip->extractTo($targetDir);
            $zip->close();
            return $targetDir;
        }

        throw new Exception("Failed to open zip archive: {$sourcePath}");
    }

    /**
     * Find directory containing RXNCONSO.RRF
     */
    protected function findRrfDirectory(string $baseDir): ?string
    {
        if (file_exists($baseDir . DIRECTORY_SEPARATOR . 'RXNCONSO.RRF')) {
            return $baseDir;
        }

        $rrfSub = $baseDir . DIRECTORY_SEPARATOR . 'rrf';
        if (file_exists($rrfSub . DIRECTORY_SEPARATOR . 'RXNCONSO.RRF')) {
            return $rrfSub;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtoupper($file->getFilename()) === 'RXNCONSO.RRF') {
                return $file->getPath();
            }
        }

        return null;
    }

    /**
     * Detect release version from file path, release notes, or zip filename.
     */
    protected function detectReleaseVersion(string $extractedDir, string $sourcePath): string
    {
        if (preg_match('/(\d{2}\d{2}\d{4})/', basename($sourcePath), $m)) {
            return $m[1];
        }

        $releaseNotesFiles = glob($extractedDir . '/*release*.*') ?: glob($extractedDir . '/*README*.*');
        if ($releaseNotesFiles) {
            foreach ($releaseNotesFiles as $fn) {
                $content = file_get_contents($fn);
                if (preg_match('/RxNorm\s+Release\s+([0-9A-Za-z\/\-\._]+)/i', $content, $match)) {
                    return trim($match[1]);
                }
            }
        }

        return 'Prescribable Current (' . date('Y-m-d') . ')';
    }

    /**
     * Parse RXNCONSO.RRF and insert into rxnorm_concepts.
     * Returns an in-memory array of concept metadata indexed by rxcui.
     */
    protected function parseAndStoreConso(string $filePath, ?callable $output): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception("Cannot open file: {$filePath}");
        }

        $batch = [];
        $conceptsData = [];
        $lineCount = 0;

        while (($line = fgets($handle)) !== false) {
            $parts = explode('|', trim($line));
            if (count($parts) < 15) {
                continue;
            }

            $rxcui    = $parts[0];
            $lat      = $parts[1] ?? 'ENG';
            $isPref   = $parts[6] ?? 'N';
            $sab      = $parts[11] ?? 'RXNORM';
            $tty      = $parts[12] ?? '';
            $str      = $parts[14] ?? '';
            $suppress = $parts[16] ?? 'N';

            if ($lat !== 'ENG' || $sab !== 'RXNORM') {
                continue;
            }

            $lineCount++;

            if (!isset($conceptsData[$rxcui])) {
                $conceptsData[$rxcui] = [
                    'rxcui' => $rxcui,
                    'name' => $str,
                    'tty' => $tty,
                    'suppress' => $suppress,
                    'sab' => $sab,
                    'generic_name' => null,
                    'brand_name' => null,
                    'dosage_form' => null,
                    'strength' => null,
                    'route' => null,
                ];
            } else {
                if ($tty === 'IN' || $tty === 'PIN') {
                    $conceptsData[$rxcui]['generic_name'] = $str;
                } elseif ($tty === 'BN') {
                    $conceptsData[$rxcui]['brand_name'] = $str;
                } elseif ($tty === 'DF') {
                    $conceptsData[$rxcui]['dosage_form'] = $str;
                }
            }

            $batch[] = [
                'rxcui' => $rxcui,
                'name' => mb_substr($str, 0, 500),
                'tty' => $tty,
                'suppress' => $suppress,
                'sab' => $sab,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($batch) >= $this->chunkSize) {
                DB::table('rxnorm_concepts')->upsert(
                    $batch,
                    ['rxcui'],
                    ['name', 'tty', 'suppress', 'sab', 'updated_at']
                );
                $batch = [];
                if ($output) {
                    $output("Imported {$lineCount} RxNorm concepts...", $lineCount, 0);
                }
            }
        }

        if (!empty($batch)) {
            DB::table('rxnorm_concepts')->upsert(
                $batch,
                ['rxcui'],
                ['name', 'tty', 'suppress', 'sab', 'updated_at']
            );
        }

        fclose($handle);
        return $conceptsData;
    }

    /**
     * Parse RXNREL.RRF and insert into rxnorm_relationships & link concepts.
     */
    protected function parseAndStoreRel(string $filePath, array &$conceptsData, ?callable $output): void
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return;
        }

        $batch = [];
        $lineCount = 0;

        while (($line = fgets($handle)) !== false) {
            $parts = explode('|', trim($line));
            if (count($parts) < 8) {
                continue;
            }

            $rxcui1 = $parts[0];
            $stype1 = $parts[2] ?? '';
            $rel    = $parts[3] ?? '';
            $rxcui2 = $parts[4] ?? '';
            $stype2 = $parts[6] ?? '';
            $rela   = $parts[7] ?? '';
            $sab    = $parts[10] ?? 'RXNORM';

            if (empty($rxcui1) || empty($rxcui2) || $rxcui1 === $rxcui2) {
                continue;
            }

            $lineCount++;

            if (($rela === 'has_ingredient' || $rel === 'RN') && isset($conceptsData[$rxcui1]) && isset($conceptsData[$rxcui2])) {
                if ($conceptsData[$rxcui2]['tty'] === 'IN' || $conceptsData[$rxcui2]['tty'] === 'PIN') {
                    $conceptsData[$rxcui1]['generic_name'] = $conceptsData[$rxcui2]['name'];
                }
            } elseif (($rela === 'ingredient_of' || $rel === 'RB') && isset($conceptsData[$rxcui1]) && isset($conceptsData[$rxcui2])) {
                if ($conceptsData[$rxcui1]['tty'] === 'IN' || $conceptsData[$rxcui1]['tty'] === 'PIN') {
                    $conceptsData[$rxcui2]['generic_name'] = $conceptsData[$rxcui1]['name'];
                }
            }

            if (($rela === 'has_brand_name' || $rela === 'has_tradename') && isset($conceptsData[$rxcui1]) && isset($conceptsData[$rxcui2])) {
                if ($conceptsData[$rxcui2]['tty'] === 'BN') {
                    $conceptsData[$rxcui1]['brand_name'] = $conceptsData[$rxcui2]['name'];
                }
            } elseif ($rela === 'tradename_of' && isset($conceptsData[$rxcui1]) && isset($conceptsData[$rxcui2])) {
                if ($conceptsData[$rxcui1]['tty'] === 'BN') {
                    $conceptsData[$rxcui2]['brand_name'] = $conceptsData[$rxcui1]['name'];
                }
            }

            if ($rela === 'has_dose_form' && isset($conceptsData[$rxcui1]) && isset($conceptsData[$rxcui2])) {
                if ($conceptsData[$rxcui2]['tty'] === 'DF') {
                    $conceptsData[$rxcui1]['dosage_form'] = $conceptsData[$rxcui2]['name'];
                }
            } elseif ($rela === 'dose_form_of' && isset($conceptsData[$rxcui1]) && isset($conceptsData[$rxcui2])) {
                if ($conceptsData[$rxcui1]['tty'] === 'DF') {
                    $conceptsData[$rxcui2]['dosage_form'] = $conceptsData[$rxcui1]['name'];
                }
            }

            $batch[] = [
                'rxcui1' => $rxcui1,
                'rxcui2' => $rxcui2,
                'rel' => $rel,
                'rela' => $rela,
                'sab' => $sab,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($batch) >= $this->chunkSize) {
                DB::table('rxnorm_relationships')->upsert(
                    $batch,
                    ['rxcui1', 'rxcui2', 'rela'],
                    ['rel', 'sab', 'updated_at']
                );
                $batch = [];
                if ($output) {
                    $output("Imported {$lineCount} RxNorm relationships...", $lineCount, 0);
                }
            }
        }

        if (!empty($batch)) {
            DB::table('rxnorm_relationships')->upsert(
                $batch,
                ['rxcui1', 'rxcui2', 'rela'],
                ['rel', 'sab', 'updated_at']
            );
        }

        fclose($handle);
    }

    /**
     * Parse RXNSAT.RRF and insert into rxnorm_attributes.
     */
    protected function parseAndStoreSat(string $filePath, array &$conceptsData, ?callable $output): void
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return;
        }

        $batch = [];
        $lineCount = 0;

        while (($line = fgets($handle)) !== false) {
            $parts = explode('|', trim($line));
            if (count($parts) < 11) {
                continue;
            }

            $rxcui = $parts[0];
            $atn   = $parts[8] ?? '';
            $sab   = $parts[9] ?? 'RXNORM';
            $atv   = $parts[10] ?? '';

            if (empty($rxcui) || empty($atn)) {
                continue;
            }

            $lineCount++;

            if ($atn === 'ROUTE' && isset($conceptsData[$rxcui])) {
                $conceptsData[$rxcui]['route'] = $atv;
            } elseif ($atn === 'RXN_AVAILABLE_STRENGTH' && isset($conceptsData[$rxcui])) {
                $conceptsData[$rxcui]['strength'] = $atv;
            }

            $batch[] = [
                'rxcui' => $rxcui,
                'atn' => $atn,
                'atv' => mb_substr($atv, 0, 1000),
                'sab' => $sab,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($batch) >= $this->chunkSize) {
                DB::table('rxnorm_attributes')->insert($batch);
                $batch = [];
                if ($output) {
                    $output("Imported {$lineCount} RxNorm attributes...", $lineCount, 0);
                }
            }
        }

        if (!empty($batch)) {
            DB::table('rxnorm_attributes')->insert($batch);
        }

        fclose($handle);
    }

    /**
     * Populate unified medicines table with clinical & branded drugs and concepts.
     */
    protected function populateMedicinesTable(array &$conceptsData, ?callable $output): array
    {
        $batch = [];
        $importedCount = 0;
        $updatedCount = 0;
        $failedCount = 0;

        foreach ($conceptsData as $rxcui => $data) {
            if ($data['suppress'] === 'Y') {
                continue;
            }

            $name = $data['name'];
            $tty  = $data['tty'];
            $genericName = $data['generic_name'];
            $brandName   = $data['brand_name'];
            $dosageForm  = $data['dosage_form'];
            $strength    = $data['strength'];
            $route       = $data['route'];

            if (empty($strength)) {
                if (preg_match('/(\d+(?:\.\d+)?\s*(?:MG|ML|MCG|G|UNITS|%|ACTUAT|HR|L|MEQ|MG\/ML|MCG\/ML|UNIT\/ML))\b/i', $name, $matches)) {
                    $strength = $matches[1];
                }
            }

            if (empty($dosageForm)) {
                if (preg_match('/\b(Oral Capsule|Oral Tablet|Injectable Solution|Topical Cream|Topical Ointment|Ophthalmic Solution|Inhalation Solution|Nasal Spray|Suppository|Oral Solution|Oral Suspension|Extended Release Tablet)\b/i', $name, $dfMatches)) {
                    $dosageForm = $dfMatches[1];
                }
            }

            if (empty($route)) {
                if (!empty($dosageForm)) {
                    if (preg_match('/Oral/i', $dosageForm)) {
                        $route = 'ORAL';
                    } elseif (preg_match('/Injectable|Injection/i', $dosageForm)) {
                        $route = 'INJECTION';
                    } elseif (preg_match('/Topical/i', $dosageForm)) {
                        $route = 'TOPICAL';
                    } elseif (preg_match('/Ophthalmic/i', $dosageForm)) {
                        $route = 'OPHTHALMIC';
                    } elseif (preg_match('/Inhalation/i', $dosageForm)) {
                        $route = 'INHALATION';
                    } elseif (preg_match('/Nasal/i', $dosageForm)) {
                        $route = 'NASAL';
                    } elseif (preg_match('/Rectal|Suppository/i', $dosageForm)) {
                        $route = 'RECTAL';
                    }
                }
            }

            if (empty($genericName) && ($tty === 'IN' || $tty === 'PIN')) {
                $genericName = $name;
            }
            if (empty($brandName) && $tty === 'BN') {
                $brandName = $name;
            }

            $batch[] = [
                'rxcui' => $rxcui,
                'tty' => $tty,
                'name' => mb_substr($name, 0, 500),
                'generic_name' => $genericName ? mb_substr($genericName, 0, 255) : null,
                'brand_name' => $brandName ? mb_substr($brandName, 0, 255) : null,
                'strength' => $strength ? mb_substr($strength, 0, 100) : null,
                'dosage_form' => $dosageForm ? mb_substr($dosageForm, 0, 100) : null,
                'route' => $route ? mb_substr($route, 0, 100) : null,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $importedCount++;

            if (count($batch) >= $this->chunkSize) {
                DB::table('medicines')->upsert(
                    $batch,
                    ['rxcui'],
                    ['tty', 'name', 'generic_name', 'brand_name', 'strength', 'dosage_form', 'route', 'active', 'updated_at']
                );
                $batch = [];
                if ($output) {
                    $output("Stored {$importedCount} medicines records in database...", $importedCount, 0);
                }
            }
        }

        if (!empty($batch)) {
            DB::table('medicines')->upsert(
                $batch,
                ['rxcui'],
                ['tty', 'name', 'generic_name', 'brand_name', 'strength', 'dosage_form', 'route', 'active', 'updated_at']
            );
        }

        return [
            'imported' => $importedCount,
            'updated'  => $updatedCount,
            'failed'   => $failedCount,
        ];
    }
}
