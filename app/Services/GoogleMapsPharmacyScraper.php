<?php

namespace App\Services;

use App\Services\Contracts\PharmacyScraperInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleMapsPharmacyScraper implements PharmacyScraperInterface
{
    protected ?string $lastStopReason = null;
    protected ?array $lastMeta = null;

    public function getLastStopReason(): ?string
    {
        return $this->lastStopReason;
    }

    public function getLastMeta(): ?array
    {
        return $this->lastMeta;
    }

    public function search(string $city, string $state, ?int $limit = null): array
    {
        $this->lastStopReason = null;
        $this->lastMeta = null;

        $maxLimit = ($limit !== null && $limit > 0) ? $limit : 2000;
        $results = [];
        $seen = [];

        $userAgent = 'PDMS-Healthcare-System/1.0 (healthcare@pdms.org)';

        $http = fn() => Http::withHeaders([
            'User-Agent' => $userAgent,
            'Accept'     => 'application/json',
        ]);

        // ---------------------------------------------------------------------
        // 1. RESOLVE CITY CENTER (Nominatim Geocoding API)
        // ---------------------------------------------------------------------
        $centerLat = null;
        $centerLon = null;

        try {
            $res = $http()->timeout(10)->get('https://nominatim.openstreetmap.org/search', [
                'q'      => "{$city}, {$state}",
                'format' => 'json',
                'limit'  => 1,
            ]);

            if ($res->successful() && !empty($res->json())) {
                $centerLat = (float) $res->json()[0]['lat'];
                $centerLon = (float) $res->json()[0]['lon'];
                Log::info("DEBUG [Geocode]: Resolved {$city}, {$state} to Center [{$centerLat}, {$centerLon}]");
            }
        } catch (Throwable $e) {
            Log::error('DEBUG [Geocode Exception]: ' . $e->getMessage());
        }

        if (!$centerLat || !$centerLon) {
            $this->lastStopReason = 'could_not_resolve_city_coordinates';
            $this->lastMeta = ['total_found' => 0];
            return [];
        }

        // ---------------------------------------------------------------------
        // 2. UNIVERSAL PROCESSOR & ADDITION HELPER
        // ---------------------------------------------------------------------
        $processApiSource = function (string $sourceName, iterable $items) use (&$results, &$seen, $maxLimit, $city, $state) {
            $rawFetched = 0;
            $uniqueAdded = 0;

            foreach ($items as $item) {
                $rawFetched++;

                $name = trim($item['name'] ?? '');
                $street = trim($item['street'] ?? '');
                $postal = trim($item['postal'] ?? '');
                $phone = trim($item['phone'] ?? '');
                $website = trim($item['website'] ?? '');
                $lat = $item['lat'] ?? null;
                $lon = $item['lon'] ?? null;
                $placeId = $item['place_id'] ?? null;

                if (strlen($name) < 2 || in_array(strtolower($name), ['pharmacy', 'chemist', 'drugstore'])) {
                    continue;
                }

                $cleanName = preg_replace('/[^a-z0-9]/', '', strtolower($name));
                $key = $cleanName . '|' . ($lat ? round((float)$lat, 4) : 'x') . '|' . ($lon ? round((float)$lon, 4) : 'x');
                
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                if (count($results) >= $maxLimit) {
                    break;
                }

                $results[] = [
                    'name'                => $name,
                    'street_address'      => $street ?: null,
                    'city'                => ucwords(strtolower($city)),
                    'state'               => ucwords(strtolower($state)),
                    'postal_code'         => $postal ?: null,
                    'phone'               => $phone ?: null,
                    'website'             => $website ?: null,
                    'latitude'            => $lat !== null ? (float) $lat : null,
                    'longitude'           => $lon !== null ? (float) $lon : null,
                    'google_place_id'     => $placeId,
                    'source'              => $sourceName,
                    'external_source_url' => $website ?: null,
                ];
                $uniqueAdded++;
            }

            Log::info("DEBUG [API Summary - {$sourceName}] => Raw Fetched: {$rawFetched} | Unique Added: {$uniqueAdded}");
        };

        // ---------------------------------------------------------------------
        // 3. FOURSQUARE PLACES API v3
        // ---------------------------------------------------------------------
        $fsqKey = env('FOURSQUARE_API_KEY');
        if ($fsqKey) {
            try {
                $res = Http::withHeaders([
                    'Authorization' => trim($fsqKey),
                    'Accept'        => 'application/json',
                ])->timeout(12)->get('https://api.foursquare.com/v3/places/search', [
                    'll'     => "{$centerLat},{$centerLon}",
                    'query'  => 'pharmacy',
                    'radius' => 30000,
                    'limit'  => 50,
                ]);

                if ($res->successful()) {
                    $items = [];
                    foreach ($res->json('results') ?? [] as $fsq) {
                        $loc = $fsq['location'] ?? [];
                        $geos = $fsq['geocodes']['main'] ?? [];
                        $items[] = [
                            'name'    => $fsq['name'] ?? null,
                            'street'  => $loc['address'] ?? null,
                            'postal'  => $loc['postcode'] ?? null,
                            'phone'   => $fsq['tel'] ?? null,
                            'website' => $fsq['website'] ?? null,
                            'lat'     => $geos['latitude'] ?? null,
                            'lon'     => $geos['longitude'] ?? null,
                        ];
                    }
                    $processApiSource('foursquare', $items);
                } else {
                    Log::warning("DEBUG [Foursquare Failed]: Status " . $res->status() . " - Body: " . $res->body());
                }
            } catch (Throwable $e) {
                Log::error("DEBUG [Foursquare Exception]: " . $e->getMessage());
            }
        }

        // ---------------------------------------------------------------------
        // 4. MAPBOX SEARCH & GEOCODING API
        // ---------------------------------------------------------------------
        $mapboxKey = env('MAPBOX_ACCESS_TOKEN');
        if ($mapboxKey) {
            try {
                $res = $http()->timeout(10)->get("https://api.mapbox.com/search/geocode/v6/forward", [
                    'q'            => "pharmacy {$city}",
                    'access_token' => $mapboxKey,
                    'proximity'    => "{$centerLon},{$centerLat}",
                    'limit'        => 20,
                ]);

                if ($res->successful()) {
                    $mapboxItems = [];
                    foreach ($res->json('features') ?? [] as $feature) {
                        $coords = $feature['geometry']['coordinates'] ?? [null, null];
                        $props = $feature['properties'] ?? [];
                        $featureType = $props['feature_type'] ?? '';
                        $name = $props['name'] ?? ($props['place_name'] ?? '');

                        if (in_array($featureType, ['street', 'address', 'locality', 'place', 'region', 'country'])) {
                            continue;
                        }

                        $lowerName = strtolower($name);
                        if (str_contains($lowerName, 'expressway') || str_contains($lowerName, 'highway') || str_contains($lowerName, 'road')) {
                            continue;
                        }
                        
                        $mapboxItems[] = [
                            'name'    => $name,
                            'street'  => $props['address'] ?? null,
                            'postal'  => $props['context']['postcode']['name'] ?? null,
                            'phone'   => null,
                            'website' => null,
                            'lat'     => $coords[1] ?? null,
                            'lon'     => $coords[0] ?? null,
                        ];
                    }
                    $processApiSource('mapbox', $mapboxItems);
                } else {
                    Log::warning("DEBUG [Mapbox Failed]: Status " . $res->status() . " - Body: " . $res->body());
                }
            } catch (Throwable $e) {
                Log::error("DEBUG [Mapbox Exception]: " . $e->getMessage());
            }
        }

        // ---------------------------------------------------------------------
        // 5. GEOAPIFY PLACES API (Optimized Grid Scan)
        // ---------------------------------------------------------------------
        $geoKey = env('GEOAPIFY_API_KEY');
        if ($geoKey) {
            $categories = 'healthcare.pharmacy,commercial.health_and_beauty.pharmacy,commercial.health_and_beauty.medical_supply';
            $offset = 0.06;
            $geoItems = [];

            for ($i = -1; $i <= 1; $i++) {
                for ($j = -1; $j <= 1; $j++) {
                    $lat = $centerLat + ($i * $offset);
                    $lon = $centerLon + ($j * $offset);

                    try {
                        $res = $http()->timeout(8)->get('https://api.geoapify.com/v2/places', [
                            'categories' => $categories,
                            'filter'     => "circle:{$lon},{$lat},10000",
                            'limit'      => 50,
                            'apiKey'     => $geoKey,
                        ]);

                        if ($res->successful()) {
                            foreach ($res->json('features') ?? [] as $f) {
                                $p = $f['properties'] ?? [];
                                $c = $f['geometry']['coordinates'] ?? [null, null];
                                $geoItems[] = [
                                    'name'    => $p['name'] ?? null,
                                    'street'  => $p['address_line1'] ?? null,
                                    'postal'  => $p['postcode'] ?? null,
                                    'phone'   => $p['contact']['phone'] ?? null,
                                    'website' => $p['website'] ?? null,
                                    'lat'     => $c[1] ?? null,
                                    'lon'     => $c[0] ?? null,
                                ];
                            }
                        }
                    } catch (Throwable $e) {
                        Log::warning("DEBUG [Geoapify Error]: " . $e->getMessage());
                    }
                    usleep(100000);
                }
            }
            $processApiSource('geoapify', $geoItems);
        }

        // ---------------------------------------------------------------------
        // 6. LOCATIONIQ NEARBY POI API
        // ---------------------------------------------------------------------
        $liqKey = env('LOCATIONIQ_API_KEY');
        if ($liqKey) {
            try {
                $res = $http()->timeout(12)->get('https://us1.locationiq.com/v1/nearby', [
                    'key'    => $liqKey,
                    'lat'    => $centerLat,
                    'lon'    => $centerLon,
                    'tag'    => 'pharmacy',
                    'radius' => 25000,
                    'limit'  => 50,
                    'format' => 'json',
                ]);

                if ($res->successful()) {
                    $liqItems = [];
                    foreach ($res->json() ?? [] as $item) {
                        $liqItems[] = [
                            'name'   => $item['name'] ?? ($item['display_name'] ?? null),
                            'lat'    => $item['lat'] ?? null,
                            'lon'    => $item['lon'] ?? null,
                        ];
                    }
                    $processApiSource('locationiq', $liqItems);
                }
            } catch (Throwable $e) {
                Log::error("DEBUG [LocationIQ Exception]: " . $e->getMessage());
            }
        }

        // ---------------------------------------------------------------------
        // 7. OVERPASS API (Optimized Radius to prevent 504 timeout)
        // ---------------------------------------------------------------------
        $query = "[out:json][timeout:30];
        (
        node[\"amenity\"=\"pharmacy\"](around:25000,{$centerLat},{$centerLon});
        way[\"amenity\"=\"pharmacy\"](around:25000,{$centerLat},{$centerLon});
        node[\"shop\"=\"chemist\"](around:25000,{$centerLat},{$centerLon});
        node[\"shop\"=\"drugstore\"](around:25000,{$centerLat},{$centerLon});
        );
        out center 300;";

        try {
            $res = $http()->timeout(30)->asForm()->post('https://overpass-api.de/api/interpreter', [
                'data' => $query,
            ]);

            if ($res->successful()) {
                $osmItems = [];
                foreach ($res->json('elements') ?? [] as $el) {
                    $tags = $el['tags'] ?? [];
                    $street = trim(($tags['addr:housenumber'] ?? '') . ' ' . ($tags['addr:street'] ?? ''));
                    $osmItems[] = [
                        'name'    => $tags['name'] ?? ($tags['operator'] ?? null),
                        'street'  => $street ?: null,
                        'postal'  => $tags['addr:postcode'] ?? null,
                        'phone'   => $tags['phone'] ?? ($tags['contact:phone'] ?? null),
                        'website' => $tags['website'] ?? ($tags['contact:website'] ?? null),
                        'lat'     => $el['lat'] ?? ($el['center']['lat'] ?? null),
                        'lon'     => $el['lon'] ?? ($el['center']['lon'] ?? null),
                    ];
                }
                $processApiSource('osm_overpass', $osmItems);
            }
        } catch (Throwable $e) {
            Log::error("DEBUG [Overpass Exception]: " . $e->getMessage());
        }

        // ---------------------------------------------------------------------
        // 8. PHOTON KOMOOT GEOCODER (Multi-Keyword Loop)
        // ---------------------------------------------------------------------
        $keywords = ['pharmacy', 'chemist', 'patent medicine', 'drugstore'];
        foreach ($keywords as $kw) {
            try {
                $res = $http()->timeout(8)->get('https://photon.komoot.io/api/', [
                    'q'     => "{$kw} {$city}",
                    'limit' => 50,
                ]);

                if ($res->successful()) {
                    $photonItems = [];
                    foreach ($res->json('features') ?? [] as $f) {
                        $p = $f['properties'] ?? [];
                        $c = $f['geometry']['coordinates'] ?? [null, null];
                        $street = trim(($p['housenumber'] ?? '') . ' ' . ($p['street'] ?? ''));
                        $photonItems[] = [
                            'name'   => $p['name'] ?? null,
                            'street' => $street ?: null,
                            'postal' => $p['postcode'] ?? null,
                            'lat'    => $c[1] ?? null,
                            'lon'    => $c[0] ?? null,
                        ];
                    }
                    $processApiSource("photon_{$kw}", $photonItems);
                }
            } catch (Throwable $e) {
                Log::warning("DEBUG [Photon Error for {$kw}]: " . $e->getMessage());
            }
            usleep(100000);
        }

        // ---------------------------------------------------------------------
        // 9. FINALIZATION & TRIMMING
        // ---------------------------------------------------------------------
        if (count($results) > $maxLimit) {
            $results = array_slice($results, 0, $maxLimit);
        }

        $this->lastStopReason = count($results) > 0 ? 'completed' : 'no_listings_found';
        $this->lastMeta = [
            'total_found' => count($results),
            'city'        => $city,
            'state'       => $state,
            'center'      => [$centerLat, $centerLon],
            'sources'     => array_values(array_unique(array_column($results, 'source'))),
        ];

        Log::info("DEBUG [Final Summary]: Total Unique Results Returned: " . count($results));

        return $results;
    }

    public function isValidBusinessName(string $val): bool
    {
        return true;
    }

    public function isStrictPharmacy(?string $category, ?string $name): bool
    {
        return true;
    }
}