<?php

namespace Tests\Feature;

use App\Models\Pharmacy;
use App\Services\Contracts\PharmacyScraperInterface;
use App\Services\PharmacyImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PharmacyImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Critical Database Safety Assertion:
        // Ensure tests NEVER run against MySQL or a non-isolated database
        $this->assertEquals(
            'sqlite',
            DB::connection()->getDriverName(),
            'CRITICAL SAFETY ERROR: Tests must only run against an isolated SQLite database.'
        );
        $this->assertEquals(
            ':memory:',
            config('database.connections.sqlite.database'),
            'CRITICAL SAFETY ERROR: Tests must only run against an in-memory database.'
        );

        // Prevent all stray external HTTP calls to guarantee no live requests to Google Maps
        Http::preventStrayRequests();
    }

    /**
     * Test 1: Pharmacy record can be created.
     */
    public function test_pharmacy_record_can_be_created(): void
    {
        $pharmacy = Pharmacy::create([
            'name' => 'Boston Community Pharmacy',
            'street_address' => '400 Tremont St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02116',
            'phone' => '(617) 555-0144',
            'website' => 'https://bostoncommunitypharmacy.com',
            'latitude' => 42.3456780,
            'longitude' => -71.0678900,
            'google_place_id' => 'ChIJ_test_place_id_123',
            'source' => 'google_maps',
            'external_source_url' => 'https://maps.google.com/?cid=12345',
        ]);

        $this->assertDatabaseHas('pharmacies', [
            'id' => $pharmacy->id,
            'name' => 'Boston Community Pharmacy',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02116',
            'google_place_id' => 'ChIJ_test_place_id_123',
        ]);

        $this->assertSame(42.345678, $pharmacy->latitude);
        $this->assertSame(-71.06789, $pharmacy->longitude);
    }

    /**
     * Test 2: Import normalization works.
     */
    public function test_import_normalization_works(): void
    {
        /** @var PharmacyImportService $service */
        $service = $this->app->make(PharmacyImportService::class);

        $raw = [
            'name' => '   Beacon   Hill   Pharmacy   ',
            'address' => '  123   Charles   St  ',
            'city' => 'boston',
            'state' => 'ma',
            'postal_code' => '02114',
            'phone' => '6175550188',
            'website' => 'beaconhillpharmacy.com',
            'latitude' => '42.3588',
            'longitude' => '-71.0707',
            'google_place_id' => '  place_123  ',
            'source' => '   ',
        ];

        $normalized = $service->normalizeRecord($raw, 'Boston', 'MA');

        $this->assertSame('Beacon Hill Pharmacy', $normalized['name']);
        $this->assertSame('123 Charles St', $normalized['street_address']);
        $this->assertSame('Boston', $normalized['city']);
        $this->assertSame('MA', $normalized['state']);
        $this->assertSame('02114', $normalized['postal_code']); // Keeps leading zero
        $this->assertSame('(617) 555-0188', $normalized['phone']);
        $this->assertSame('https://beaconhillpharmacy.com', $normalized['website']);
        $this->assertSame(42.3588, $normalized['latitude']);
        $this->assertSame(-71.0707, $normalized['longitude']);
        $this->assertSame('place_123', $normalized['google_place_id']);
        $this->assertSame('google_maps', $normalized['source']); // Converted blank to default
    }

    /**
     * Test 3: Same pharmacy imported twice does not create duplicates.
     */
    public function test_same_pharmacy_imported_twice_does_not_create_duplicates(): void
    {
        $mockData = [
            [
                'name' => 'Metro Care Pharmacy',
                'street_address' => '500 Commonwealth Ave',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02215',
                'phone' => '(617) 555-0199',
                'website' => 'https://metrocarepharmacy.com',
                'google_place_id' => 'ChIJ_metro_care_01',
            ],
        ];

        $mockScraper = $this->createMock(PharmacyScraperInterface::class);
        $mockScraper->expects($this->exactly(2))
            ->method('search')
            ->willReturn($mockData);

        $this->app->instance(PharmacyScraperInterface::class, $mockScraper);

        /** @var PharmacyImportService $service */
        $service = $this->app->make(PharmacyImportService::class);

        // First import run
        $firstResult = $service->import('Boston', 'MA');
        $this->assertSame(1, $firstResult['inserted']);
        $this->assertSame(0, $firstResult['updated']);
        $this->assertSame(0, $firstResult['skipped']);
        $this->assertDatabaseCount('pharmacies', 1);

        // Second import run with identical data
        $secondResult = $service->import('Boston', 'MA');
        $this->assertSame(0, $secondResult['inserted']);
        $this->assertSame(0, $secondResult['updated']);
        $this->assertSame(1, $secondResult['skipped']);
        $this->assertDatabaseCount('pharmacies', 1);
    }

    /**
     * Test 4: Existing pharmacy is updated when new useful information appears.
     */
    public function test_existing_pharmacy_is_updated_when_new_useful_information_appears(): void
    {
        // Pre-existing pharmacy record with missing phone and website
        $pharmacy = Pharmacy::create([
            'name' => 'Back Bay Pharmacy',
            'street_address' => '200 Newbury St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02116',
            'phone' => null,
            'website' => null,
            'google_place_id' => 'ChIJ_back_bay_02',
        ]);

        $mockData = [
            [
                'name' => 'Back Bay Pharmacy',
                'street_address' => '200 Newbury St',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02116',
                'phone' => '617-555-9988',
                'website' => 'https://backbayrx.com',
                'latitude' => 42.3501,
                'longitude' => -71.0801,
                'google_place_id' => 'ChIJ_back_bay_02',
            ],
        ];

        $mockScraper = $this->createMock(PharmacyScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(PharmacyScraperInterface::class, $mockScraper);

        /** @var PharmacyImportService $service */
        $service = $this->app->make(PharmacyImportService::class);
        $result = $service->import('Boston', 'MA');

        $this->assertSame(0, $result['inserted']);
        $this->assertSame(1, $result['updated']);
        $this->assertDatabaseCount('pharmacies', 1);

        $pharmacy->refresh();
        $this->assertSame('(617) 555-9988', $pharmacy->phone);
        $this->assertSame('https://backbayrx.com', $pharmacy->website);
        $this->assertSame(42.3501, $pharmacy->latitude);
        $this->assertSame(-71.0801, $pharmacy->longitude);
    }

    /**
     * Test 5: Existing non-empty data is not overwritten by null/empty imported data.
     */
    public function test_existing_non_empty_data_is_not_overwritten_by_null_or_empty_imported_data(): void
    {
        $pharmacy = Pharmacy::create([
            'name' => 'North End Apothecary',
            'street_address' => '150 Hanover St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02113',
            'phone' => '(617) 555-3344',
            'website' => 'https://northendapothecary.com',
            'latitude' => 42.3630,
            'longitude' => -71.0560,
            'google_place_id' => 'ChIJ_north_end_03',
        ]);

        // Incoming scraped record has missing (null/empty) fields
        $mockData = [
            [
                'name' => 'North End Apothecary',
                'street_address' => '150 Hanover St',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => null, // empty
                'phone' => '', // empty string
                'website' => null, // null
                'latitude' => null,
                'longitude' => null,
                'google_place_id' => 'ChIJ_north_end_03',
            ],
        ];

        $mockScraper = $this->createMock(PharmacyScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(PharmacyScraperInterface::class, $mockScraper);

        /** @var PharmacyImportService $service */
        $service = $this->app->make(PharmacyImportService::class);
        $result = $service->import('Boston', 'MA');

        $this->assertSame(0, $result['inserted']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['skipped']);

        // Verify existing values remain completely intact
        $pharmacy->refresh();
        $this->assertSame('(617) 555-3344', $pharmacy->phone);
        $this->assertSame('https://northendapothecary.com', $pharmacy->website);
        $this->assertSame('02113', $pharmacy->postal_code);
        $this->assertSame(42.363, $pharmacy->latitude);
        $this->assertSame(-71.056, $pharmacy->longitude);
    }

    /**
     * Test 6: Different pharmacies with similar names are not incorrectly merged.
     */
    public function test_different_pharmacies_with_similar_names_are_not_incorrectly_merged(): void
    {
        // Two distinct locations of a pharmacy chain in Boston
        $mockData = [
            [
                'name' => 'HealthFirst Pharmacy',
                'street_address' => '100 Tremont St',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02108',
                'phone' => '(617) 555-1111',
            ],
            [
                'name' => 'HealthFirst Pharmacy',
                'street_address' => '800 Washington St',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02111',
                'phone' => '(617) 555-2222',
            ],
        ];

        $mockScraper = $this->createMock(PharmacyScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(PharmacyScraperInterface::class, $mockScraper);

        /** @var PharmacyImportService $service */
        $service = $this->app->make(PharmacyImportService::class);
        $result = $service->import('Boston', 'MA');

        $this->assertSame(2, $result['inserted']);
        $this->assertDatabaseCount('pharmacies', 2);

        $this->assertDatabaseHas('pharmacies', [
            'name' => 'HealthFirst Pharmacy',
            'street_address' => '100 Tremont St',
            'postal_code' => '02108',
        ]);

        $this->assertDatabaseHas('pharmacies', [
            'name' => 'HealthFirst Pharmacy',
            'street_address' => '800 Washington St',
            'postal_code' => '02111',
        ]);
    }

    /**
     * Test 7: dry-run performs no database writes.
     */
    public function test_dry_run_performs_no_database_writes(): void
    {
        $mockData = [
            [
                'name' => 'South End Pharmacy',
                'street_address' => '650 Columbus Ave',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02118',
                'phone' => '(617) 555-7788',
            ],
        ];

        $mockScraper = $this->createMock(PharmacyScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(PharmacyScraperInterface::class, $mockScraper);

        /** @var PharmacyImportService $service */
        $service = $this->app->make(PharmacyImportService::class);
        $result = $service->import('Boston', 'MA', null, true); // dryRun = true

        $this->assertSame(1, $result['found']);
        $this->assertSame(1, $result['inserted']); // Projected insert
        $this->assertDatabaseCount('pharmacies', 0); // ZERO rows written to DB
    }

    /**
     * Test 8: malformed individual results are skipped safely.
     */
    public function test_malformed_individual_results_are_skipped_safely(): void
    {
        $mockData = [
            [
                'name' => '', // Malformed: empty name
                'street_address' => '100 Main St',
                'city' => 'Boston',
                'state' => 'MA',
            ],
            [
                'name' => '   ', // Malformed: whitespace-only name
                'street_address' => '200 Main St',
            ],
            [
                'name' => 'Valid Downtown Pharmacy',
                'street_address' => '300 Washington St',
                'city' => 'Boston',
                'state' => 'MA',
                'phone' => '6175559090',
            ],
        ];

        $mockScraper = $this->createMock(PharmacyScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(PharmacyScraperInterface::class, $mockScraper);

        /** @var PharmacyImportService $service */
        $service = $this->app->make(PharmacyImportService::class);
        $result = $service->import('Boston', 'MA');

        $this->assertSame(3, $result['found']);
        $this->assertSame(1, $result['inserted']);
        $this->assertSame(2, $result['skipped']);
        $this->assertSame(0, $result['failed']);
        $this->assertDatabaseCount('pharmacies', 1);
        $this->assertDatabaseHas('pharmacies', ['name' => 'Valid Downtown Pharmacy']);
    }

    /**
     * Test 9: command returns a useful import summary.
     */
    public function test_command_returns_useful_import_summary(): void
    {
        $mockData = [
            [
                'name' => 'Fenway Community Pharmacy',
                'street_address' => '1340 Boylston St',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02215',
                'phone' => '(617) 555-4433',
            ],
        ];

        $mockScraper = $this->createMock(PharmacyScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(PharmacyScraperInterface::class, $mockScraper);

        $this->artisan('pharmacies:import', [
            'city' => 'Boston',
            'state' => 'MA',
        ])
            ->expectsOutputToContain('Pharmacy Directory Importer')
            ->expectsOutputToContain('Target Location: Boston, MA')
            ->expectsOutputToContain('City:     Boston, MA')
            ->expectsOutputToContain('Found:    1')
            ->expectsOutputToContain('Inserted: 1')
            ->expectsOutputToContain('Updated:  0')
            ->expectsOutputToContain('Skipped:  0')
            ->expectsOutputToContain('Failed:   0')
            ->expectsOutputToContain('Import completed successfully.')
            ->assertExitCode(0);
    }

    /**
     * Test 10: scraper/fetch layer can be mocked so tests do not contact Google Maps.
     */
    public function test_scraper_layer_can_be_mocked_without_live_google_maps_requests(): void
    {
        // Ensure no external HTTP requests can occur
        Http::preventStrayRequests();

        $mockScraper = $this->createMock(PharmacyScraperInterface::class);
        $mockScraper->expects($this->once())
            ->method('search')
            ->with('Cambridge', 'MA', 10)
            ->willReturn([
                [
                    'name' => 'Harvard Square Pharmacy',
                    'street_address' => '22 JFK St',
                    'city' => 'Cambridge',
                    'state' => 'MA',
                    'postal_code' => '02138',
                    'phone' => '(617) 555-8811',
                    'google_place_id' => 'ChIJ_cambridge_01',
                ],
            ]);

        $this->app->instance(PharmacyScraperInterface::class, $mockScraper);

        $this->artisan('pharmacies:import', [
            'city' => 'Cambridge',
            'state' => 'MA',
            '--limit' => 10,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('Found:    1')
            ->expectsOutputToContain('Dry run complete. No records were written to the database.')
            ->assertExitCode(0);

        // No database records should exist because of dry-run
        $this->assertDatabaseCount('pharmacies', 0);
    }

    /**
     * Test 11: Google tracking token cannot become pharmacy name.
     */
    public function test_google_tracking_token_cannot_become_pharmacy_name(): void
    {
        $mockData = [
            [
                'name' => '0ahUKEwiy5uSltdyWAxXSkeEIHXd5OxkQjqADCAUoAg',
                'street_address' => null,
                'city' => 'Boston',
                'state' => 'MA',
                'google_place_id' => '0x89e3652d0d3d311b:0x787cbf240162e8a0',
            ],
        ];

        $mockScraper = $this->createMock(PharmacyScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(PharmacyScraperInterface::class, $mockScraper);

        /** @var PharmacyImportService $service */
        $service = $this->app->make(PharmacyImportService::class);
        $result = $service->import('Boston', 'MA');

        $this->assertSame(1, $result['found']);
        $this->assertSame(0, $result['inserted']);
        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseCount('pharmacies', 0);
    }

    /**
     * Test 12: Legitimate name such as CVS Pharmacy is accepted.
     */
    public function test_legitimate_name_cvs_pharmacy_is_accepted(): void
    {
        $mockData = [
            [
                'name' => 'CVS Pharmacy',
                'street_address' => '285 Columbus Ave',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02116',
                'phone' => '(617) 236-8538',
            ],
        ];

        $mockScraper = $this->createMock(PharmacyScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(PharmacyScraperInterface::class, $mockScraper);

        /** @var PharmacyImportService $service */
        $service = $this->app->make(PharmacyImportService::class);
        $result = $service->import('Boston', 'MA');

        $this->assertSame(1, $result['inserted']);
        $this->assertDatabaseHas('pharmacies', [
            'name' => 'CVS Pharmacy',
            'street_address' => '285 Columbus Ave',
            'postal_code' => '02116',
        ]);
    }

    /**
     * Test 13: Legitimate chain such as Walgreens is accepted even without the word 'pharmacy'.
     */
    public function test_legitimate_chain_walgreens_accepted_without_pharmacy_in_name(): void
    {
        $mockData = [
            [
                'name' => 'Walgreens',
                'street_address' => '841 Boylston St',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02116',
                'phone' => '(617) 236-1692',
            ],
        ];

        $mockScraper = $this->createMock(PharmacyScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(PharmacyScraperInterface::class, $mockScraper);

        /** @var PharmacyImportService $service */
        $service = $this->app->make(PharmacyImportService::class);
        $result = $service->import('Boston', 'MA');

        $this->assertSame(1, $result['inserted']);
        $this->assertDatabaseHas('pharmacies', [
            'name' => 'Walgreens',
            'street_address' => '841 Boylston St',
        ]);
    }

    /**
     * Test 14: Google UI strings cannot become business names.
     */
    public function test_google_ui_strings_cannot_become_business_names(): void
    {
        $scraper = new \App\Services\GoogleMapsPharmacyScraper();

        $forbidden = ['Directions', 'Website', 'Call', 'Share', 'Save', 'Results', 'Sponsored', 'Book online'];
        foreach ($forbidden as $label) {
            $this->assertFalse(
                $scraper->isValidBusinessName($label, 'Boston', 'MA'),
                "Expected UI string '{$label}' to be rejected as a business name."
            );
        }

        $this->assertFalse($scraper->isValidBusinessName('0ahUKEwiy5uSltdyWAxXSkeEIHXd5OxkQjqADCAUoAg'));
        $this->assertFalse($scraper->isValidBusinessName('Boston, MA, USA', 'Boston', 'MA'));
        $this->assertFalse($scraper->isValidBusinessName('Boston', 'Boston', 'MA'));
        $this->assertTrue($scraper->isValidBusinessName('Capsule', 'Boston', 'MA'));
        $this->assertTrue($scraper->isValidBusinessName('CVS Pharmacy', 'Boston', 'MA'));
        $this->assertTrue($scraper->isValidBusinessName('Walgreens', 'Boston', 'MA'));
    }

    /**
     * Test 15: Block or challenge response does not result in a fake successful import.
     */
    public function test_block_or_challenge_response_does_not_result_in_fake_success(): void
    {
        $mockScraper = $this->createMock(PharmacyScraperInterface::class);
        $mockScraper->method('search')->willReturn([]); // Empty array simulating challenge / blocked scraper
        $this->app->instance(PharmacyScraperInterface::class, $mockScraper);

        $this->artisan('pharmacies:import', [
            'city' => 'Boston',
            'state' => 'MA',
        ])
            ->expectsOutputToContain('Found:    0')
            ->expectsOutputToContain('Inserted: 0')
            ->expectsOutputToContain('No pharmacy listings were found or imported for Boston, MA.')
            ->assertExitCode(0);
    }

    /**
     * Test 16: Postal code extraction for standard 5-digit format preserves leading zero as string.
     */
    public function test_postal_code_extraction_standard_5_digit_preserves_leading_zero(): void
    {
        /** @var PharmacyImportService $service */
        $service = $this->app->make(PharmacyImportService::class);

        $raw = [
            'name' => 'CVS Pharmacy Specialty Services',
            'street_address' => '35 Kneeland St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02111',
        ];

        $normalized = $service->normalizeRecord($raw, 'Boston', 'MA');

        $this->assertSame('02111', $normalized['postal_code']);
        $this->assertIsString($normalized['postal_code']);
        $this->assertNotSame(2111, $normalized['postal_code'], 'Postal code must never be cast to integer');

        $this->assertSame('02111', $service->normalizePostalCode('02111'));
        $this->assertIsString($service->normalizePostalCode('02111'));
    }

    /**
     * Test 17: Postal code extraction supports ZIP+4 format.
     */
    public function test_postal_code_extraction_supports_zip_plus_four_format(): void
    {
        /** @var PharmacyImportService $service */
        $service = $this->app->make(PharmacyImportService::class);

        $raw = [
            'name' => 'Kneeland Pharmacy',
            'street_address' => '123 Main St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02111-1234',
        ];

        $normalized = $service->normalizeRecord($raw, 'Boston', 'MA');

        $this->assertSame('02111-1234', $normalized['postal_code']);
        $this->assertIsString($normalized['postal_code']);

        $this->assertSame('02111-1234', $service->normalizePostalCode('02111-1234'));
        $this->assertSame('02111-1234', $service->normalizePostalCode('02111 1234'));
    }

    /**
     * Test 18: Missing postal code returns null without inventing or inferring a ZIP.
     */
    public function test_missing_postal_code_returns_null(): void
    {
        /** @var PharmacyImportService $service */
        $service = $this->app->make(PharmacyImportService::class);

        $raw = [
            'name' => 'Gary Drug Co.',
            'street_address' => '59 Charles St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => null,
        ];

        $normalized = $service->normalizeRecord($raw, 'Boston', 'MA');

        $this->assertNull($normalized['postal_code']);
        $this->assertNull($service->normalizePostalCode(null));
        $this->assertNull($service->normalizePostalCode(''));
        $this->assertNull($service->normalizePostalCode('   '));
    }

    /**
     * Test 19: Malformed address or invalid postal code does not generate a fake ZIP.
     */
    public function test_malformed_postal_code_does_not_generate_fake_zip(): void
    {
        /** @var PharmacyImportService $service */
        $service = $this->app->make(PharmacyImportService::class);

        $this->assertNull($service->normalizePostalCode('invalid-zip'));
        $this->assertNull($service->normalizePostalCode('Boston, MA'));
        $this->assertNull($service->normalizePostalCode('123')); // Under 5 digits
        $this->assertNull($service->normalizePostalCode('123456')); // 6 digits

        $raw = [
            'name' => 'Sample Pharmacy',
            'street_address' => '500 Main St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => 'ABCDE',
        ];

        $normalized = $service->normalizeRecord($raw, 'Boston', 'MA');
        $this->assertNull($normalized['postal_code']);
    }

    /**
     * Test 20: No --limit passes null/unlimited through Artisan command and service layer.
     */
    public function test_no_limit_passes_null_through_all_layers(): void
    {
        $mockScraper = $this->createMock(PharmacyScraperInterface::class);
        $mockScraper->expects($this->once())
            ->method('search')
            ->with('Boston', 'MA', null) // Must be exactly null, NOT 20
            ->willReturn([]);

        $this->app->instance(PharmacyScraperInterface::class, $mockScraper);

        $this->artisan('pharmacies:import', [
            'city' => 'Boston',
            'state' => 'MA',
        ])
            ->expectsOutputToContain('Limit:           No limit (until results exhausted)')
            ->assertExitCode(0);
    }

    /**
     * Test 21: No --limit does not become 20 in the scraper layer.
     */
    public function test_no_limit_does_not_become_twenty(): void
    {
        $mockScraper = $this->createMock(PharmacyScraperInterface::class);
        $mockScraper->expects($this->once())
            ->method('search')
            ->with(
                'Boston',
                'MA',
                $this->callback(function ($limit) {
                    return $limit === null && $limit !== 20;
                })
            )
            ->willReturn([]);

        $this->app->instance(PharmacyScraperInterface::class, $mockScraper);

        /** @var PharmacyImportService $service */
        $service = $this->app->make(PharmacyImportService::class);
        $result = $service->import('Boston', 'MA');

        $this->assertSame(0, $result['found']);
    }

    /**
     * Test 22: --limit=20 stops at 20 unique results and passes 20 to scraper.
     */
    public function test_explicit_limit_twenty_passes_twenty_to_scraper(): void
    {
        $mockScraper = $this->createMock(PharmacyScraperInterface::class);
        $mockScraper->expects($this->once())
            ->method('search')
            ->with('Boston', 'MA', 20)
            ->willReturn([]);

        $this->app->instance(PharmacyScraperInterface::class, $mockScraper);

        $this->artisan('pharmacies:import', [
            'city' => 'Boston',
            'state' => 'MA',
            '--limit' => 20,
        ])
            ->expectsOutputToContain('Limit:           20')
            ->assertExitCode(0);
    }

    /**
     * Test 23: --limit=50 permits more than 20 results.
     */
    public function test_explicit_limit_fifty_permits_more_than_twenty(): void
    {
        // Generate 35 mock pharmacy records
        $mockData = [];
        for ($i = 1; $i <= 35; $i++) {
            $mockData[] = [
                'name' => "Pharmacy #{$i}",
                'street_address' => "{$i} Medical Center Way",
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => sprintf('021%02d', $i % 99),
                'google_place_id' => "ChIJ_mock_place_{$i}",
            ];
        }

        $mockScraper = $this->createMock(PharmacyScraperInterface::class);
        $mockScraper->expects($this->once())
            ->method('search')
            ->with('Boston', 'MA', 50)
            ->willReturn($mockData);

        $this->app->instance(PharmacyScraperInterface::class, $mockScraper);

        $this->artisan('pharmacies:import', [
            'city' => 'Boston',
            'state' => 'MA',
            '--limit' => 50,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Limit:           50')
            ->expectsOutputToContain('Found:    35')
            ->expectsOutputToContain('Inserted: 35')
            ->assertExitCode(0);
    }

    /**
     * Test 24: Duplicate cards do not count toward unique limit.
     */
    public function test_duplicate_cards_do_not_count_toward_limit(): void
    {
        // 4 records but with 2 duplicates (representing same pharmacy found twice during scrolling)
        $mockData = [
            [
                'name' => 'Tufts Community Pharmacy',
                'street_address' => '800 Washington St',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02111',
                'google_place_id' => 'ChIJ_tufts_01',
            ],
            [
                'name' => 'Tufts Community Pharmacy',
                'street_address' => '800 Washington St',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02111',
                'google_place_id' => 'ChIJ_tufts_01',
            ],
            [
                'name' => 'Boston Health Pharmacy',
                'street_address' => '725 Albany St',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02118',
                'google_place_id' => 'ChIJ_bmc_01',
            ],
            [
                'name' => 'Boston Health Pharmacy',
                'street_address' => '725 Albany St',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02118',
                'google_place_id' => 'ChIJ_bmc_01',
            ],
        ];

        $mockScraper = $this->createMock(PharmacyScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(PharmacyScraperInterface::class, $mockScraper);

        /** @var PharmacyImportService $service */
        $service = $this->app->make(PharmacyImportService::class);
        $result = $service->import('Boston', 'MA', 2);

        $this->assertSame(4, $result['found']);
        $this->assertSame(2, $result['inserted']);
        $this->assertSame(2, $result['skipped']);
        $this->assertDatabaseCount('pharmacies', 2);
    }

    /**
     * Test 25: No-limit mode terminates when no new cards appear.
     */
    public function test_no_limit_mode_terminates_when_no_new_cards_appear(): void
    {
        $mockScraper = $this->createMock(\App\Services\GoogleMapsPharmacyScraper::class);
        $mockScraper->method('search')->willReturn([
            [
                'name' => 'Charles River Pharmacy',
                'street_address' => '100 Cambridge St',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02114',
            ]
        ]);
        $mockScraper->method('getLastStopReason')->willReturn('no_new_results_after_repeated_scrolling');
        $this->app->instance(PharmacyScraperInterface::class, $mockScraper);

        $this->artisan('pharmacies:import', [
            'city' => 'Boston',
            'state' => 'MA',
        ])
            ->expectsOutputToContain('Stop Reason: No new results after repeated scrolling')
            ->assertExitCode(0);
    }

    /**
     * Test 26: No-limit mode terminates at Google Maps end of results.
     */
    public function test_no_limit_mode_terminates_at_google_maps_end_of_results(): void
    {
        $mockScraper = $this->createMock(\App\Services\GoogleMapsPharmacyScraper::class);
        $mockScraper->method('search')->willReturn([
            [
                'name' => 'South End Pharmacy',
                'street_address' => '500 Tremont St',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02116',
            ]
        ]);
        $mockScraper->method('getLastStopReason')->willReturn('end_of_results_reached');
        $this->app->instance(PharmacyScraperInterface::class, $mockScraper);

        $this->artisan('pharmacies:import', [
            'city' => 'Boston',
            'state' => 'MA',
        ])
            ->expectsOutputToContain('Stop Reason: End of Google Maps results reached')
            ->assertExitCode(0);
    }

    /**
     * Test 27: Safety guard prevents infinite scrolling.
     */
    public function test_safety_guard_prevents_infinite_scrolling(): void
    {
        $mockScraper = $this->createMock(\App\Services\GoogleMapsPharmacyScraper::class);
        $mockScraper->method('search')->willReturn([]);
        $mockScraper->method('getLastStopReason')->willReturn('safety_guard_reached');
        $this->app->instance(PharmacyScraperInterface::class, $mockScraper);

        $this->artisan('pharmacies:import', [
            'city' => 'Boston',
            'state' => 'MA',
        ])
            ->expectsOutputToContain('Stop Reason: Safety scroll guard / timeout reached')
            ->assertExitCode(0);
    }

    /**
     * Test 28: Strict pharmacy filter accepts legitimate pharmacies and rejects non-pharmacies.
     */
    public function test_strict_pharmacy_filter_accepts_legitimate_pharmacies_and_rejects_non_pharmacies(): void
    {
        $validPharmacies = [
            'CVS Pharmacy',
            'CVS',
            'Walgreens Pharmacy',
            'Walgreens',
            'Stop & Shop Pharmacy',
            'Walmart Pharmacy',
            'Shaw\'s Pharmacy',
            'County Square Pharmacy',
            'Avita Pharmacy 1061',
            'North Attleboro Pharmacy',
            'Beacon Hill Compounding Pharmacy',
            'Fazal Din Pharma Plus',
        ];

        foreach ($validPharmacies as $name) {
            $this->assertTrue(
                PharmacyImportService::isStrictPharmacy(null, $name),
                "Expected {$name} to be accepted as a strict pharmacy"
            );
        }

        $invalidBusinesses = [
            'The UPS Store',
            'Extra Space Storage',
            'CVS Photo',
            'Dollar General',
            'Ulta Beauty',
            'MinuteClinic at CVS',
            'Cumberland Farms',
            'Native Sun Dispensary North Attleboro',
            'Zahara Cannabis Dispensary - Attleboro',
            'COVID-19 Drive-Thru Testing at Walgreens',
            'Pick N Pay',
        ];

        foreach ($invalidBusinesses as $name) {
            $this->assertFalse(
                PharmacyImportService::isStrictPharmacy(null, $name),
                "Expected {$name} to be rejected by strict pharmacy filter"
            );
        }
    }

    /**
     * Test 29: Same-address chain variants are deduplicated and upgraded to pharmacy name.
     */
    public function test_same_address_chain_variants_are_deduplicated_and_upgraded_to_pharmacy_name(): void
    {
        /** @var PharmacyImportService $service */
        $service = $this->app->make(PharmacyImportService::class);

        // 1. First record: "CVS" at 8 E Washington St
        $res1 = $service->processRecord([
            'name' => 'CVS',
            'street_address' => '8 E Washington St',
            'city' => 'North Attleborough',
            'state' => 'MA',
            'postal_code' => '02760',
            'phone' => '(508) 695-1481',
            'source' => 'google_maps',
        ]);

        $this->assertSame('inserted', $res1['action']);
        $this->assertDatabaseCount('pharmacies', 1);

        // 2. Second record: "CVS Pharmacy" at the same 8 E Washington St
        $res2 = $service->processRecord([
            'name' => 'CVS Pharmacy',
            'street_address' => '8 E Washington St',
            'city' => 'North Attleborough',
            'state' => 'MA',
            'postal_code' => '02760',
            'phone' => '(508) 695-1481',
            'source' => 'google_maps',
        ]);

        $this->assertSame('updated', $res2['action']);
        $this->assertDatabaseCount('pharmacies', 1);

        // The database record should have been upgraded from "CVS" to "CVS Pharmacy"
        $pharmacy = Pharmacy::first();
        $this->assertSame('CVS Pharmacy', $pharmacy->name);
    }
}

