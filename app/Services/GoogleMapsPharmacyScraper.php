<?php

namespace App\Services;

use App\Services\Contracts\PharmacyScraperInterface;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

class GoogleMapsPharmacyScraper implements PharmacyScraperInterface
{
    protected ?string $lastStopReason = null;
    protected ?array $lastMeta = null;

    /**
     * Get the stop reason from the last scraping run.
     */
    public function getLastStopReason(): ?string
    {
        return $this->lastStopReason;
    }

    /**
     * Get metadata from the last scraping run.
     */
    public function getLastMeta(): ?array
    {
        return $this->lastMeta;
    }

    /**
     * Search and scrape pharmacy listings for the specified city and state.
     *
     * @param string $city
     * @param string $state
     * @param int|null $limit
     * @return array<int, array<string, mixed>>
     */
    public function search(string $city, string $state, ?int $limit = null): array
    {
        $this->lastStopReason = null;
        $this->lastMeta = null;

        $scriptPath = base_path('scripts/google-maps-pharmacy-scraper.js');
        $nodeBinary = $this->findNodeBinary();

        if (file_exists($scriptPath)) {
            // '0' indicates unlimited to the node scraper
            $limitArg = ($limit !== null && $limit > 0) ? (string) $limit : '0';
            $command = [$nodeBinary, $scriptPath, $city, $state, $limitArg];

            try {
                $process = new Process($command, base_path(), $this->getProcessEnvironment(), null, 180);
                $process->run();

                $output = trim($process->getOutput());
                $errorOutput = trim($process->getErrorOutput());

                if (!empty($errorOutput)) {
                    Log::debug("Google Maps Playwright scraper stderr: {$errorOutput}");
                }

                if (!empty($output)) {
                    $data = json_decode($output, true);
                    if (is_array($data)) {
                        $this->lastStopReason = $data['stop_reason'] ?? ($data['meta']['stop_reason'] ?? null);
                        $this->lastMeta = $data['meta'] ?? [];

                        $rawResults = $data['results'] ?? [];
                        $validResults = [];
                        foreach ($rawResults as $item) {
                            if (is_array($item) && !empty($item['name']) && $this->isValidBusinessName($item['name'], $city, $state) && $this->isStrictPharmacy($item['category'] ?? null, $item['name'])) {
                                $validResults[] = $item;
                            }
                        }

                        if (!empty($validResults)) {
                            return $validResults;
                        }

                        // If Playwright ran and was challenged by Google, or found 0
                        $savedMeta = $this->lastMeta;
                        $savedStopReason = $this->lastStopReason;
                        $fallback = $this->fetchFromWorldwideDirectory($city, $state, $limit);
                        if (!empty($fallback)) {
                            return $fallback;
                        }

                        $this->lastStopReason = $savedStopReason ?: 'no_listings_found_in_target_location';
                        $this->lastMeta = $savedMeta;
                        return [];
                    }
                }
            } catch (Throwable $e) {
                Log::warning("Primary scraper failed, falling back to worldwide directory: " . $e->getMessage(), [
                    'city' => $city,
                    'state' => $state,
                ]);
            }
        }

        // Fallback to high-reliability worldwide directory search
        return $this->fetchFromWorldwideDirectory($city, $state, $limit);
    }

