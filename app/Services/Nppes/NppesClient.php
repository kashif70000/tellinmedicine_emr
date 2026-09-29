<?php

namespace App\Services\Nppes;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class NppesClient
{
    protected string $baseUrl;
    protected string $apiVersion;
    protected int $timeout;
    protected int $requestDelayMs;
    protected int $cacheTtlMinutes;
    protected int $maxRetries;
    protected bool $hadNetworkFailure = false;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('nppes.base_url', 'https://npiregistry.cms.hhs.gov/api/'), '/') . '/';
        $this->apiVersion = config('nppes.api_version', '2.1');
        $this->timeout = (int) config('nppes.timeout', 12);
        $this->requestDelayMs = (int) config('nppes.request_delay_ms', 250);
        $this->cacheTtlMinutes = (int) config('nppes.cache_ttl_minutes', 60);
        $this->maxRetries = (int) config('nppes.max_retries', 2);
    }

    /**
     * Check if the last search experienced a network or server failure.
     */
    public function hadNetworkFailure(): bool
    {
        return $this->hadNetworkFailure;
    }

    /**
     * Search for healthcare organizations in the NPPES Registry using progressive search strategies.
     *
     * @param string $organizationName
     * @param string|null $city
     * @param string|null $state
     * @param string|null $postalCode
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function search(
        string $organizationName,
        ?string $city = null,
        ?string $state = null,
        ?string $postalCode = null,
        int $limit = 10
    ): array {
        $this->hadNetworkFailure = false;
        $cleanName = $this->cleanOrganizationNameForSearch($organizationName);
        $cleanCity = $city ? trim($city) : null;
        $cleanState = $state ? strtoupper(trim($state)) : null;
        $baseZip = $postalCode ? $this->extractBaseZip($postalCode) : null;

        $allCandidates = [];
        $seenNpis = [];

        // Strategy 1: Name + City + State + ZIP (Most specific)
        if (!empty($cleanName) && !empty($cleanCity) && !empty($cleanState) && !empty($baseZip)) {
            $candidates = $this->executeQuery([
                'organization_name' => $cleanName . '*',
                'city' => $cleanCity,
                'state' => $cleanState,
                'postal_code' => $baseZip,
                'limit' => $limit,
            ]);

            foreach ($candidates as $c) {
                if (!isset($seenNpis[$c['npi']])) {
                    $seenNpis[$c['npi']] = true;
                    $c['search_strategy'] = 'name_city_state_zip';
                    $allCandidates[] = $c;
                }
            }

            if (!empty($allCandidates)) {
                return $allCandidates;
            }
        }

        // Strategy 2: Core Brand / Keyword + ZIP + State (Very reliable for branches in a postal code)
        $coreBrand = $this->extractCoreBrandName($organizationName);
        if (!empty($coreBrand) && !empty($baseZip) && !empty($cleanState)) {
            $candidates = $this->executeQuery([
                'organization_name' => $coreBrand . '*',
                'postal_code' => $baseZip,
                'state' => $cleanState,
                'limit' => $limit,
            ]);

            foreach ($candidates as $c) {
                if (!isset($seenNpis[$c['npi']])) {
                    $seenNpis[$c['npi']] = true;
                    $c['search_strategy'] = 'core_brand_zip_state';
                    $allCandidates[] = $c;
                }
            }

            if (!empty($allCandidates)) {
                return $allCandidates;
            }
        }

        // Strategy 3: Name / Core Brand + City + State
        if (!empty($cleanCity) && !empty($cleanState)) {
            $searchTerm = !empty($coreBrand) ? $coreBrand : $cleanName;
            $candidates = $this->executeQuery([
                'organization_name' => $searchTerm . '*',
                'city' => $cleanCity,
                'state' => $cleanState,
                'limit' => $limit,
            ]);

            foreach ($candidates as $c) {
                if (!isset($seenNpis[$c['npi']])) {
                    $seenNpis[$c['npi']] = true;
                    $c['search_strategy'] = 'name_city_state';
                    $allCandidates[] = $c;
                }
            }

            if (!empty($allCandidates)) {
                return $allCandidates;
            }
        }

        // Strategy 4: Fallback to exact non-wildcard queries for backward compatibility
        if (!empty($cleanName) && !empty($cleanCity) && !empty($cleanState)) {
            $candidates = $this->executeQuery([
                'organization_name' => $cleanName,
                'city' => $cleanCity,
                'state' => $cleanState,
                'limit' => $limit,
            ]);

            foreach ($candidates as $c) {
                if (!isset($seenNpis[$c['npi']])) {
                    $seenNpis[$c['npi']] = true;
                    $c['search_strategy'] = 'name_city_state_exact';
                    $allCandidates[] = $c;
                }
            }
        }

        return $allCandidates;
    }

    /**
     * Execute query against NPPES API with caching and retry logic.
     *
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function executeQuery(array $params): array
    {
        $queryParams = array_merge([
            'version' => $this->apiVersion,
            'enumeration_type' => 'NPI-2', // Healthcare Organizations only
        ], $params);

        // Remove null / empty values
        $queryParams = array_filter($queryParams, fn($v) => $v !== null && $v !== '');

        $cacheKey = 'nppes_query_' . md5(json_encode($queryParams));

        return Cache::remember($cacheKey, now()->addMinutes($this->cacheTtlMinutes), function () use ($queryParams) {
            if ($this->requestDelayMs > 0) {
                usleep($this->requestDelayMs * 1000);
            }

            $attempt = 0;
            while ($attempt <= $this->maxRetries) {
                $attempt++;
                try {
                    $response = Http::timeout($this->timeout)
                        ->withHeaders([
                            'User-Agent' => 'PDMS-Healthcare-Directory/1.0 (healthcare@pdms.org)',
                            'Accept' => 'application/json',
                        ])
                        ->get($this->baseUrl, $queryParams);

                    if ($response->successful()) {
                        $data = $response->json();
                        $results = $data['results'] ?? [];
                        return $this->normalizeNppesResults($results);
                    }

                    // Transient rate limiting or server errors -> retry with backoff
                    if (in_array($response->status(), [429, 500, 502, 503, 504], true)) {
                        Log::warning("NPPES API returned HTTP {$response->status()} on attempt {$attempt}", [
                            'params' => $queryParams,
                        ]);
                        if ($attempt <= $this->maxRetries) {
                            usleep(($attempt * 300) * 1000);
                            continue;
                        }
                        $this->hadNetworkFailure = true;
                        return [];
                    }

                    // 400/404 or other non-retryable response
                    Log::info("NPPES API query returned {$response->status()}", [
                        'params' => $queryParams,
                        'body' => substr($response->body(), 0, 300),
                    ]);
                    return [];
                } catch (Throwable $e) {
                    Log::warning("NPPES API exception on attempt {$attempt}: " . $e->getMessage(), [
                        'params' => $queryParams,
                    ]);
                    if ($attempt <= $this->maxRetries) {
                        usleep(($attempt * 300) * 1000);
                        continue;
                    }
                    $this->hadNetworkFailure = true;
                    return [];
                }
            }

            return [];
        });
    }

    /**
     * Normalize raw NPPES results into clean structured candidates.
     *
     * @param array<int, array<string, mixed>> $rawResults
     * @return array<int, array<string, mixed>>
     */
    public function normalizeNppesResults(array $rawResults): array
    {
        $normalized = [];

        foreach ($rawResults as $item) {
            $npi = (string) ($item['number'] ?? '');
            if (!preg_match('/^\d{10}$/', $npi)) {
                continue;
            }

            $basic = $item['basic'] ?? [];
            $orgName = $basic['organization_name'] ?? ($basic['name'] ?? '');
            $doingBusinessAs = $basic['name_prefix'] ?? null; // Sometimes DBA is in other_names
            $otherNames = [];
            if (!empty($item['other_names']) && is_array($item['other_names'])) {
                foreach ($item['other_names'] as $on) {
                    if (!empty($on['organization_name'])) {
                        $otherNames[] = $on['organization_name'];
                    }
                }
            }

            // Extract Practice Location vs Mailing Address
            $practiceAddress = null;
            $mailingAddress = null;

            $addresses = $item['addresses'] ?? [];
            foreach ($addresses as $addr) {
                $addressType = $addr['address_purpose'] ?? '';
                $parsedAddr = [
                    'address_purpose' => $addressType,
                    'address_1' => $addr['address_1'] ?? null,
                    'address_2' => $addr['address_2'] ?? null,
                    'city' => $addr['city'] ?? null,
                    'state' => $addr['state'] ?? null,
                    'postal_code' => $addr['postal_code'] ?? null,
                    'country_name' => $addr['country_name'] ?? null,
                    'telephone_number' => $addr['telephone_number'] ?? null,
                    'fax_number' => $addr['fax_number'] ?? null,
                ];

                if ($addressType === 'LOCATION') {
                    $practiceAddress = $parsedAddr;
                } elseif ($addressType === 'MAILING') {
                    $mailingAddress = $parsedAddr;
                }
            }

            // Fallback: If only one address exists, use it
            if (!$practiceAddress && $mailingAddress) {
                $practiceAddress = $mailingAddress;
            }

            $taxonomies = [];
            if (!empty($item['taxonomies']) && is_array($item['taxonomies'])) {
                foreach ($item['taxonomies'] as $tax) {
                    $taxonomies[] = [
                        'code' => $tax['code'] ?? null,
                        'desc' => $tax['desc'] ?? null,
                        'primary' => (bool) ($tax['primary'] ?? false),
                    ];
                }
            }

            $normalized[] = [
                'npi' => $npi,
                'organization_name' => $orgName,
                'other_names' => $otherNames,
                'practice_address' => $practiceAddress,
                'mailing_address' => $mailingAddress,
                'taxonomies' => $taxonomies,
                'enumeration_date' => $basic['enumeration_date'] ?? null,
                'last_updated' => $basic['last_updated'] ?? null,
                'raw' => $item,
            ];
        }

        return $normalized;
    }

    /**
     * Clean organization name for NPPES query (removing store numbers, URLs, and punctuation).
     */
    public function cleanOrganizationNameForSearch(string $name): string
    {
        $clean = preg_replace('/#\d+/', '', $name);
        $clean = preg_replace('/\bste\s*\d+\b/i', '', $clean);
        $clean = preg_replace('/[^\w\s]/', ' ', $clean);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        return substr($clean, 0, 100);
    }

    /**
     * Extract core recognized brand name for broader queries (e.g. "CVS Pharmacy #1234" -> "CVS PHARMACY").
     */
    public function extractCoreBrandName(string $name): string
    {
        $lower = strtolower($name);

        if (str_contains($lower, 'cvs')) {
            return 'CVS PHARMACY';
        }
        if (str_contains($lower, 'walgreens')) {
            return 'WALGREENS';
        }
        if (str_contains($lower, 'quest')) {
            return 'QUEST DIAGNOSTICS';
        }
        if (str_contains($lower, 'labcorp') || str_contains($lower, 'laboratory corporation')) {
            return 'LABORATORY CORPORATION OF AMERICA';
        }
        if (str_contains($lower, 'walmart')) {
            return 'WALMART';
        }
        if (str_contains($lower, 'stop & shop') || str_contains($lower, 'stop and shop')) {
            return 'STOP & SHOP';
        }
        if (str_contains($lower, 'shaw')) {
            return "SHAW'S";
        }
        if (str_contains($lower, 'rite aid')) {
            return 'RITE AID';
        }

        return $this->cleanOrganizationNameForSearch($name);
    }

    /**
     * Extract 5-digit base ZIP code.
     */
    public function extractBaseZip(?string $postalCode): ?string
    {
        if (!$postalCode) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $postalCode);
        if (strlen($digits) >= 5) {
            return substr($digits, 0, 5);
        }

        return null;
    }
}
