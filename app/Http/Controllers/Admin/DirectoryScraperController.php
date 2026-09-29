<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Laboratory;
use App\Models\Pharmacy;
use App\Services\LabImportService;
use App\Services\PharmacyImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class DirectoryScraperController extends Controller
{
    public function __construct(
        protected PharmacyImportService $pharmacyImporter,
        protected LabImportService $labImporter
    ) {}

    /**
     * Display the Admin Directory Scraper Portal.
     */
    public function index(): View
    {
        $this->authorizeAdmin();

        $pharmacyCount = Pharmacy::count();
        $labCount = Laboratory::count();

        // Recent imports for preview
        $recentPharmacies = Pharmacy::latest()->limit(8)->get();
        $recentLabs = Laboratory::latest()->limit(8)->get();

        // Popular US States list
        $states = $this->getUsStates();

        return view('admin.scraper.index', compact(
            'pharmacyCount',
            'labCount',
            'recentPharmacies',
            'recentLabs',
            'states'
        ));
    }

    /**
     * Execute live scraping for pharmacies, laboratories, or both.
     */
    public function scrape(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        // Allow long execution for scraping
        @set_time_limit(300);

        $validated = $request->validate([
            'type' => 'required|in:pharmacies,laboratories,both',
            'city' => 'required|string|min:2|max:100',
            'state' => 'required|string|min:2|max:50',
            'limit' => 'nullable|integer|min:0|max:1000',
            'dry_run' => 'nullable|boolean',
        ]);

        $type = $validated['type'];
        $city = trim($validated['city']);
        $state = trim($validated['state']);
        $limit = (isset($validated['limit']) && (int)$validated['limit'] > 0) ? (int) $validated['limit'] : null;
        $dryRun = (bool) ($validated['dry_run'] ?? false);

        $results = [
            'success' => true,
            'type' => $type,
            'city' => $city,
            'state' => $state,
            'limit' => $limit,
            'dry_run' => $dryRun,
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'summary' => [
                'found' => 0,
                'inserted' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
            ],
            'pharmacies' => null,
            'laboratories' => null,
            'items' => [],
            'notes' => [],
        ];

        // 1. Scrape Pharmacies if requested
        if ($type === 'pharmacies' || $type === 'both') {
            try {
                $pharmLimit = $type === 'both' && $limit !== null ? (int) ceil($limit / 2) : $limit;
                $pharmStats = $this->pharmacyImporter->import($city, $state, $pharmLimit, $dryRun);

                $results['pharmacies'] = $pharmStats;
                $results['summary']['found'] += $pharmStats['found'];
                $results['summary']['inserted'] += $pharmStats['inserted'];
                $results['summary']['updated'] += $pharmStats['updated'];
                $results['summary']['skipped'] += $pharmStats['skipped'];
                $results['summary']['failed'] += $pharmStats['failed'];

                if (!empty($pharmStats['stop_reason'])) {
                    $versionPrefix = !empty($pharmStats['scraper_version']) ? "[{$pharmStats['scraper_version']}] " : '';
                    $results['notes'][] = "Pharmacy Scraper: {$versionPrefix}" . $this->formatStopReason($pharmStats['stop_reason']);
                }

                foreach ($pharmStats['details'] ?? [] as $item) {
                    $results['items'][] = [
                        'type' => 'Pharmacy',
                        'name' => $item['name'] ?? ($item['raw']['name'] ?? 'Unknown'),
                        'action' => $item['action'] ?? ($item['status'] ?? 'unknown'),
                        'reason' => $item['reason'] ?? null,
                        'address' => $item['pharmacy']->street_address ?? ($item['raw']['street_address'] ?? ($item['raw']['address'] ?? null)),
                        'city' => $item['pharmacy']->city ?? $city,
                        'state' => $item['pharmacy']->state ?? $state,
                        'phone' => $item['pharmacy']->phone ?? ($item['raw']['phone'] ?? null),
                    ];
                }
            } catch (Throwable $e) {
                $results['notes'][] = "Pharmacy Scraper error: " . $e->getMessage();
                $results['summary']['failed']++;
            }
        }

        // 2. Scrape Laboratories if requested
        if ($type === 'laboratories' || $type === 'both') {
            try {
                $labLimit = $type === 'both' && $limit !== null ? (int) ceil($limit / 2) : $limit;
                $labStats = $this->labImporter->import($city, $state, $labLimit, $dryRun);

                $results['laboratories'] = $labStats;
                $results['summary']['found'] += $labStats['found'];
                $results['summary']['inserted'] += $labStats['inserted'];
                $results['summary']['updated'] += $labStats['updated'];
                $results['summary']['skipped'] += $labStats['skipped'];
                $results['summary']['failed'] += $labStats['failed'];

                if (!empty($labStats['stop_reason'])) {
                    $versionPrefix = !empty($labStats['scraper_version']) ? "[{$labStats['scraper_version']}] " : '';
                    $results['notes'][] = "Laboratory Scraper: {$versionPrefix}" . $this->formatStopReason($labStats['stop_reason']);
                }

                foreach ($labStats['details'] ?? [] as $item) {
                    $results['items'][] = [
                        'type' => 'Laboratory',
                        'name' => $item['name'] ?? ($item['raw']['name'] ?? 'Unknown'),
                        'action' => $item['action'] ?? ($item['status'] ?? 'unknown'),
                        'reason' => $item['reason'] ?? null,
                        'address' => $item['laboratory']->street_address ?? ($item['raw']['street_address'] ?? ($item['raw']['address'] ?? null)),
                        'city' => $item['laboratory']->city ?? $city,
                        'state' => $item['laboratory']->state ?? $state,
                        'phone' => $item['laboratory']->phone ?? ($item['raw']['phone'] ?? null),
                    ];
                }
            } catch (Throwable $e) {
                $results['notes'][] = "Laboratory Scraper error: " . $e->getMessage();
                $results['summary']['failed']++;
            }
        }

        $results['scraper_version'] = ($type === 'laboratories')
            ? ($results['laboratories']['scraper_version'] ?? null)
            : ($results['pharmacies']['scraper_version'] ?? ($results['laboratories']['scraper_version'] ?? null));

        // Fresh totals
        $results['current_totals'] = [
            'pharmacies' => Pharmacy::count(),
            'laboratories' => Laboratory::count(),
        ];

        return response()->json($results);
    }

    /**
     * Get paginated directory records for viewing.
     */
    public function data(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $type = $request->input('type', 'pharmacies');
        $search = trim((string) $request->input('q', ''));
        $state = trim((string) $request->input('state', ''));

        if ($type === 'laboratories') {
            $query = Laboratory::query();
        } else {
            $query = Pharmacy::query();
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('city', 'LIKE', "%{$search}%")
                    ->orWhere('street_address', 'LIKE', "%{$search}%");
            });
        }

        if ($state !== '') {
            $query->where('state', strtoupper($state));
        }

        $records = $query->latest()->paginate(15);

        return response()->json($records);
    }

    /**
     * Live worldwide location suggestions using Google Maps / Places API.
     */
    public function locationSuggest(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $query = trim((string) $request->input('q', ''));
        if (mb_strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $apiKey = config('services.google.maps_api_key') 
            ?? (env('GOOGLE_MAPS_API_KEY') ?? env('GOOGLE_PLACES_API_KEY'));

        // 1. If Google Maps API Key is configured, use official Google Places Autocomplete API
        if (!empty($apiKey)) {
            try {
                $googleUrl = "https://maps.googleapis.com/maps/api/place/autocomplete/json?" . http_build_query([
                    'input' => $query,
                    'types' => '(regions)',
                    'key' => $apiKey,
                ]);

                $res = \Illuminate\Support\Facades\Http::timeout(6)->get($googleUrl);
                if ($res->successful()) {
                    $predictions = $res->json('predictions') ?? [];
                    $suggestions = [];

                    foreach ($predictions as $p) {
                        $desc = $p['description'] ?? '';
                        $terms = $p['terms'] ?? [];

                        $city = $terms[0]['value'] ?? ($p['structured_formatting']['main_text'] ?? $desc);
                        $state = isset($terms[1]) ? $terms[1]['value'] : '';
                        $country = count($terms) > 2 ? $terms[count($terms) - 1]['value'] : ($terms[1]['value'] ?? '');

                        $suggestions[] = [
                            'id' => $desc,
                            'text' => $desc,
                            'city' => $city,
                            'state' => $state,
                            'country' => $country,
                        ];
                    }

                    if (!empty($suggestions)) {
                        return response()->json(['results' => $suggestions]);
                    }
                }
            } catch (Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Google Places API error: " . $e->getMessage());
            }
        }

        // 2. High-precision Google Maps global geocoding suggestion engine
        try {
            $nomUrl = "https://nominatim.openstreetmap.org/search?" . http_build_query([
                'q' => $query,
                'format' => 'json',
                'addressdetails' => 1,
                'limit' => 10,
                'accept-language' => 'en',
            ]);

            $nomRes = \Illuminate\Support\Facades\Http::timeout(6)
                ->withHeaders(['User-Agent' => 'PDMS-Healthcare-System/1.0 (healthcare@pdms.org)'])
                ->get($nomUrl);

            if ($nomRes->successful()) {
                $items = $nomRes->json() ?? [];
                $suggestions = [];

                foreach ($items as $item) {
                    $addr = $item['address'] ?? [];
                    $city = $addr['city'] ?? ($addr['town'] ?? ($addr['municipality'] ?? ($addr['village'] ?? ($addr['county'] ?? ($item['name'] ?? null)))));
                    $state = $addr['state'] ?? ($addr['region'] ?? ($addr['state_district'] ?? ''));
                    $country = $addr['country'] ?? '';

                    if (!$city) {
                        $displayParts = explode(',', $item['display_name'] ?? '');
                        $city = trim($displayParts[0] ?? '');
                    }

                    if (!$city) continue;

                    $labelParts = array_unique(array_filter([$city, $state, $country]));
                    $label = implode(', ', $labelParts);

                    $suggestions[] = [
                        'id' => $label,
                        'text' => $label,
                        'city' => $city,
                        'state' => $state ?: ($country ?: ''),
                        'country' => $country,
                    ];
                }

                if (!empty($suggestions)) {
                    return response()->json(['results' => $suggestions]);
                }
            }
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Geocoding suggest error: " . $e->getMessage());
        }

        // 3. Fallback to Photon English geocoding
        try {
            $photonUrl = "https://photon.komoot.io/api/?q=" . urlencode($query) . "&lang=en&limit=10";
            $pRes = \Illuminate\Support\Facades\Http::timeout(5)->get($photonUrl);
            if ($pRes->successful()) {
                $features = $pRes->json('features') ?? [];
                $suggestions = [];

                foreach ($features as $f) {
                    $props = $f['properties'] ?? [];
                    $city = $props['city'] ?? ($props['name'] ?? null);
                    $state = $props['state'] ?? ($props['county'] ?? '');
                    $country = $props['country'] ?? '';

                    if (!$city) continue;

                    $labelParts = array_unique(array_filter([$city, $state, $country]));
                    $label = implode(', ', $labelParts);

                    $suggestions[] = [
                        'id' => $label,
                        'text' => $label,
                        'city' => $city,
                        'state' => $state,
                        'country' => $country,
                    ];
                }

                return response()->json(['results' => $suggestions]);
            }
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Photon suggest error: " . $e->getMessage());
        }

        return response()->json(['results' => []]);
    }

    /**
     * Ensure current user is an authenticated Admin.
     */
    protected function authorizeAdmin(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'admin') {
            abort(403, 'Unauthorized access.');
        }
    }

    /**
     * Format stop reason string.
     */
    protected function formatStopReason(string $reason): string
    {
        return match ($reason) {
            'requested_limit_reached' => 'Requested target limit reached.',
            'end_of_results_reached' => 'All available listings exhausted for this area.',
            'no_new_results_after_repeated_scrolling' => 'No further listings detected.',
            'safety_guard_reached', 'safety_timeout' => 'Safety limit reached.',
            'google_challenge_blocked', 'blocked' => 'Rate limit protection encountered.',
            default => ucfirst(str_replace('_', ' ', $reason)),
        };
    }

    /**
     * Get list of US States.
     *
     * @return array<string, string>
     */
    protected function getUsStates(): array
    {
        return [
            'AL' => 'Alabama',
            'AK' => 'Alaska',
            'AZ' => 'Arizona',
            'AR' => 'Arkansas',
            'CA' => 'California',
            'CO' => 'Colorado',
            'CT' => 'Connecticut',
            'DE' => 'Delaware',
            'FL' => 'Florida',
            'GA' => 'Georgia',
            'HI' => 'Hawaii',
            'ID' => 'Idaho',
            'IL' => 'Illinois',
            'IN' => 'Indiana',
            'IA' => 'Iowa',
            'KS' => 'Kansas',
            'KY' => 'Kentucky',
            'LA' => 'Louisiana',
            'ME' => 'Maine',
            'MD' => 'Maryland',
            'MA' => 'Massachusetts',
            'MI' => 'Michigan',
            'MN' => 'Minnesota',
            'MS' => 'Mississippi',
            'MO' => 'Missouri',
            'MT' => 'Montana',
            'NE' => 'Nebraska',
            'NV' => 'Nevada',
            'NH' => 'New Hampshire',
            'NJ' => 'New Jersey',
            'NM' => 'New Mexico',
            'NY' => 'New York',
            'NC' => 'North Carolina',
            'ND' => 'North Dakota',
            'OH' => 'Ohio',
            'OK' => 'Oklahoma',
            'OR' => 'Oregon',
            'PA' => 'Pennsylvania',
            'RI' => 'Rhode Island',
            'SC' => 'South Carolina',
            'SD' => 'South Dakota',
            'TN' => 'Tennessee',
            'TX' => 'Texas',
            'UT' => 'Utah',
            'VT' => 'Vermont',
            'VA' => 'Virginia',
            'WA' => 'Washington',
            'WV' => 'West Virginia',
            'WI' => 'Wisconsin',
            'WY' => 'Wyoming',
            'DC' => 'District of Columbia',
        ];
    }
}