    /**
     * Build process environment with mandatory Windows system variables.
     * Prevents Node.js Assertion failed: ncrypto::CSPRNG(nullptr, 0) in Apache/PHP-FPM web runtime.
     *
     * @return array<string, string>
     */
    protected function getProcessEnvironment(): array
    {
        $env = [];
        foreach ($_SERVER as $k => $v) {
            if (is_string($v)) {
                $env[$k] = $v;
            }
        }
        foreach ($_ENV as $k => $v) {
            if (is_string($v)) {
                $env[$k] = $v;
            }
        }

        $systemRoot = getenv('SystemRoot') ?: (getenv('WINDIR') ?: 'C:\\Windows');
        $env['SystemRoot'] = $systemRoot;
        $env['WINDIR'] = $systemRoot;
        $env['PATH'] = getenv('PATH') ?: "{$systemRoot}\\system32;{$systemRoot};C:\\Program Files\\nodejs";
        $env['TEMP'] = sys_get_temp_dir();
        $env['TMP'] = sys_get_temp_dir();

        $userProfile = getenv('USERPROFILE') ?: ((getenv('HOMEDRIVE') ?: 'C:') . (getenv('HOMEPATH') ?: '\\Users\\DELL'));
        $env['USERPROFILE'] = $userProfile;
        $env['LOCALAPPDATA'] = getenv('LOCALAPPDATA') ?: "{$userProfile}\\AppData\\Local";
        $env['APPDATA'] = getenv('APPDATA') ?: "{$userProfile}\\AppData\\Roaming";

        return $env;
    }

    /**
     * Resolve the appropriate node executable.
     */
    protected function findNodeBinary(): string
    {
        $candidates = [
            'C:\\Program Files\\nodejs\\node.exe',
            'C:\\Program Files (x86)\\nodejs\\node.exe',
            'D:\\laragon\\bin\\nodejs\\node.exe',
        ];

        foreach ($candidates as $bin) {
            if (file_exists($bin)) {
                return $bin;
            }
        }

        return 'node';
    }

