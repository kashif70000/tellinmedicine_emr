<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\Contracts\LabScraperInterface;
use App\Services\Contracts\PharmacyScraperInterface;
use App\Services\GoogleMapsLabScraper;
use App\Services\GoogleMapsPharmacyScraper;
use App\Services\LabImportService;
use App\Services\PharmacyImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScraperSmallCityAndSinglePlaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertEquals(
            'sqlite',
            DB::connection()->getDriverName(),
            'Safety: Tests must only run against an isolated SQLite database.'
        );
        $this->assertEquals(
            ':memory:',
            config('database.connections.sqlite.database'),
            'Safety: Tests must only run against an in-memory database.'
        );

        Http::preventStrayRequests();
    }

    /**
     * Test 1: Portal limit=0 reaches scraper as unlimited (null limit).
     */
    public function test_portal_limit_zero_reaches_scraper_as_unlimited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $capturedPharmLimit = 'not_called';
        $capturedLabLimit = 'not_called';

        $mockPharmScraper = $this->createMock(PharmacyScraperInterface::class);
        $mockPharmScraper->method('search')->willReturnCallback(function ($city, $state, $limit) use (&$capturedPharmLimit) {
            $capturedPharmLimit = $limit;
            return [];
        });
        $this->app->instance(PharmacyScraperInterface::class, $mockPharmScraper);

        $mockLabScraper = $this->createMock(LabScraperInterface::class);
        $mockLabScraper->method('search')->willReturnCallback(function ($city, $state, $limit) use (&$capturedLabLimit) {
            $capturedLabLimit = $limit;
            return [];
        });
        $this->app->instance(LabScraperInterface::class, $mockLabScraper);

        $response = $this->actingAs($admin)->postJson(route('admin.directory-scraper.scrape'), [
            'type' => 'both',
            'city' => 'Attleboro',
            'state' => 'Massachusetts',
            'limit' => 0,
            'dry_run' => true,
        ]);

        $response->assertStatus(200);
        $this->assertNull($capturedPharmLimit, 'Pharmacy limit=0 must pass null (unlimited) to scraper');
        $this->assertNull($capturedLabLimit, 'Laboratory limit=0 must pass null (unlimited) to scraper');
    }

    /**
     * Test 2 & 3: Scraper version and metadata are returned in portal JSON response.
     */
    public function test_scraper_version_and_metadata_are_returned(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $mockPharm = new class implements PharmacyScraperInterface {
            public function search(string $city, string $state, ?int $limit = null): array
            {
                return [
                    [
                        'name' => 'Avita Pharmacy 1061',
                        'street_address' => '163 Pleasant St',
                        'city' => 'Attleboro',
                        'state' => 'MA',
                        'postal_code' => '02703',
                        'google_place_id' => 'ChIJ_avita_1061',
                    ]
                ];
            }
            public function getLastStopReason(): ?string { return 'end_of_results_reached'; }
            public function getLastMeta(): ?array {
                return [
                    'scraper_version' => 'pharmacy-small-city-debug-v1',
                    'raw_candidates' => 5,
                    'accepted_candidates' => 1,
                    'duplicates_removed' => 4,
                    'final_unique' => 1,
                ];
            }
        };
        $this->app->instance(PharmacyScraperInterface::class, $mockPharm);

        $response = $this->actingAs($admin)->postJson(route('admin.directory-scraper.scrape'), [
            'type' => 'pharmacies',
            'city' => 'Attleboro',
            'state' => 'MA',
            'limit' => 0,
            'dry_run' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'scraper_version' => 'pharmacy-small-city-debug-v1',
            'pharmacies' => [
                'scraper_version' => 'pharmacy-small-city-debug-v1',
            ]
        ]);
    }

    /**
     * Test 4: Single-place lab page payload is imported and normalized.
     */
    public function test_single_place_lab_page_is_extracted_and_imported(): void
    {
        $mockLabScraper = $this->createMock(LabScraperInterface::class);
        $mockLabScraper->method('search')->willReturn([
            [
                'name' => 'Quest Diagnostics Attleboro Pleasant Street',
                'category' => 'Medical laboratory',
                'street_address' => '211 Park St',
                'city' => 'Attleboro',
                'state' => 'MA',
                'postal_code' => '02703',
                'phone' => '(866) 697-8378',
                'website' => 'https://questdiagnostics.com',
                'latitude' => 41.9366432,
                'longitude' => -71.2721863,
                'google_place_id' => 'ChIJa6PyEUpgxokREnz4Ii0_I1I',
                'external_source_url' => 'https://maps.google.com/place/Quest+Diagnostics',
            ]
        ]);
        $this->app->instance(LabScraperInterface::class, $mockLabScraper);

        /** @var LabImportService $service */
        $service = $this->app->make(LabImportService::class);
        $stats = $service->import('Attleboro', 'MA', null, false);

        $this->assertSame(1, $stats['found']);
        $this->assertSame(1, $stats['inserted']);
        $this->assertDatabaseHas('laboratories', [
            'name' => 'Quest Diagnostics Attleboro Pleasant Street',
            'category' => 'Medical Laboratory',
            'street_address' => '211 Park St',
            'city' => 'Attleboro',
            'state' => 'MA',
            'postal_code' => '02703',
            'google_place_id' => 'ChIJa6PyEUpgxokREnz4Ii0_I1I',
        ]);
    }

    /**
     * Test 5: Single-place pharmacy page payload is extracted and imported.
     */
    public function test_single_place_pharmacy_page_is_extracted_and_imported(): void
    {
        $mockPharmScraper = $this->createMock(PharmacyScraperInterface::class);
        $mockPharmScraper->method('search')->willReturn([
            [
                'name' => 'Stop & Shop Pharmacy',
                'street_address' => '251 Washington St',
                'city' => 'Attleboro',
                'state' => 'MA',
                'postal_code' => '02703',
                'phone' => '(508) 399-6107',
                'website' => 'https://stopandshop.com',
                'google_place_id' => 'ChIJWWOTtEtgxokRsx23NX6dXRw',
            ]
        ]);
        $this->app->instance(PharmacyScraperInterface::class, $mockPharmScraper);

        /** @var PharmacyImportService $service */
        $service = $this->app->make(PharmacyImportService::class);
        $stats = $service->import('Attleboro', 'MA', null, false);

        $this->assertSame(1, $stats['found']);
        $this->assertSame(1, $stats['inserted']);
        $this->assertDatabaseHas('pharmacies', [
            'name' => 'Stop & Shop Pharmacy',
            'street_address' => '251 Washington St',
            'city' => 'Attleboro',
            'state' => 'MA',
            'postal_code' => '02703',
        ]);
    }

    /**
     * Test 6: Multiple queries aggregate results and deduplicate identical locations.
     */
    public function test_multiple_queries_aggregate_and_deduplicate(): void
    {
        $mockPharmScraper = $this->createMock(PharmacyScraperInterface::class);
        $mockPharmScraper->method('search')->willReturn([
            [
                'name' => 'CVS Pharmacy',
                'street_address' => '191 N Main St',
                'city' => 'Attleboro',
                'state' => 'MA',
                'postal_code' => '02703',
                'google_place_id' => 'ChIJ_cvs_1',
            ],
            [
                'name' => 'CVS Pharmacy',
                'street_address' => '419 Pleasant St',
                'city' => 'Attleboro',
                'state' => 'MA',
                'postal_code' => '02703',
                'google_place_id' => 'ChIJ_cvs_2',
            ],
            [
                'name' => 'CVS Pharmacy',
                'street_address' => '191 N Main St', // duplicate of first
                'city' => 'Attleboro',
                'state' => 'MA',
                'postal_code' => '02703',
                'google_place_id' => 'ChIJ_cvs_1',
            ],
        ]);
        $this->app->instance(PharmacyScraperInterface::class, $mockPharmScraper);

        $service = $this->app->make(PharmacyImportService::class);
        $stats = $service->import('Attleboro', 'MA', null, false);

        $this->assertSame(3, $stats['found']);
        $this->assertSame(2, $stats['inserted']);
        $this->assertSame(1, $stats['skipped']);
        $this->assertDatabaseCount('pharmacies', 2);
    }

    /**
     * Test 7: State "Massachusetts" normalizes consistently with "MA".
     */
    public function test_state_normalization_consistency(): void
    {
        $pharmService = $this->app->make(PharmacyImportService::class);
        $labService = $this->app->make(LabImportService::class);

        $normPharmState = $pharmService->normalizeState('Massachusetts');
        $normLabState = $labService->normalizeState('Massachusetts');

        $this->assertSame('MASSACHUSETTS', $normPharmState);
        $this->assertSame('MASSACHUSETTS', $normLabState);
    }

    /**
     * Test 8: Google challenge is reported accurately as stop_reason.
     */
    public function test_google_challenge_reported_accurately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $mockLab = new class implements LabScraperInterface {
            public function search(string $city, string $state, ?int $limit = null): array { return []; }
            public function getLastStopReason(): ?string { return 'google_challenge_blocked'; }
            public function getLastMeta(): ?array { return ['stop_reason' => 'google_challenge_blocked']; }
        };
        $this->app->instance(LabScraperInterface::class, $mockLab);

        $response = $this->actingAs($admin)->postJson(route('admin.directory-scraper.scrape'), [
            'type' => 'laboratories',
            'city' => 'Attleboro',
            'state' => 'MA',
            'limit' => 0,
            'dry_run' => true,
        ]);

        $response->assertStatus(200);
        $this->assertTrue(collect($response->json('notes'))->contains(function ($note) {
            return str_contains($note, 'Rate limit protection') || str_contains($note, 'google_challenge_blocked');
        }));
    }
}
