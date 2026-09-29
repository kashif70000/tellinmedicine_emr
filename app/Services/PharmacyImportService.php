<?php

namespace App\Services;

use App\Models\Pharmacy;
use App\Services\Contracts\PharmacyScraperInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class PharmacyImportService
{
    public function __construct(
        protected PharmacyScraperInterface $scraper
    ) {}

    /**
     * Import pharmacies for a given city and state.
     *
     * @param string $city
     * @param string $state
     * @param int|null $limit
     * @param bool $dryRun
     * @return array{city: string, found: int, inserted: int, updated: int, skipped: int, failed: int, details: array}
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
            'details' => [],
        ];

        // 1. Fetch raw scraped results
        try {
            $rawRecords = $this->scraper->search($normalizedCity, $normalizedState, $limit);
        } catch (Throwable $e) {
            Log::error('Pharmacy scraper failed to execute search: ' . $e->getMessage(), [
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
                        'reason' => 'Missing or empty pharmacy name',
                        'raw' => $raw,
                    ];
                    continue;
                }

                // Check strict pharmacy qualification
                if (!self::isStrictPharmacy($raw['category'] ?? null, $normalized['name'])) {
                    $stats['skipped']++;
                    $stats['details'][] = [
                        'status' => 'skipped',
                        'reason' => 'Excluded non-pharmacy or department profile',
                        'raw' => $raw,
                    ];
                    continue;
                }

                $outcome = $this->processRecord($normalized, $dryRun);
                $stats[$outcome['action']]++;
                $stats['details'][] = $outcome;
            } catch (Throwable $e) {
                $stats['failed']++;
                Log::warning('Error processing individual pharmacy record: ' . $e->getMessage(), [
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
     * Process a single normalized pharmacy record with deduplication and safe update logic.
     *
     * @param array<string, mixed> $data
     * @param bool $dryRun
     * @return array{action: 'inserted'|'updated'|'skipped', pharmacy: ?Pharmacy, name: string}
     */
    public function processRecord(array $data, bool $dryRun = false): array
    {
        $existing = $this->findExistingPharmacy($data);

        if ($existing) {
            // Check if there are useful non-empty fields to update
            $updates = $this->determineSafeUpdates($existing, $data);

            if (!empty($updates)) {
                if (!$dryRun) {
                    $existing->update($updates);
                }
                return [
                    'action' => 'updated',
                    'pharmacy' => $existing,
                    'name' => $existing->name,
                    'updated_fields' => array_keys($updates),
                ];
            }

            return [
                'action' => 'skipped',
                'pharmacy' => $existing,
                'name' => $existing->name,
                'reason' => 'No new or updated information available',
            ];
        }

        // Insert new pharmacy record
        $pharmacy = null;
        if (!$dryRun) {
            $pharmacy = Pharmacy::create($data);
        }

        return [
            'action' => 'inserted',
            'pharmacy' => $pharmacy,
            'name' => $data['name'],
        ];
    }

    /**
     * Find existing pharmacy using strict deduplication precedence.
     * 1. Match by google_place_id if present.
     * 2. Match by normalized name + street_address + city + state + postal_code.
     * 3. Fallback: match by normalized name + street_address + city + state when address exists.
     */
    public function findExistingPharmacy(array $data): ?Pharmacy
    {
        // 1. Strongest identity: google_place_id
        if (!empty($data['google_place_id'])) {
            $match = Pharmacy::where('google_place_id', $data['google_place_id'])->first();
            if ($match) {
                return $match;
            }
        }

        // 2. Compound identity: name + street_address + city + state + postal_code
        if (!empty($data['name']) && !empty($data['street_address']) && !empty($data['city']) && !empty($data['state'])) {
            $query = Pharmacy::where('city', $data['city'])
                ->where('state', $data['state']);

            // Fetch candidate pharmacies in the same city/state
            $candidates = $query->get();

            foreach ($candidates as $candidate) {
                if ($this->isSamePharmacy($candidate, $data)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Compare an existing pharmacy model against incoming normalized data.
     */
    protected function isSamePharmacy(Pharmacy $existing, array $incoming): bool
    {
        $existingName = $this->normalizeStringForComparison($existing->name);
        $incomingName = $this->normalizeStringForComparison($incoming['name']);

        $nameMatches = ($existingName === $incomingName);
        if (!$nameMatches) {
            $existingBrand = $this->normalizeBrandForComparison($existing->name);
            $incomingBrand = $this->normalizeBrandForComparison($incoming['name']);
            if (!empty($existingBrand) && !empty($incomingBrand) && ($existingBrand === $incomingBrand)) {
                $nameMatches = true;
            }
        }

        // Must match either exact normalized name or normalized brand
        if (!$nameMatches) {
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
        // between distinct branches of chains in the same city
        return false;
    }

    /**
     * Determine which fields should be safely updated on the existing record.
     * NEVER overwrite non-empty existing values with null/empty incoming values.
     *
     * @param Pharmacy $existing
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    public function determineSafeUpdates(Pharmacy $existing, array $incoming): array
    {
        $updatableFields = [
            'name',
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

            // Rule: Do not replace useful existing values with null or empty values
            if ($newValue === null || $newValue === '') {
                continue;
            }

            // If current value is empty or null, take the new value
            if ($currentValue === null || $currentValue === '') {
                $updates[$field] = $newValue;
                continue;
            }

            // If coordinates, check if new values are more precise or filling missing
            if (in_array($field, ['latitude', 'longitude'], true)) {
                if ($currentValue === null && $newValue !== null) {
                    $updates[$field] = $newValue;
                }
                continue;
            }

            // Upgrade generic brand name (e.g. "CVS") to complete pharmacy name ("CVS Pharmacy")
            if ($field === 'name') {
                if (!empty($newValue) && self::hasPharmacyKeyword(strtolower((string) $newValue)) && !self::hasPharmacyKeyword(strtolower((string) $currentValue))) {
                    $updates['name'] = $newValue;
                }
                continue;
            }

            // If current value is different and new value is non-empty
            if ($currentValue !== $newValue) {
                // Keep stronger or newer fields
                $updates[$field] = $newValue;
            }
        }

        return $updates;
    }

    /**
     * Normalize all fields of a raw scraped pharmacy record.
     *
     * @param array<string, mixed> $raw
     * @param string $defaultCity
     * @param string $defaultState
     * @return array<string, mixed>
     */
    public function normalizeRecord(array $raw, string $defaultCity, string $defaultState): array
    {
        $name = $this->normalizeName($raw['name'] ?? null, $defaultCity, $defaultState);
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
     * Normalize and validate a business/pharmacy name.
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
     * Determine if a string is a valid business/pharmacy name (and not an internal token or UI label).
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

        // Extract digits
        $digits = preg_replace('/\D+/', '', $clean);

        // US standard 10 digits
        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 4));
        }

        // US 11 digits starting with 1
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return sprintf('+1 (%s) %s-%s', substr($digits, 1, 3), substr($digits, 4, 3), substr($digits, 7, 4));
        }

        // Return cleaned original if international or non-standard format
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

        // Add https:// scheme if missing
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
     * Strip temporary clinical/drive-thru testing prefixes from pharmacy business names.
     */
    public function cleanPharmacyName(string $name): string
    {
        return Pharmacy::cleanDisplayName($name);
    }

    /**
     * Normalize street address for comparison (standardizing St, Ave, etc.).
     */
    protected function normalizeAddressForComparison(?string $address): string
    {
        if ($address === null) {
            return '';
        }

        $addr = strtolower($address);
        $addr = preg_replace('/\b(street|st\.)\b/', 'st', $addr);
        $addr = preg_replace('/\b(avenue|ave\.)\b/', 'ave', $addr);
        $addr = preg_replace('/\b(road|rd\.)\b/', 'rd', $addr);
        $addr = preg_replace('/\b(boulevard|blvd\.)\b/', 'blvd', $addr);
        $addr = preg_replace('/\b(drive|dr\.)\b/', 'dr', $addr);

        return preg_replace('/[^a-zA-Z0-9]/', '', $addr);
    }

    /**
     * Determine if a business is genuinely a licensed pharmacy or drug store.
     */
    public static function isStrictPharmacy(?string $category, ?string $name): bool
    {
        if (empty($name)) {
            return false;
        }

        $n = strtolower(trim($name));
        $cat = strtolower(trim($category ?? ''));

        // Excluded business name terms
        $excludedNamePatterns = [
            'storage', 'self storage', 'public storage', 'extra space', 'cubesmart', 'life storage',
            'the ups store', 'ups store', 'fedex', 'usps', 'post office', 'postal service', 'shipping',
            'photo', 'print shop', 'printing',
            'dollar general', 'dollar tree', 'family dollar', '99 cents', 'five below',
            'ulta beauty', 'sephora', 'sally beauty', 'cosmetics', 'beauty supply', 'nail salon', 'hair salon', 'barber',
            'dispensary', 'cannabis', 'marijuana', 'weed', 'cbd store', 'smoke shop', 'vape shop', 'hookah',
            'cumberland farms', '7-eleven', 'circle k', 'wawa', 'sheetz', 'speedway', 'gas station', 'convenience store', 'pick n pay',
            'minuteclinic', 'urgent care', 'covid-19 drive-thru', 'covid-19 testing', 'testing site',
            'optometry', 'eyecare', 'vision center', 'dentist', 'dental', 'veterinary', 'animal hospital'
        ];

        foreach ($excludedNamePatterns as $pattern) {
            if (str_contains($n, $pattern)) {
                return false;
            }
        }

        // Excluded category terms
        $excludedCategories = [
            'self-storage', 'storage facility', 'shipping service', 'photo shop', 'photo lab', 'dollar store',
            'cosmetics store', 'cannabis store', 'dispensary', 'convenience store', 'gas station', 'urgent care',
            'medical clinic', 'walk-in clinic', 'supermarket', 'department store', 'grocery store', 'optometrist', 'dentist'
        ];

        foreach ($excludedCategories as $exc) {
            if (str_contains($cat, $exc) && !self::hasPharmacyKeyword($n)) {
                return false;
            }
        }

        // Positive check: Known standalone pharmacy chains
        $knownPharmacyChains = [
            'cvs', 'walgreens', 'rite aid', 'boots', 'duane reade', 'fazal din', 'servaid',
            'clinix', 'dvago', 'apollo pharmacy', 'medplus', 'guardian pharmacy', 'chemist warehouse'
        ];

        foreach ($knownPharmacyChains as $chain) {
            if (str_contains($n, $chain)) {
                return true;
            }
        }

        // Positive check: Must contain pharmacy keyword in name or category
        if (self::hasPharmacyKeyword($n) || self::hasPharmacyKeyword($cat)) {
            return true;
        }

        return false;
    }

    /**
     * Check if a string contains pharmacy keywords.
     */
    public static function hasPharmacyKeyword(string $text): bool
    {
        $lower = strtolower($text);
        return str_contains($lower, 'pharmacy') ||
               str_contains($lower, 'pharmacies') ||
               str_contains($lower, 'drug store') ||
               str_contains($lower, 'drugstore') ||
               str_contains($lower, 'chemist') ||
               str_contains($lower, 'apothecary') ||
               str_contains($lower, 'medical store') ||
               str_contains($lower, 'farmacia') ||
               str_contains($lower, 'prescription') ||
               str_contains($lower, ' rx') ||
               str_ends_with($lower, 'rx');
    }

    /**
     * Normalize brand name for cross-variant comparison (stripping pharmacy/store suffixes).
     */
    public function normalizeBrandForComparison(?string $str): string
    {
        if ($str === null) {
            return '';
        }

        $clean = strtolower($str);
        $clean = preg_replace('/\b(pharmacy|pharmacies|drug\s*store|drugstore|chemist|apothecary|medical\s*store|rx|store|supercenter|market|supermarket)\b/i', '', $clean);
        $clean = preg_replace('/#\d+/', '', $clean);
        $clean = preg_replace('/\bste\s*\d+\b/i', '', $clean);

        return preg_replace('/[^a-zA-Z0-9]/', '', (string) $clean);
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