    /**
     * High-reliability multi-source worldwide directory search for pharmacies.
     *
     * @param string $city
     * @param string $state
     * @param int|null $limit
     * @return array<int, array<string, mixed>>
     */
    protected function fetchFromWorldwideDirectory(string $city, string $state, ?int $limit = null): array
    {
        $maxLimit = ($limit !== null && $limit > 0) ? $limit : 150;
        $results = [];
        $seenNames = [];

        // 1. Geocode location with Nominatim to get lat/lon and bounding box
        $lat = null;
        $lon = null;
        $bbox = null;

        try {
            $geoUrl = "https://nominatim.openstreetmap.org/search?q=" . urlencode("{$city}, {$state}") . "&format=json&limit=1";
            $geoRes = \Illuminate\Support\Facades\Http::timeout(8)
                ->withHeaders(['User-Agent' => 'PDMS-Healthcare-System/1.0 (healthcare@pdms.org)'])
                ->get($geoUrl);

            if ($geoRes->successful() && !empty($geoRes->json())) {
                $geoData = $geoRes->json()[0] ?? null;
                $lat = isset($geoData['lat']) ? (float) $geoData['lat'] : null;
                $lon = isset($geoData['lon']) ? (float) $geoData['lon'] : null;
                $bbox = $geoData['boundingbox'] ?? null;
            }
        } catch (Throwable $e) {
            Log::warning("Pharmacy geocoding failed: " . $e->getMessage());
        }

        // 2. Overpass API query (using 50km radius around city coordinates)
        if ($lat !== null && $lon !== null) {
            $overpassServers = [
                'https://overpass-api.de/api/interpreter',
                'https://overpass.kumi.systems/api/interpreter',
            ];

            $overpassQuery = "[out:json][timeout:25];(node[\"amenity\"=\"pharmacy\"](around:50000,{$lat},{$lon});way[\"amenity\"=\"pharmacy\"](around:50000,{$lat},{$lon});node[\"healthcare\"=\"pharmacy\"](around:50000,{$lat},{$lon});way[\"healthcare\"=\"pharmacy\"](around:50000,{$lat},{$lon});node[\"shop\"=\"chemist\"](around:50000,{$lat},{$lon});way[\"shop\"=\"chemist\"](around:50000,{$lat},{$lon});node[\"amenity\"~\"pharmacy|chemist|clinic\"][\"name\"~\"Pharmacy|Chemist|Medical Store|Medicos|Fazal Din|Servaid|Clinix|Walgreens|CVS|Rite Aid\",i](around:50000,{$lat},{$lon}););out center {$maxLimit};";

            foreach ($overpassServers as $serverUrl) {
                try {
                    $opRes = \Illuminate\Support\Facades\Http::timeout(12)
                        ->asForm()
                        ->post($serverUrl, ['data' => $overpassQuery]);

                    if ($opRes->successful()) {
                        $elements = $opRes->json('elements') ?? [];
                        foreach ($elements as $el) {
                            $tags = $el['tags'] ?? [];
                            $name = $tags['name'] ?? ($tags['operator'] ?? ($tags['brand'] ?? null));

                            if (!$name || !$this->isValidBusinessName($name, $city, $state)) {
                                continue;
                            }

                            $lowerName = strtolower(trim($name));
                            if (isset($seenNames[$lowerName])) {
                                continue;
                            }
                            $seenNames[$lowerName] = true;

                            $streetNumber = $tags['addr:housenumber'] ?? '';
                            $streetName = $tags['addr:street'] ?? '';
                            $street = trim("{$streetNumber} {$streetName}");

                            $phone = $tags['phone'] ?? ($tags['contact:phone'] ?? null);
                            $website = $tags['website'] ?? ($tags['contact:website'] ?? null);
                            $postal = $tags['addr:postcode'] ?? null;
                            $elLat = $el['lat'] ?? ($el['center']['lat'] ?? null);
                            $elLng = $el['lon'] ?? ($el['center']['lon'] ?? null);

                            $results[] = [
                                'name' => $name,
                                'street_address' => $street ?: null,
                                'city' => $tags['addr:city'] ?? $city,
                                'state' => $tags['addr:state'] ?? $state,
                                'postal_code' => $postal,
                                'phone' => $phone,
                                'website' => $website,
                                'latitude' => $elLat,
                                'longitude' => $elLng,
                                'google_place_id' => null,
                                'source' => 'live_directory',
                                'external_source_url' => $website,
                            ];

                            if (count($results) >= $maxLimit) {
                                break 2;
                            }
                        }

                        if (!empty($results)) {
                            break;
                        }
                    }
                } catch (Throwable $e) {
                    Log::warning("Overpass server {$serverUrl} failed: " . $e->getMessage());
                }
            }
        }

        if (!empty($results)) {
            $this->lastStopReason = (count($results) >= $maxLimit) ? 'requested_limit_reached' : 'end_of_results_reached';
            return $results;
        }

        // 3. Nominatim Structured & Brand Searches
        try {
            $searchEndpoints = [
                "https://nominatim.openstreetmap.org/search?amenity=pharmacy&city=" . urlencode($city) . "&state=" . urlencode($state) . "&format=json&addressdetails=1&limit={$maxLimit}",
                "https://nominatim.openstreetmap.org/search?q=" . urlencode("pharmacy {$city} {$state}") . "&format=json&addressdetails=1&limit={$maxLimit}",
                "https://nominatim.openstreetmap.org/search?q=" . urlencode("CVS {$city} {$state}") . "&format=json&addressdetails=1&limit=20",
                "https://nominatim.openstreetmap.org/search?q=" . urlencode("Walgreens {$city} {$state}") . "&format=json&addressdetails=1&limit=20",
            ];

            foreach ($searchEndpoints as $searchUrl) {
                $searchRes = \Illuminate\Support\Facades\Http::timeout(10)
                    ->withHeaders(['User-Agent' => 'PDMS-Healthcare-System/1.0 (healthcare@pdms.org)'])
                    ->get($searchUrl);

                if ($searchRes->successful()) {
                    $items = $searchRes->json() ?? [];
                    foreach ($items as $item) {
                        $name = $item['name'] ?? null;
                        if (!$name) {
                            $displayParts = explode(',', $item['display_name'] ?? '');
                            $name = trim($displayParts[0] ?? '');
                        }

                        if (!$name || !$this->isValidBusinessName($name, $city, $state) || !$this->isStrictPharmacy(null, $name)) {
                            continue;
                        }

                        $lowerName = strtolower(trim($name));
                        if (isset($seenNames[$lowerName])) {
                            continue;
                        }
                        $seenNames[$lowerName] = true;

                        $addr = $item['address'] ?? [];
                        $streetNumber = $addr['house_number'] ?? '';
                        $streetName = $addr['road'] ?? '';
                        $street = trim("{$streetNumber} {$streetName}");

                        $results[] = [
                            'name' => $name,
                            'street_address' => $street ?: null,
                            'city' => $addr['city'] ?? ($addr['town'] ?? ($addr['village'] ?? $city)),
                            'state' => $addr['state'] ?? $state,
                            'postal_code' => $addr['postcode'] ?? null,
                            'phone' => null,
                            'website' => null,
                            'latitude' => isset($item['lat']) ? (float) $item['lat'] : null,
                            'longitude' => isset($item['lon']) ? (float) $item['lon'] : null,
                            'google_place_id' => null,
                            'source' => 'live_directory',
                            'external_source_url' => null,
                        ];

                        if (count($results) >= $maxLimit) {
                            break 2;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning("Nominatim direct search failed: " . $e->getMessage());
        }

        if (!empty($results)) {
            $this->lastStopReason = (count($results) >= $maxLimit) ? 'requested_limit_reached' : 'end_of_results_reached';
            return $results;
        }

        $this->lastStopReason = 'no_listings_found_in_target_location';
        return [];
    }

    /**
     * Determine if a string is a valid business/pharmacy name (and not an internal token or UI label).
     */
    public function isValidBusinessName(string $val, string $fallbackCity = '', string $fallbackState = ''): bool
    {
        $val = trim($val);
        if (strlen($val) < 2 || strlen($val) > 120) {
            return false;
        }
        if (str_contains($val, ';') || str_contains($val, '{') || str_contains($val, '}')) {
            return false;
        }
        if (str_starts_with($val, '0x') || str_starts_with($val, '0ah') || str_starts_with($val, 'ChIJ') || str_starts_with($val, 'CAE')) {
            return false;
        }
        if (preg_match('/^[A-Za-z0-9_-]{18,}$/', $val)) {
            return false;
        }

        $forbiddenLabels = [
            'directions', 'website', 'call', 'share', 'save', 'results',
            'sponsored', 'send to phone', 'nearby', 'menu', 'overview',
            'reviews', 'about', 'photos', 'claim this business', 'suggest an edit',
            'closed', 'open', 'open 24 hours', 'temporarily closed', 'book online'
        ];
        if (in_array(strtolower($val), $forbiddenLabels, true)) {
            return false;
        }

        if (preg_match('/^[-+]?\d{1,3}\.\d+,\s*[-+]?\d{1,3}\.\d+$/', $val)) {
            return false;
        }
        if (preg_match('~^https?://~i', $val)) {
            return false;
        }

        // Exclude city/state boundary labels (e.g. "Boston, MA, USA" or "Boston")
        $lowerVal = strtolower($val);
        if (!empty($fallbackCity)) {
            $cLower = strtolower($fallbackCity);
            $sLower = strtolower($fallbackState);
            if ($lowerVal === $cLower || $lowerVal === "{$cLower}, {$sLower}" || $lowerVal === "{$cLower}, {$sLower}, usa" || $lowerVal === "{$cLower}, usa") {
                return false;
            }
        }

        return (bool) preg_match('/[a-zA-Z]/', $val);
    }

    /**
     * Strict check to ensure a business is genuinely a pharmacy/drug store and not a non-pharmacy retail entity.
     */
    public function isStrictPharmacy(?string $category, ?string $name): bool
    {
        return PharmacyImportService::isStrictPharmacy($category, $name);
    }
}
