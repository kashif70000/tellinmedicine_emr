<?php

namespace App\Services;

use App\Models\Laboratory;
use App\Services\Contracts\LabScraperInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class LabImportService
{
    /**
     * Excluded non-medical laboratory categories.
     *
     * @var array<int, string>
     */
    protected array $excludedCategories = [
        'research institute',
        'university department',
        'university research laboratory',
        'computer lab',
        'dental laboratory',
        'dental lab',
        'photography lab',
        'photo lab',
        'film lab',
        'engineering laboratory',
        'environmental testing laboratory',
        'materials testing laboratory',
        'calibration laboratory',
        'electronics laboratory',
        'chemistry research laboratory',
        'academic research lab',
        'veterinary laboratory',
        'veterinarian',
        'veterinary care',
        'product testing laboratory',
        'industrial laboratory',
        'forensic laboratory',
        'school',
        'high school',
        'college',
        'university',
        'software company',
        'corporate office',
    ];

    public function __construct(
        protected LabScraperInterface $scraper
    ) {}

    /**
     * Import medical laboratories for a given city and state.
     *
     * @param string $city
     * @param string $state
     * @param int|null $limit
     * @param bool $dryRun
     * @return array{city: string, found: int, inserted: int, updated: int, skipped: int, failed: int, stop_reason: ?string, details: array}
     */
    public function import(string $city, string $state, ?int $limit = null, bool $dryRun = false): array
    {
        $normalizedCity = $this->normalizeCity($city);
        $normalizedState = $this->normalizeState($state);

        $stats = [
            'city' => "{$normalizedCity}, {$normalizedState}",
            'found' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'stop_reason' => null,
            'details' => [],
        ];

        // 1. Fetch raw scraped results
        try {
            $rawRecords = $this->scraper->search($normalizedCity, $normalizedState, $limit);
        } catch (Throwable $e) {
            Log::error('Lab scraper failed to execute search: ' . $e->getMessage(), [
                'city' => $normalizedCity,
                'state' => $normalizedState,
            ]);
            $stats['stop_reason'] = 'execution_exception';
            return $stats;
        }

        $stats['found'] = count($rawRecords);

        $stopReason = null;
        if (method_exists($this->scraper, 'getLastStopReason')) {
            $stopReason = $this->scraper->getLastStopReason();
        }
        if (!$stopReason) {
            if ($limit !== null && count($rawRecords) >= $limit) {
                $stopReason = 'requested_limit_reached';
            } else {
                $stopReason = 'end_of_results_reached';
            }
        }
        $stats['stop_reason'] = $stopReason;
        $stats['scraper_version'] = method_exists($this->scraper, 'getLastMeta') ? ($this->scraper->getLastMeta()['scraper_version'] ?? null) : null;
        $stats['meta'] = method_exists($this->scraper, 'getLastMeta') ? $this->scraper->getLastMeta() : null;

        // 2. Process and persist each record safely
        foreach ($rawRecords as $index => $raw) {
            try {
                $normalized = $this->normalizeRecord($raw, $normalizedCity, $normalizedState);

                // Check for required minimum identifier (valid name)
                if (empty($normalized['name'])) {
                    $stats['skipped']++;
                    $stats['details'][] = [
                        'status' => 'skipped',
                        'reason' => 'Missing or empty laboratory name',
                        'raw' => $raw,
                    ];
                    continue;
                }

                // Verify strictly a medical laboratory or diagnostic testing center
                if (!$this->isStrictMedicalLaboratory($normalized['category'], $normalized['name'])) {
                    $stats['skipped']++;
                    $stats['details'][] = [
                        'status' => 'skipped',
                        'reason' => "Non-laboratory facility excluded: {$normalized['category']}",
                        'raw' => $raw,
                    ];
                    continue;
                }

                $outcome = $this->processRecord($normalized, $dryRun);
                $stats[$outcome['action']]++;
                $stats['details'][] = $outcome;
            } catch (Throwable $e) {
                $stats['failed']++;
                Log::warning('Error processing individual laboratory record: ' . $e->getMessage(), [
                    'index' => $index,
                    'raw' => $raw,
                ]);
                $stats['details'][] = [
                    'status' => 'failed',
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return $stats;
    }

    /**
     * Process a single normalized laboratory record with deduplication and safe update logic.
     *
     * @param array<string, mixed> $data
     * @param bool $dryRun
     * @return array{action: 'inserted'|'updated'|'skipped', laboratory: ?Laboratory, name: string}
     */
    public function processRecord(array $data, bool $dryRun = false): array
    {
        $existing = $this->findExistingLaboratory($data);

        if ($existing) {
            $updates = $this->determineSafeUpdates($existing, $data);

            if (!empty($updates)) {
                if (!$dryRun) {
                    $existing->update($updates);
                }
                return [
                    'action' => 'updated',
                    'laboratory' => $existing,
                    'name' => $existing->name,
                    'updated_fields' => array_keys($updates),
                ];
            }

            return [
                'action' => 'skipped',
                'laboratory' => $existing,
                'name' => $existing->name,
                'reason' => 'No new or updated information available',
            ];
        }

        // Insert new laboratory record
        $laboratory = null;
        if (!$dryRun) {
            $laboratory = Laboratory::create($data);
        }

        return [
            'action' => 'inserted',
            'laboratory' => $laboratory,
            'name' => $data['name'],
        ];
    }

    /**
     * Find existing laboratory using strict deduplication precedence.
     * 1. Match by google_place_id if present.
     * 2. Match by normalized name + street_address + city + state + postal_code.
     */
    public function findExistingLaboratory(array $data): ?Laboratory
    {
        // 1. Strongest identity: google_place_id
        if (!empty($data['google_place_id'])) {
            $match = Laboratory::where('google_place_id', $data['google_place_id'])->first();
            if ($match) {
                return $match;
            }
        }

        // 2. Compound identity: name + street_address + city + state
        if (!empty($data['name']) && !empty($data['street_address']) && !empty($data['city']) && !empty($data['state'])) {
            $candidates = Laboratory::where('city', $data['city'])
                ->where('state', $data['state'])
                ->get();

            foreach ($candidates as $candidate) {
                if ($this->isSameLaboratory($candidate, $data)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Compare an existing laboratory model against incoming normalized data.
     */
    protected function isSameLaboratory(Laboratory $existing, array $incoming): bool
    {
        // Must match normalized names
        if ($this->normalizeStringForComparison($existing->name) !== $this->normalizeStringForComparison($incoming['name'])) {
            return false;
        }

        // Both have street addresses -> compare them
        if (!empty($existing->street_address) && !empty($incoming['street_address'])) {
            $existingAddr = $this->normalizeAddressForComparison($existing->street_address);
            $incomingAddr = $this->normalizeAddressForComparison($incoming['street_address']);

            if ($existingAddr !== $incomingAddr) {
                return false;
            }

            // If postal codes exist for both, they should not conflict
            if (!empty($existing->postal_code) && !empty($incoming['postal_code'])) {
                if ($existing->postal_code !== $incoming['postal_code']) {
                    return false;
                }
            }

            return true;
        }

        // If one is missing street address, do not merge blindly to prevent false merges
        // between distinct branches of chains (like Quest Diagnostics) in the same city
        return false;
    }

    /**
     * Determine which fields should be safely updated on the existing record.
     * NEVER overwrite non-empty existing values with null/empty incoming values.
     *
     * @param Laboratory $existing
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    public function determineSafeUpdates(Laboratory $existing, array $incoming): array
    {
        $updatableFields = [
            'name',
            'category',
            'street_address',
            'city',
            'state',
            'postal_code',
            'phone',
            'website',
            'latitude',
            'longitude',
            'google_place_id',
            'external_source_url',
        ];

        $updates = [];

        foreach ($updatableFields as $field) {
            $newValue = $incoming[$field] ?? null;
            $currentValue = $existing->{$field};

            if ($newValue === null || $newValue === '') {
                continue;
            }

            if ($currentValue === null || $currentValue === '') {
                $updates[$field] = $newValue;
                continue;
            }

            if (in_array($field, ['latitude', 'longitude'], true)) {
                if ($currentValue === null && $newValue !== null) {
                    $updates[$field] = $newValue;
                }
                continue;
            }

            if ($currentValue !== $newValue) {
                $updates[$field] = $newValue;
            }
        }

        return $updates;
    }

    /**
     * Normalize all fields of a raw scraped laboratory record.
     *
     * @param array<string, mixed> $raw
     * @param string $defaultCity
     * @param string $defaultState
     * @return array<string, mixed>
     */
    public function normalizeRecord(array $raw, string $defaultCity, string $defaultState): array
    {
        $name = $this->normalizeName($raw['name'] ?? null, $defaultCity, $defaultState);
        $category = $this->normalizeCategory($raw['category'] ?? null);
        $street = $this->normalizeWhitespace($raw['street_address'] ?? ($raw['address'] ?? null));

        $rawCity = $raw['city'] ?? null;
        if ($rawCity && $this->isKnownCountry($rawCity)) {
            $rawCity = $defaultCity;
        }

        $city = $this->normalizeCity($rawCity ?? $defaultCity);
        $state = $this->normalizeState($raw['state'] ?? $defaultState);
        $postalCode = $this->normalizePostalCode($raw['postal_code'] ?? ($raw['zip'] ?? null));
        $phone = $this->normalizePhone($raw['phone'] ?? null);
        $website = $this->normalizeWebsite($raw['website'] ?? null);

        $lat = isset($raw['latitude']) && is_numeric($raw['latitude']) ? (float) $raw['latitude'] : null;
        $lng = isset($raw['longitude']) && is_numeric($raw['longitude']) ? (float) $raw['longitude'] : null;

        $placeId = $this->normalizeWhitespace($raw['google_place_id'] ?? null);
        $source = $this->normalizeWhitespace($raw['source'] ?? 'google_maps');
        $externalUrl = $this->normalizeWebsite($raw['external_source_url'] ?? null);

        return [
            'name' => $name,
            'category' => $category,
            'street_address' => $street,
            'city' => $city,
            'state' => $state,
            'postal_code' => $postalCode,
            'phone' => $phone,
            'website' => $website,
            'latitude' => $lat,
            'longitude' => $lng,
            'google_place_id' => $placeId,
            'source' => $source ?: 'google_maps',
            'external_source_url' => $externalUrl,
        ];
    }

    /**
     * Normalize and validate a business/laboratory name.
     */
    public function normalizeName(?string $name, string $city = '', string $state = ''): ?string
    {
        $clean = $this->normalizeWhitespace($name);
        if ($clean === null || !$this->isValidBusinessName($clean, $city, $state)) {
            return null;
        }
        return $clean;
    }

    /**
     * Normalize category string.
     */
    public function normalizeCategory(?string $category): ?string
    {
        $clean = $this->normalizeWhitespace($category);
        if ($clean === null) {
            return 'Medical laboratory';
        }
        return mb_convert_case(mb_strtolower($clean, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Check if a category string is excluded.
     */
    public function isExcludedCategory(?string $category): bool
    {
        if ($category === null) {
            return false;
        }

        $catLower = strtolower(trim($category));
        foreach ($this->excludedCategories as $excluded) {
            if ($catLower === $excluded || str_contains($catLower, $excluded)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a name is a well-known medical laboratory brand.
     */
    public function isKnownMedicalLabBrand(?string $name): bool
    {
        if ($name === null) {
            return false;
        }

        $n = strtolower($name);
        return str_contains($n, 'quest diagnostics') ||
               str_contains($n, 'labcorp') ||
               str_contains($n, 'laboratory corporation') ||
               str_contains($n, 'bioreference') ||
               str_contains($n, 'sonic healthcare') ||
               str_contains($n, 'chughtai') ||
               str_contains($n, 'shaukat khanum') ||
               str_contains($n, 'skmch') ||
               str_contains($n, 'islamabad diagnostic') ||
               str_contains($n, 'idc') ||
               str_contains($n, 'excel lab') ||
               str_contains($n, 'essa lab') ||
               str_contains($n, 'aga khan') ||
               str_contains($n, 'alnoor') ||
               str_contains($n, 'citilab') ||
               str_contains($n, 'test zone') ||
               str_contains($n, 'dr lal pathlabs') ||
               str_contains($n, 'metropolis') ||
               str_contains($n, 'thyrocare') ||
               str_contains($n, 'srl diagnostic') ||
               str_contains($n, 'apollo diagnostic') ||
               str_contains($n, 'east side clinical') ||
               str_contains($n, 'caritas medical lab') ||
               str_contains($n, 'safe lab') ||
               str_contains($n, 'shields pet') ||
               str_contains($n, 'arcpoint labs') ||
               str_contains($n, 'lilium diagnostics') ||
               str_contains($n, 'agrawal laboratory') ||
               str_contains($n, 'clinical lab') ||
               str_contains($n, 'medical lab') ||
               str_contains($n, 'diagnostic lab') ||
               str_contains($n, 'diagnostic centre') ||
               str_contains($n, 'diagnostic center') ||
               str_contains($n, 'blood test') ||
               str_contains($n, 'blood draw') ||
               str_contains($n, 'draw station') ||
               str_contains($n, 'pathology');
    }

    /**
     * Determine if a facility is strictly a medical laboratory, pathology testing, blood draw station, or diagnostic imaging center.
     */
    public function isStrictMedicalLaboratory(?string $category, ?string $name): bool
    {
        if (empty($name)) {
            return false;
        }

        if ($this->isKnownMedicalLabBrand($name)) {
            return true;
        }

        $cleanName = strtolower(trim($name));
        $catLower = $category ? strtolower(trim($category)) : '';

        // Exclude dental, photo, film, computer, university/research without medical testing
        if (preg_match('/\b(dental\s*lab|dental\s*laboratory|photo\s*lab|film\s*lab|computer\s*lab|computer\s*science)\b/i', $cleanName) ||
            str_contains($catLower, 'dental') ||
            str_contains($catLower, 'photo') ||
            str_contains($catLower, 'computer') ||
            str_contains($catLower, 'veterin') ||
            (str_contains($catLower, 'research') && !str_contains($cleanName, 'diagnostic') && !str_contains($cleanName, 'pathology') && !str_contains($cleanName, 'medical')) ||
            (str_contains($catLower, 'university') && !str_contains($cleanName, 'diagnostic') && !str_contains($cleanName, 'pathology') && !str_contains($cleanName, 'medical'))) {
            return false;
        }

        if (preg_match('/\b(medical\s*laboratory|clinical\s*laboratory|pathology\s*laboratory|blood\s*testing\s*service|diagnostic\s*center|medical\s*diagnostic|diagnostic\s*imaging|blood\s*bank|dna\s*testing|drug\s*testing)\b/i', $catLower)) {
            return true;
        }

        if (preg_match('/\b(pathology|pathlab|diagnostic|diagnostics|blood\s*test|blood\s*draw|draw\s*station|pet\/ct|mri|x-ray|radiology|imaging|pcr|sample\s*collection|phlebotomy)\b/i', $cleanName)) {
            return true;
        }

        // If category is generic "Laboratory" and name contains "Lab" or "Laboratory" but not excluded
        if ($catLower === 'laboratory' && preg_match('/\b(medical|clinical|diagnostic|pathology)\b/i', $cleanName)) {
            return true;
        }

        return false;
    }

    /**
     * Determine if a string is a valid business/laboratory name.
     */
    public function isValidBusinessName(?string $val, string $fallbackCity = '', string $fallbackState = ''): bool
    {
        if ($val === null) {
            return false;
        }

        $clean = trim($val);
        if (strlen($clean) < 2 || strlen($clean) > 120) {
            return false;
        }
        if (str_contains($clean, ';') || str_contains($clean, '{') || str_contains($clean, '}')) {
            return false;
        }
        if (str_starts_with($clean, '0x') || str_starts_with($clean, '0ah') || str_starts_with($clean, 'ChIJ') || str_starts_with($clean, 'CAE')) {
            return false;
        }
        if (preg_match('/^[A-Za-z0-9_-]{18,}$/', $clean)) {
            return false;
        }

        $forbiddenLabels = [
            'directions', 'website', 'call', 'share', 'save', 'results',
            'sponsored', 'send to phone', 'nearby', 'menu', 'overview',
            'reviews', 'about', 'photos', 'claim this business', 'suggest an edit',
            'closed', 'open', 'open 24 hours', 'temporarily closed', 'book online'
        ];
        if (in_array(strtolower($clean), $forbiddenLabels, true)) {
            return false;
        }

        if (preg_match('/^[-+]?\d{1,3}\.\d+,\s*[-+]?\d{1,3}\.\d+$/', $clean)) {
            return false;
        }
        if (preg_match('~^https?://~i', $clean)) {
            return false;
        }

        $lowerVal = strtolower($clean);
        if (!empty($fallbackCity)) {
            $cLower = strtolower($fallbackCity);
            $sLower = strtolower($fallbackState);
            if ($lowerVal === $cLower || $lowerVal === "{$cLower}, {$sLower}" || $lowerVal === "{$cLower}, {$sLower}, usa" || $lowerVal === "{$cLower}, usa") {
                return false;
            }
        }

        return (bool) preg_match('/[a-zA-Z]/', $clean);
    }

    /**
     * Trim and collapse repeated whitespace. Converts empty strings to null.
     */
    public function normalizeWhitespace(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $cleaned = trim(preg_replace('/\s+/', ' ', $value));
        return $cleaned === '' ? null : $cleaned;
    }

    /**
     * Normalize city to Title Case.
     */
    public function normalizeCity(?string $city): ?string
    {
        $clean = $this->normalizeWhitespace($city);
        if ($clean === null) {
            return null;
        }

        return mb_convert_case(mb_strtolower($clean, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Normalize state to uppercase 2-letter abbreviation or uppercase name.
     */
    public function normalizeState(?string $state): ?string
    {
        $clean = $this->normalizeWhitespace($state);
        if ($clean === null) {
            return null;
        }

        return strtoupper($clean);
    }

    /**
     * Normalize ZIP / Postal code strictly as string.
     */
    public function normalizePostalCode(mixed $postalCode): ?string
    {
        if ($postalCode === null) {
            return null;
        }

        $str = trim((string) $postalCode);
        if ($str === '') {
            return null;
        }

        // Standardize 9-digit (ZIP+4) format (e.g. 02111-1234 or 02111 1234)
        if (preg_match('/^(\d{5})[- ]?(\d{4})$/', $str, $m)) {
            return "{$m[1]}-{$m[2]}";
        }

        // Standardize 5-digit format (e.g. "02111", preserving leading zero as string)
        if (preg_match('/^\d{5}$/', $str)) {
            return $str;
        }

        return null;
    }

    /**
     * Normalize phone number format to standard (XXX) XXX-XXXX.
     */
    public function normalizePhone(?string $phone): ?string
    {
        $clean = $this->normalizeWhitespace($phone);
        if ($clean === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $clean);

        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 4));
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return sprintf('+1 (%s) %s-%s', substr($digits, 1, 3), substr($digits, 4, 3), substr($digits, 7, 4));
        }

        return $clean;
    }

    /**
     * Normalize website URL.
     */
    public function normalizeWebsite(?string $website): ?string
    {
        $clean = $this->normalizeWhitespace($website);
        if ($clean === null) {
            return null;
        }

        if (!preg_match('~^(?:f|ht)tps?://~i', $clean)) {
            $clean = 'https://' . $clean;
        }

        return filter_var($clean, FILTER_VALIDATE_URL) ? $clean : null;
    }

    /**
     * Normalize string for comparison (lowercase, alphanumeric only).
     */
    protected function normalizeStringForComparison(?string $str): string
    {
        if ($str === null) {
            return '';
        }

        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $str));
    }

    /**
     * Normalize street address for comparison.
     */
    protected function normalizeAddressForComparison(?string $address): string
    {
        if ($address === null) {
            return '';
        }

        $addr = strtolower($address);
        $addr = preg_replace('/\b(street|st\.)\b/', 'st', $addr);
        $addr = preg_replace('/\b(avenue|ave\.)\b/', 'ave', $addr);
        return preg_replace('/[^a-zA-Z0-9]/', '', $addr);
    }

    /**
     * Determine if a string is a known country name/code.
     */
    protected function isKnownCountry(?string $str): bool
    {
        if (!$str) {
            return false;
        }

        $lower = strtolower(trim($str));
        $countries = [
            'pakistan', 'united states', 'usa', 'u.s.a.', 'united kingdom', 'uk', 'u.k.',
            'canada', 'australia', 'india', 'united arab emirates', 'uae', 'saudi arabia',
            'germany', 'france', 'italy', 'spain', 'brazil', 'mexico', 'japan', 'china',
            'bangladesh', 'sri lanka', 'nepal', 'south africa', 'new zealand', 'ireland',
            'netherlands', 'switzerland', 'sweden', 'norway', 'denmark', 'singapore',
            'malaysia', 'philippines', 'indonesia', 'thailand', 'vietnam', 'egypt', 'turkey'
        ];

        return in_array($lower, $countries, true);
    }
}

