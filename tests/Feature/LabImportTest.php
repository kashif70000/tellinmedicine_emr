<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Services\Contracts\LabScraperInterface;
use App\Services\GoogleMapsLabScraper;
use App\Services\LabImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LabImportTest extends TestCase
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

        // Prevent all stray external HTTP calls
        Http::preventStrayRequests();
    }

    /**
     * Test 1: Laboratory record can be created.
     */
    public function test_laboratory_record_can_be_created(): void
    {
        $lab = Laboratory::create([
            'name' => 'Boston Diagnostic Laboratory',
            'category' => 'Medical laboratory',
            'street_address' => '750 Washington St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02111',
            'phone' => '(617) 555-0199',
            'website' => 'https://bostondiagnosticlab.com',
            'latitude' => 42.3501230,
            'longitude' => -71.0623450,
            'google_place_id' => 'ChIJ_lab_place_123',
            'source' => 'google_maps',
            'external_source_url' => 'https://maps.google.com/?cid=998877',
        ]);

        $this->assertDatabaseHas('laboratories', [
            'id' => $lab->id,
            'name' => 'Boston Diagnostic Laboratory',
            'category' => 'Medical laboratory',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02111',
            'google_place_id' => 'ChIJ_lab_place_123',
        ]);

        $this->assertSame(42.350123, $lab->latitude);
        $this->assertSame(-71.062345, $lab->longitude);
    }

    /**
     * Test 2: Normalization works.
     */
    public function test_normalization_works(): void
    {
        /** @var LabImportService $service */
        $service = $this->app->make(LabImportService::class);

        $raw = [
            'name' => '   Quest   Diagnostics   ',
            'category' => '  medical   laboratory  ',
            'address' => '  419   Boylston   St  ',
            'city' => 'boston',
            'state' => 'ma',
            'postal_code' => '02116',
            'phone' => '6175550144',
            'website' => 'questdiagnostics.com',
            'latitude' => '42.3512',
            'longitude' => '-71.0724',
            'google_place_id' => '  place_quest_123  ',
            'source' => '   ',
        ];

        $normalized = $service->normalizeRecord($raw, 'Boston', 'MA');

        $this->assertSame('Quest Diagnostics', $normalized['name']);
        $this->assertSame('Medical Laboratory', $normalized['category']);
        $this->assertSame('419 Boylston St', $normalized['street_address']);
        $this->assertSame('Boston', $normalized['city']);
        $this->assertSame('MA', $normalized['state']);
        $this->assertSame('02116', $normalized['postal_code']);
        $this->assertSame('(617) 555-0144', $normalized['phone']);
        $this->assertSame('https://questdiagnostics.com', $normalized['website']);
        $this->assertSame(42.3512, $normalized['latitude']);
        $this->assertSame(-71.0724, $normalized['longitude']);
        $this->assertSame('place_quest_123', $normalized['google_place_id']);
        $this->assertSame('google_maps', $normalized['source']);
    }

    /**
     * Test 3: Leading-zero ZIP remains a string.
     */
    public function test_leading_zero_zip_remains_a_string(): void
    {
        /** @var LabImportService $service */
        $service = $this->app->make(LabImportService::class);

        $raw = [
            'name' => 'Labcorp Patient Service Center',
            'street_address' => '35 Kneeland St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02111',
        ];

        $normalized = $service->normalizeRecord($raw, 'Boston', 'MA');

        $this->assertSame('02111', $normalized['postal_code']);
        $this->assertIsString($normalized['postal_code']);
        $this->assertNotSame(2111, $normalized['postal_code'], 'Postal code must not be converted to integer');

        $this->assertSame('02111', $service->normalizePostalCode('02111'));
        $this->assertIsString($service->normalizePostalCode('02111'));
    }

    /**
     * Test 4: ZIP+4 is preserved.
     */
    public function test_zip_plus_four_is_preserved(): void
    {
        /** @var LabImportService $service */
        $service = $this->app->make(LabImportService::class);

        $raw = [
            'name' => 'Tufts Medical Center Clinical Labs',
            'street_address' => '800 Washington St',
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
     * Test 5: Same lab imported twice does not create duplicates.
     */
    public function test_same_lab_imported_twice_does_not_create_duplicates(): void
    {
        $mockData = [
            [
                'name' => 'Quest Diagnostics',
                'category' => 'Medical laboratory',
                'street_address' => '500 Commonwealth Ave',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02215',
                'phone' => '(617) 555-0199',
                'website' => 'https://questdiagnostics.com',
                'google_place_id' => 'ChIJ_quest_commonwealth',
            ],
        ];

        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->expects($this->exactly(2))
            ->method('search')
            ->willReturn($mockData);

        $this->app->instance(LabScraperInterface::class, $mockScraper);

        /** @var LabImportService $service */
        $service = $this->app->make(LabImportService::class);

        $firstResult = $service->import('Boston', 'MA');
        $this->assertSame(1, $firstResult['inserted']);
        $this->assertSame(0, $firstResult['updated']);
        $this->assertSame(0, $firstResult['skipped']);
        $this->assertDatabaseCount('laboratories', 1);

        $secondResult = $service->import('Boston', 'MA');
        $this->assertSame(0, $secondResult['inserted']);
        $this->assertSame(0, $secondResult['updated']);
        $this->assertSame(1, $secondResult['skipped']);
        $this->assertDatabaseCount('laboratories', 1);
    }

    /**
     * Test 6: Two branches of Quest Diagnostics at different addresses remain separate.
     */
    public function test_two_branches_of_quest_diagnostics_at_different_addresses_remain_separate(): void
    {
        $mockData = [
            [
                'name' => 'Quest Diagnostics',
                'category' => 'Medical laboratory',
                'street_address' => '419 Boylston St',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02116',
                'phone' => '(617) 555-1111',
            ],
            [
                'name' => 'Quest Diagnostics',
                'category' => 'Medical laboratory',
                'street_address' => '175 Cambridge St',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02114',
                'phone' => '(617) 555-2222',
            ],
        ];

        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(LabScraperInterface::class, $mockScraper);

        /** @var LabImportService $service */
        $service = $this->app->make(LabImportService::class);
        $result = $service->import('Boston', 'MA');

        $this->assertSame(2, $result['inserted']);
        $this->assertDatabaseCount('laboratories', 2);

        $this->assertDatabaseHas('laboratories', [
            'name' => 'Quest Diagnostics',
            'street_address' => '419 Boylston St',
            'postal_code' => '02116',
        ]);

        $this->assertDatabaseHas('laboratories', [
            'name' => 'Quest Diagnostics',
            'street_address' => '175 Cambridge St',
            'postal_code' => '02114',
        ]);
    }

    /**
     * Test 7: Existing lab is enriched by newly available fields.
     */
    public function test_existing_lab_is_enriched_by_newly_available_fields(): void
    {
        $lab = Laboratory::create([
            'name' => 'Labcorp',
            'category' => 'Medical laboratory',
            'street_address' => '200 Newbury St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02116',
            'phone' => null,
            'website' => null,
            'google_place_id' => 'ChIJ_labcorp_newbury',
        ]);

        $mockData = [
            [
                'name' => 'Labcorp',
                'category' => 'Medical laboratory',
                'street_address' => '200 Newbury St',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02116',
                'phone' => '617-555-9988',
                'website' => 'https://labcorp.com',
                'latitude' => 42.3501,
                'longitude' => -71.0801,
                'google_place_id' => 'ChIJ_labcorp_newbury',
            ],
        ];

        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(LabScraperInterface::class, $mockScraper);

        /** @var LabImportService $service */
        $service = $this->app->make(LabImportService::class);
        $result = $service->import('Boston', 'MA');

        $this->assertSame(0, $result['inserted']);
        $this->assertSame(1, $result['updated']);
        $this->assertDatabaseCount('laboratories', 1);

        $lab->refresh();
        $this->assertSame('(617) 555-9988', $lab->phone);
        $this->assertSame('https://labcorp.com', $lab->website);
        $this->assertSame(42.3501, $lab->latitude);
        $this->assertSame(-71.0801, $lab->longitude);
    }

    /**
     * Test 8: Existing non-empty values are not overwritten by null.
     */
    public function test_existing_non_empty_values_are_not_overwritten_by_null(): void
    {
        $lab = Laboratory::create([
            'name' => 'Beth Israel Deaconess Clinical Labs',
            'category' => 'Medical laboratory',
            'street_address' => '330 Brookline Ave',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02215',
            'phone' => '(617) 555-3344',
            'website' => 'https://bidmc.org/labs',
            'latitude' => 42.3385,
            'longitude' => -71.1070,
            'google_place_id' => 'ChIJ_bidmc_labs',
        ]);

        $mockData = [
            [
                'name' => 'Beth Israel Deaconess Clinical Labs',
                'category' => null,
                'street_address' => '330 Brookline Ave',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => null,
                'phone' => '',
                'website' => null,
                'latitude' => null,
                'longitude' => null,
                'google_place_id' => 'ChIJ_bidmc_labs',
            ],
        ];

        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(LabScraperInterface::class, $mockScraper);

        /** @var LabImportService $service */
        $service = $this->app->make(LabImportService::class);
        $result = $service->import('Boston', 'MA');

        $this->assertSame(0, $result['inserted']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['skipped']);

        $lab->refresh();
        $this->assertSame('(617) 555-3344', $lab->phone);
        $this->assertSame('https://bidmc.org/labs', $lab->website);
        $this->assertSame('02215', $lab->postal_code);
        $this->assertSame(42.3385, $lab->latitude);
        $this->assertSame(-71.107, $lab->longitude);
    }

    /**
     * Test 9: Dry-run performs no database writes.
     */
    public function test_dry_run_performs_no_writes(): void
    {
        $mockData = [
            [
                'name' => 'BioReference Laboratories',
                'category' => 'Medical laboratory',
                'street_address' => '650 Columbus Ave',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02118',
                'phone' => '(617) 555-7788',
            ],
        ];

        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(LabScraperInterface::class, $mockScraper);

        /** @var LabImportService $service */
        $service = $this->app->make(LabImportService::class);
        $result = $service->import('Boston', 'MA', null, true);

        $this->assertSame(1, $result['found']);
        $this->assertSame(1, $result['inserted']);
        $this->assertDatabaseCount('laboratories', 0);
    }

    /**
     * Test 10: Malformed result is skipped safely.
     */
    public function test_malformed_result_is_skipped(): void
    {
        $mockData = [
            [
                'name' => '', // Empty name
                'street_address' => '100 Main St',
                'city' => 'Boston',
                'state' => 'MA',
            ],
            [
                'name' => '   ', // Blank name
                'street_address' => '200 Main St',
            ],
            [
                'name' => 'Valid Diagnostic Lab',
                'category' => 'Diagnostic center',
                'street_address' => '300 Washington St',
                'city' => 'Boston',
                'state' => 'MA',
                'phone' => '6175559090',
            ],
        ];

        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(LabScraperInterface::class, $mockScraper);

        /** @var LabImportService $service */
        $service = $this->app->make(LabImportService::class);
        $result = $service->import('Boston', 'MA');

        $this->assertSame(3, $result['found']);
        $this->assertSame(1, $result['inserted']);
        $this->assertSame(2, $result['skipped']);
        $this->assertSame(0, $result['failed']);
        $this->assertDatabaseCount('laboratories', 1);
        $this->assertDatabaseHas('laboratories', ['name' => 'Valid Diagnostic Lab']);
    }

    /**
     * Test 11: Google tracking token cannot become lab name.
     */
    public function test_google_tracking_token_cannot_become_lab_name(): void
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

        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(LabScraperInterface::class, $mockScraper);

        /** @var LabImportService $service */
        $service = $this->app->make(LabImportService::class);
        $result = $service->import('Boston', 'MA');

        $this->assertSame(1, $result['found']);
        $this->assertSame(0, $result['inserted']);
        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseCount('laboratories', 0);
    }

    /**
     * Test 12: Quest Diagnostics is accepted.
     */
    public function test_quest_diagnostics_is_accepted(): void
    {
        $mockData = [
            [
                'name' => 'Quest Diagnostics',
                'category' => 'Medical laboratory',
                'street_address' => '285 Columbus Ave',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02116',
                'phone' => '(617) 236-8538',
            ],
        ];

        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(LabScraperInterface::class, $mockScraper);

        /** @var LabImportService $service */
        $service = $this->app->make(LabImportService::class);
        $result = $service->import('Boston', 'MA');

        $this->assertSame(1, $result['inserted']);
        $this->assertDatabaseHas('laboratories', [
            'name' => 'Quest Diagnostics',
            'street_address' => '285 Columbus Ave',
            'postal_code' => '02116',
        ]);
    }

    /**
     * Test 13: Labcorp is accepted.
     */
    public function test_labcorp_is_accepted(): void
    {
        $mockData = [
            [
                'name' => 'Labcorp',
                'category' => 'Medical laboratory',
                'street_address' => '841 Boylston St',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02116',
                'phone' => '(617) 236-1692',
            ],
        ];

        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(LabScraperInterface::class, $mockScraper);

        /** @var LabImportService $service */
        $service = $this->app->make(LabImportService::class);
        $result = $service->import('Boston', 'MA');

        $this->assertSame(1, $result['inserted']);
        $this->assertDatabaseHas('laboratories', [
            'name' => 'Labcorp',
            'street_address' => '841 Boylston St',
        ]);
    }

    /**
     * Test 14: Google UI strings are rejected.
     */
    public function test_google_ui_strings_are_rejected(): void
    {
        $scraper = new GoogleMapsLabScraper();

        $forbidden = ['Directions', 'Website', 'Call', 'Share', 'Save', 'Results', 'Sponsored', 'Book online'];
        foreach ($forbidden as $label) {
            $this->assertFalse(
                $scraper->isValidBusinessName($label, 'Boston', 'MA'),
                "Expected UI string '{$label}' to be rejected as a laboratory name."
            );
        }

        $this->assertFalse($scraper->isValidBusinessName('0ahUKEwiy5uSltdyWAxXSkeEIHXd5OxkQjqADCAUoAg'));
        $this->assertFalse($scraper->isValidBusinessName('Boston, MA, USA', 'Boston', 'MA'));
        $this->assertTrue($scraper->isValidBusinessName('Quest Diagnostics', 'Boston', 'MA'));
        $this->assertTrue($scraper->isValidBusinessName('Labcorp', 'Boston', 'MA'));
    }

    /**
     * Test 15: Unrelated research/university lab is rejected when category indicates non-medical use.
     */
    public function test_unrelated_research_university_lab_is_rejected(): void
    {
        $mockData = [
            [
                'name' => 'MIT Computer Science and Artificial Intelligence Laboratory',
                'category' => 'University research laboratory',
                'street_address' => '32 Vassar St',
                'city' => 'Cambridge',
                'state' => 'MA',
            ],
            [
                'name' => 'Harvard Materials Research Science and Engineering Center',
                'category' => 'Research institute',
                'street_address' => '29 Oxford St',
                'city' => 'Cambridge',
                'state' => 'MA',
            ],
        ];

        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(LabScraperInterface::class, $mockScraper);

        /** @var LabImportService $service */
        $service = $this->app->make(LabImportService::class);
        $result = $service->import('Cambridge', 'MA');

        $this->assertSame(2, $result['found']);
        $this->assertSame(0, $result['inserted']);
        $this->assertSame(2, $result['skipped']);
        $this->assertDatabaseCount('laboratories', 0);
    }

    /**
     * Test 16: Dental laboratory is rejected.
     */
    public function test_dental_laboratory_is_rejected(): void
    {
        $mockData = [
            [
                'name' => 'Baystate Dental Laboratory',
                'category' => 'Dental laboratory',
                'street_address' => '100 Main St',
                'city' => 'Boston',
                'state' => 'MA',
            ],
        ];

        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(LabScraperInterface::class, $mockScraper);

        /** @var LabImportService $service */
        $service = $this->app->make(LabImportService::class);
        $result = $service->import('Boston', 'MA');

        $this->assertSame(1, $result['found']);
        $this->assertSame(0, $result['inserted']);
        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseCount('laboratories', 0);
    }

    /**
     * Test 17: Medical laboratory category is accepted.
     */
    public function test_medical_laboratory_category_is_accepted(): void
    {
        $mockData = [
            [
                'name' => 'Boston Clinical Testing Service',
                'category' => 'Medical laboratory',
                'street_address' => '55 Fruit St',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02114',
            ],
        ];

        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(LabScraperInterface::class, $mockScraper);

        /** @var LabImportService $service */
        $service = $this->app->make(LabImportService::class);
        $result = $service->import('Boston', 'MA');

        $this->assertSame(1, $result['inserted']);
        $this->assertDatabaseHas('laboratories', [
            'name' => 'Boston Clinical Testing Service',
            'category' => 'Medical Laboratory',
        ]);
    }

    /**
     * Test 18: Diagnostic / blood testing category is accepted.
     */
    public function test_diagnostic_blood_testing_category_is_accepted(): void
    {
        $mockData = [
            [
                'name' => 'CarePoint Blood Testing Center',
                'category' => 'Blood testing service',
                'street_address' => '1200 Washington St',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02118',
            ],
            [
                'name' => 'Advanced Diagnostic Imaging & Lab',
                'category' => 'Diagnostic center',
                'street_address' => '730 Commonwealth Ave',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02215',
            ],
        ];

        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(LabScraperInterface::class, $mockScraper);

        /** @var LabImportService $service */
        $service = $this->app->make(LabImportService::class);
        $result = $service->import('Boston', 'MA');

        $this->assertSame(2, $result['inserted']);
        $this->assertDatabaseCount('laboratories', 2);
    }

    /**
     * Test 19: Duplicate results from multiple search queries are merged.
     */
    public function test_duplicate_results_from_multiple_search_queries_are_merged(): void
    {
        $mockData = [
            [
                'name' => 'Quest Diagnostics',
                'category' => 'Medical laboratory',
                'street_address' => '500 Commonwealth Ave',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02215',
                'google_place_id' => 'ChIJ_quest_500',
            ],
            [
                'name' => 'Quest Diagnostics',
                'category' => 'Medical laboratory',
                'street_address' => '500 Commonwealth Ave',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02215',
                'google_place_id' => 'ChIJ_quest_500',
            ],
            [
                'name' => 'Quest Diagnostics',
                'category' => 'Medical laboratory',
                'street_address' => '500 Commonwealth Ave',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02215',
                'google_place_id' => 'ChIJ_quest_500',
            ],
        ];

        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->method('search')->willReturn($mockData);
        $this->app->instance(LabScraperInterface::class, $mockScraper);

        /** @var LabImportService $service */
        $service = $this->app->make(LabImportService::class);
        $result = $service->import('Boston', 'MA');

        $this->assertSame(3, $result['found']);
        $this->assertSame(1, $result['inserted']);
        $this->assertSame(2, $result['skipped']);
        $this->assertDatabaseCount('laboratories', 1);
    }

    /**
     * Test 20: No --limit remains unlimited/null.
     */
    public function test_no_limit_remains_unlimited_null(): void
    {
        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->expects($this->once())
            ->method('search')
            ->with('Boston', 'MA', null)
            ->willReturn([]);

        $this->app->instance(LabScraperInterface::class, $mockScraper);

        $this->artisan('labs:import', [
            'city' => 'Boston',
            'state' => 'MA',
        ])
            ->expectsOutputToContain('Limit:           No limit (until results exhausted)')
            ->assertExitCode(0);
    }

    /**
     * Test 21: Explicit --limit=5 limits UNIQUE valid labs to 5.
     */
    public function test_explicit_limit_five_limits_unique_valid_labs_to_five(): void
    {
        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->expects($this->once())
            ->method('search')
            ->with('Boston', 'MA', 5)
            ->willReturn([]);

        $this->app->instance(LabScraperInterface::class, $mockScraper);

        $this->artisan('labs:import', [
            'city' => 'Boston',
            'state' => 'MA',
            '--limit' => 5,
        ])
            ->expectsOutputToContain('Limit:           5')
            ->assertExitCode(0);
    }

    /**
     * Test 22: Scraper can exceed 20 when no limit or larger limit is supplied.
     */
    public function test_scraper_can_exceed_twenty_when_larger_limit_is_supplied(): void
    {
        $mockData = [];
        for ($i = 1; $i <= 30; $i++) {
            $mockData[] = [
                'name' => "Diagnostic Lab #{$i}",
                'category' => 'Medical laboratory',
                'street_address' => "{$i} Health Blvd",
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => sprintf('021%02d', $i % 99),
                'google_place_id' => "ChIJ_mock_lab_{$i}",
            ];
        }

        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->expects($this->once())
            ->method('search')
            ->with('Boston', 'MA', 50)
            ->willReturn($mockData);

        $this->app->instance(LabScraperInterface::class, $mockScraper);

        $this->artisan('labs:import', [
            'city' => 'Boston',
            'state' => 'MA',
            '--limit' => 50,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Found:    30')
            ->expectsOutputToContain('Inserted: 30')
            ->assertExitCode(0);
    }

    /**
     * Test 23: No-new-results termination works.
     */
    public function test_no_new_results_termination_works(): void
    {
        $mockScraper = $this->createMock(GoogleMapsLabScraper::class);
        $mockScraper->method('search')->willReturn([
            [
                'name' => 'Metro Diagnostic Lab',
                'street_address' => '100 Tremont St',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02108',
            ]
        ]);
        $mockScraper->method('getLastStopReason')->willReturn('no_new_results_after_repeated_scrolling');
        $this->app->instance(LabScraperInterface::class, $mockScraper);

        $this->artisan('labs:import', [
            'city' => 'Boston',
            'state' => 'MA',
        ])
            ->expectsOutputToContain('Stop Reason: No new results after repeated scrolling')
            ->assertExitCode(0);
    }

    /**
     * Test 24: End-of-results termination works.
     */
    public function test_end_of_results_termination_works(): void
    {
        $mockScraper = $this->createMock(GoogleMapsLabScraper::class);
        $mockScraper->method('search')->willReturn([
            [
                'name' => 'South End Diagnostic Lab',
                'street_address' => '500 Tremont St',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02116',
            ]
        ]);
        $mockScraper->method('getLastStopReason')->willReturn('end_of_results_reached');
        $this->app->instance(LabScraperInterface::class, $mockScraper);

        $this->artisan('labs:import', [
            'city' => 'Boston',
            'state' => 'MA',
        ])
            ->expectsOutputToContain('Stop Reason: End of Google Maps results reached')
            ->assertExitCode(0);
    }

    /**
     * Test 25: Challenge / block does not report fake success.
     */
    public function test_challenge_or_block_does_not_report_fake_success(): void
    {
        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->method('search')->willReturn([]);
        $this->app->instance(LabScraperInterface::class, $mockScraper);

        $this->artisan('labs:import', [
            'city' => 'Boston',
            'state' => 'MA',
        ])
            ->expectsOutputToContain('Found:    0')
            ->expectsOutputToContain('Inserted: 0')
            ->expectsOutputToContain('No medical laboratory listings were found or imported for Boston, MA.')
            ->assertExitCode(0);
    }

    /**
     * Test 26: Scraper layer can be mocked without launching Chromium.
     */
    public function test_scraper_layer_can_be_mocked_without_launching_chromium(): void
    {
        Http::preventStrayRequests();

        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->expects($this->once())
            ->method('search')
            ->with('Cambridge', 'MA', 10)
            ->willReturn([
                [
                    'name' => 'Cambridge Health Alliance Clinical Lab',
                    'category' => 'Medical laboratory',
                    'street_address' => '1493 Cambridge St',
                    'city' => 'Cambridge',
                    'state' => 'MA',
                    'postal_code' => '02138',
                    'phone' => '(617) 555-8811',
                    'google_place_id' => 'ChIJ_cha_labs',
                ],
            ]);

        $this->app->instance(LabScraperInterface::class, $mockScraper);

        $this->artisan('labs:import', [
            'city' => 'Cambridge',
            'state' => 'MA',
            '--limit' => 10,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('Found:    1')
            ->expectsOutputToContain('Dry run complete. No records were written to the database.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('laboratories', 0);
    }

    /**
     * Test 27: Direct single-place mode payload is parsed and imported.
     */
    public function test_direct_single_place_mode_payload_is_imported(): void
    {
        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->method('search')->willReturn([
            [
                'name' => 'Quest Diagnostics',
                'category' => 'Medical laboratory',
                'street_address' => '211 Park St',
                'city' => 'Attleboro',
                'state' => 'MA',
                'postal_code' => '02703',
                'phone' => '(508) 222-1234',
                'website' => 'https://questdiagnostics.com',
                'latitude' => 41.9445,
                'longitude' => -71.2842,
                'google_place_id' => 'ChIJ_quest_attleboro',
                'source' => 'google_maps',
            ]
        ]);
        $this->app->instance(LabScraperInterface::class, $mockScraper);

        $this->artisan('labs:import', [
            'city' => 'Attleboro',
            'state' => 'MA',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Found:    1')
            ->expectsOutputToContain('Dry run complete')
            ->assertExitCode(0);

        $this->assertDatabaseCount('laboratories', 0);
    }

    /**
     * Test 28: Single place without optional phone and ZIP code survives validation.
     */
    public function test_single_place_without_phone_and_zip_survives(): void
    {
        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->method('search')->willReturn([
            [
                'name' => 'Sturdy Memorial Hospital Laboratory',
                'category' => 'Diagnostic center',
                'street_address' => '211 Park St',
                'city' => 'Attleboro',
                'state' => 'MA',
                'postal_code' => null,
                'phone' => null,
                'website' => null,
                'google_place_id' => null,
                'source' => 'google_maps',
            ]
        ]);
        $this->app->instance(LabScraperInterface::class, $mockScraper);

        $this->artisan('labs:import', [
            'city' => 'Attleboro',
            'state' => 'MA',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Found:    1')
            ->assertExitCode(0);
    }

    /**
     * Test 29: Single place across multiple queries is deduplicated.
     */
    public function test_single_place_across_multiple_queries_is_deduplicated(): void
    {
        $mockScraper = $this->createMock(LabScraperInterface::class);
        $mockScraper->method('search')->willReturn([
            [
                'name' => 'Quest Diagnostics',
                'category' => 'Medical laboratory',
                'street_address' => '211 Park St',
                'city' => 'Attleboro',
                'state' => 'MA',
                'postal_code' => '02703',
                'google_place_id' => 'ChIJ_quest_attleboro',
            ],
            [
                'name' => 'Quest Diagnostics',
                'category' => 'Medical laboratory',
                'street_address' => '211 Park St',
                'city' => 'Attleboro',
                'state' => 'MA',
                'postal_code' => '02703',
                'google_place_id' => 'ChIJ_quest_attleboro',
            ],
        ]);
        $this->app->instance(LabScraperInterface::class, $mockScraper);

        $service = $this->app->make(LabImportService::class);
        $stats = $service->import('Attleboro', 'MA', null, false);
        $this->assertSame(2, $stats['found']);
        $this->assertSame(1, $stats['inserted']);
        $this->assertSame(1, $stats['skipped']);
        $this->assertDatabaseCount('laboratories', 1);
    }

    /**
     * Test 30: Worldwide medical laboratory brands are recognized.
     */
    public function test_worldwide_medical_lab_brands_are_recognized(): void
    {
        /** @var LabImportService $service */
        $service = $this->app->make(LabImportService::class);

        $worldwideBrands = [
            'Chughtai Lab Jail Road',
            'Shaukat Khanum Diagnostic Centre Lahore',
            'Islamabad Diagnostic Centre (IDC)',
            'Excel Labs F-7 Markaz',
            'Essa Laboratories Karachi',
            'Citilab Research Centre',
            'Quest Diagnostics Boston',
            'Labcorp Burlington',
        ];

        foreach ($worldwideBrands as $brand) {
            $this->assertTrue(
                $service->isKnownMedicalLabBrand($brand),
                "Expected brand '{$brand}' to be recognized as a known medical laboratory brand."
            );
        }
    }

    /**
     * Test 31: Strict laboratory filtering accepts testing labs and rejects general clinics/hospitals.
     */
    public function test_strict_laboratory_filtering_excludes_general_hospitals_and_clinics(): void
    {
        /** @var LabImportService $service */
        $service = $this->app->make(LabImportService::class);

        // Genuine medical testing laboratories & diagnostic centers (MUST BE ACCEPTED)
        $validLabs = [
            ['name' => 'Quest Diagnostics', 'category' => 'Medical laboratory'],
            ['name' => 'Caritas Medical Lab Draw Station', 'category' => 'Medical laboratory'],
            ['name' => 'Safe Lab STD Testing Centre', 'category' => 'STD clinic'],
            ['name' => 'East Side Clinical Laboratory', 'category' => 'Laboratory'],
            ['name' => 'Shields PET/CT at Sturdy Memorial Hospital', 'category' => 'Medical diagnostic imaging center'],
            ['name' => 'Chughtai Lab', 'category' => 'Medical laboratory'],
            ['name' => 'Labcorp', 'category' => 'Medical laboratory'],
        ];

        foreach ($validLabs as $lab) {
            $this->assertTrue(
                $service->isStrictMedicalLaboratory($lab['category'], $lab['name']),
                "Expected genuine lab '{$lab['name']}' ({$lab['category']}) to be ACCEPTED."
            );
        }

        // Non-laboratory general clinics, hospitals, doctor offices (MUST BE REJECTED)
        $nonLabs = [
            ['name' => 'Sturdy Memorial Hospital', 'category' => 'General hospital'],
            ['name' => 'AFC Urgent Care Attleboro', 'category' => 'Urgent care center'],
            ['name' => 'Lawrence Medical Center - Attleboro', 'category' => 'Medical clinic'],
            ['name' => 'Attleboro Women\'s Health Center', 'category' => 'Pregnancy care center'],
            ['name' => 'Sturdy Health Primary Care - North Attleboro', 'category' => 'Medical clinic'],
            ['name' => 'Manet Community Health Center', 'category' => 'Community health center'],
            ['name' => 'Milford Regional Medical Center - Rehabilitation', 'category' => 'Rehabilitation center'],
            ['name' => 'Tristan Medical Primary Care', 'category' => 'Doctor'],
            ['name' => 'Fresenius Kidney Care Attleboro', 'category' => 'Dialysis center'],
            ['name' => 'Highbar Physical Therapy', 'category' => 'Physical therapy clinic'],
            ['name' => 'Visionworks', 'category' => 'Optometrist'],
        ];

        foreach ($nonLabs as $nonLab) {
            $this->assertFalse(
                $service->isStrictMedicalLaboratory($nonLab['category'], $nonLab['name']),
                "Expected non-lab '{$nonLab['name']}' ({$nonLab['category']}) to be REJECTED."
            );
        }
    }
}
