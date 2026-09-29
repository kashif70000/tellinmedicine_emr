<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\NppesHealthcareLocation;
use App\Models\Pharmacy;
use App\Services\Fax\FaxMatchService;
use App\Services\Fax\HealthcareEntityFaxEnrichmentService;
use App\Services\Fax\LaboratoryFaxEnrichmentService;
use App\Services\Fax\PharmacyFaxEnrichmentService;
use App\Services\Nppes\NppesClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FaxEnrichmentTest extends TestCase
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

        // Prevent all stray external HTTP calls to guarantee no live requests to CMS NPPES
        Http::preventStrayRequests();
        Cache::flush();
        config(['nppes.source' => 'api']);
    }

    /**
     * Helper to mock NPPES standard response format.
     */
    protected function makeNppesResponse(array $results): array
    {
        return [
            'result_count' => count($results),
            'results' => $results,
        ];
    }

    /**
     * Helper to build a mock NPPES organization object.
     */
    protected function buildNppesOrg(
        string $npi,
        string $orgName,
        string $street,
        string $city,
        string $state,
        string $zip,
        ?string $phone = null,
        ?string $fax = null,
        ?string $mailingStreet = '1 CVS Dr',
        ?string $mailingFax = '4015559999'
    ): array {
        return [
            'number' => $npi,
            'basic' => [
                'organization_name' => $orgName,
                'enumeration_date' => '2006-08-15',
                'last_updated' => '2023-01-10',
            ],
            'addresses' => [
                [
                    'address_purpose' => 'LOCATION', // Practice Location
                    'address_1' => $street,
                    'city' => $city,
                    'state' => $state,
                    'postal_code' => $zip,
                    'telephone_number' => $phone,
                    'fax_number' => $fax,
                ],
                [
                    'address_purpose' => 'MAILING', // Corporate / Mailing Location
                    'address_1' => $mailingStreet,
                    'city' => 'Woonsocket',
                    'state' => 'RI',
                    'postal_code' => '02895',
                    'telephone_number' => '4015551000',
                    'fax_number' => $mailingFax,
                ],
            ],
            'taxonomies' => [
                [
                    'code' => '3336C0003X',
                    'desc' => 'Community/Retail Pharmacy',
                    'primary' => true,
                ],
            ],
        ];
    }

    /**
     * Test 1: Pharmacy strong NPPES match is identified and practice fax saved.
     */
    public function test_pharmacy_strong_nppes_match_is_identified(): void
    {
        $pharmacy = Pharmacy::create([
            'name' => 'CVS Pharmacy',
            'street_address' => '191 N Main St',
            'city' => 'Attleboro',
            'state' => 'MA',
            'postal_code' => '02703',
            'phone' => '(508) 222-0880',
        ]);

        Http::fake([
            '*npiregistry.cms.hhs.gov/api/*' => Http::response($this->makeNppesResponse([
                $this->buildNppesOrg(
                    '1649379017',
                    'CVS PHARMACY INC',
                    '191 N MAIN ST',
                    'ATTLEBORO',
                    'MA',
                    '027031234',
                    '5082220880',
                    '5082220885' // Practice fax
                )
            ]), 200),
        ]);

        /** @var HealthcareEntityFaxEnrichmentService $service */
        $service = $this->app->make(HealthcareEntityFaxEnrichmentService::class);
        $res = $service->enrichEntity($pharmacy, false);

        $this->assertSame('updated', $res['action']);
        $this->assertSame('found', $res['status']);
        $this->assertSame('+15082220885', $res['fax']);
        $this->assertSame('1649379017', $res['npi']);
        $this->assertGreaterThanOrEqual(80, $res['match_score']);

        $pharmacy->refresh();
        $this->assertSame('+15082220885', $pharmacy->fax);
        $this->assertSame('1649379017', $pharmacy->npi);
        $this->assertSame('nppes', $pharmacy->fax_source);
        $this->assertSame('found', $pharmacy->fax_lookup_status);
        $this->assertNotNull($pharmacy->fax_last_checked_at);
    }

    /**
     * Test 2: Laboratory strong NPPES match is identified and practice fax saved.
     */
    public function test_laboratory_strong_nppes_match_is_identified(): void
    {
        $lab = Laboratory::create([
            'name' => 'Quest Diagnostics',
            'street_address' => '562 Washington St',
            'city' => 'South Attleboro',
            'state' => 'MA',
            'postal_code' => '02703',
            'phone' => '(508) 406-4626',
        ]);

        Http::fake([
            '*npiregistry.cms.hhs.gov/api/*' => Http::response($this->makeNppesResponse([
                $this->buildNppesOrg(
                    '1982736451',
                    'QUEST DIAGNOSTICS LLC',
                    '562 WASHINGTON ST',
                    'SOUTH ATTLEBORO',
                    'MA',
                    '02703',
                    '5084064626',
                    '5084064630'
                )
            ]), 200),
        ]);

        /** @var HealthcareEntityFaxEnrichmentService $service */
        $service = $this->app->make(HealthcareEntityFaxEnrichmentService::class);
        $res = $service->enrichEntity($lab, false);

        $this->assertSame('updated', $res['action']);
        $this->assertSame('found', $res['status']);
        $this->assertSame('+15084064630', $res['fax']);
        $this->assertSame('1982736451', $res['npi']);

        $lab->refresh();
        $this->assertSame('+15084064630', $lab->fax);
        $this->assertSame('1982736451', $lab->npi);
        $this->assertSame('found', $lab->fax_lookup_status);
    }

    /**
     * Test 3: Correct 10-digit NPI is extracted and validated.
     */
    public function test_correct_npi_is_extracted_and_validated(): void
    {
        $pharmacy = Pharmacy::create([
            'name' => 'County Square Pharmacy',
            'street_address' => '289 County St',
            'city' => 'Attleboro',
            'state' => 'MA',
            'postal_code' => '02703',
        ]);

        Http::fake([
            '*npiregistry.cms.hhs.gov/api/*' => Http::response($this->makeNppesResponse([
                $this->buildNppesOrg('1122334455', 'COUNTY SQUARE PHARMACY', '289 COUNTY ST', 'ATTLEBORO', 'MA', '02703', null, '5082227621')
            ]), 200),
        ]);

        /** @var HealthcareEntityFaxEnrichmentService $service */
        $service = $this->app->make(HealthcareEntityFaxEnrichmentService::class);
        $res = $service->enrichEntity($pharmacy, false);

        $this->assertSame('1122334455', $res['npi']);
        $this->assertMatchesRegularExpression('/^\d{10}$/', $pharmacy->fresh()->npi);
    }

    /**
     * Test 4: Practice-location fax is extracted instead of corporate mailing fax.
     */
    public function test_practice_location_fax_is_extracted_instead_of_mailing_fax(): void
    {
        $pharmacy = Pharmacy::create([
            'name' => 'CVS Pharmacy',
            'street_address' => '8 E Washington St',
            'city' => 'North Attleborough',
            'state' => 'MA',
            'postal_code' => '02760',
        ]);

        Http::fake([
            '*npiregistry.cms.hhs.gov/api/*' => Http::response($this->makeNppesResponse([
                $this->buildNppesOrg(
                    '1659483721',
                    'CVS PHARMACY INC',
                    '8 E WASHINGTON ST',
                    'NORTH ATTLEBOROUGH',
                    'MA',
                    '02760',
                    '5086951481',
                    '5086951485', // Practice location fax
                    '1 CVS Drive (Corporate HQ)', // Mailing address
                    '4017651500' // Mailing office fax
                )
            ]), 200),
        ]);

        /** @var HealthcareEntityFaxEnrichmentService $service */
        $service = $this->app->make(HealthcareEntityFaxEnrichmentService::class);
        $res = $service->enrichEntity($pharmacy, false);

        // Must extract 5086951485 (practice), NOT 4017651500 (corporate mailing)
        $this->assertSame('+15086951485', $res['fax']);
        $this->assertNotSame('+14017651500', $res['fax']);
    }

    /**
     * Test 5: Fax number is normalized to E.164 canonical format.
     */
    public function test_fax_is_normalized_to_e164_format(): void
    {
        $matcher = new FaxMatchService();
        $this->assertSame('+15082226020', $matcher->normalizeFaxNumber('(508) 222-6020'));
        $this->assertSame('+15082226020', $matcher->normalizeFaxNumber('508.222.6020'));
        $this->assertSame('+15082226020', $matcher->normalizeFaxNumber('+1-508-222-6020'));
        $this->assertSame('+15082226020', $matcher->normalizeFaxNumber('15082226020'));
        $this->assertNull($matcher->normalizeFaxNumber('123')); // Too short
    }

    /**
     * Test 6: Leading-zero ZIP code is preserved.
     */
    public function test_leading_zero_zip_code_is_preserved(): void
    {
        $matcher = new FaxMatchService();
        $this->assertSame('02111', $matcher->extractBaseZip('02111'));
        $this->assertSame('02111', $matcher->extractBaseZip('02111-1234'));
        $this->assertSame('02703', $matcher->extractBaseZip('02703'));
    }

    /**
     * Test 7: ZIP+4 matches base 5-digit ZIP safely.
     */
    public function test_zip_plus_4_matches_base_zip(): void
    {
        $matcher = new FaxMatchService();
        $score = $matcher->calculateZipScore('02703', '027031234');
        $this->assertSame(20, $score);
    }

    /**
     * Test 8: Phone match increases match score.
     */
    public function test_phone_match_increases_match_score(): void
    {
        $matcher = new FaxMatchService();
        $this->assertSame(15, $matcher->calculatePhoneScore('(508) 222-0880', '508-222-0880'));
        $this->assertSame(0, $matcher->calculatePhoneScore('(508) 222-0880', '508-555-9999'));
    }

    /**
     * Test 9: Street address match handles abbreviations and directionals.
     */
    public function test_street_address_match_handles_abbreviations(): void
    {
        $matcher = new FaxMatchService();
        $score = $matcher->calculateStreetScore('191 North Main Street', '191 N Main St');
        $this->assertSame(40, $score);

        $score2 = $matcher->calculateStreetScore('8 East Washington Street', '8 E Washington St');
        $this->assertSame(40, $score2);
    }

    /**
     * Test 10: Chain isolation prevents assigning another branch's fax (different street numbers).
     */
    public function test_chain_isolation_prevents_branch_collision(): void
    {
        $pharmacy = Pharmacy::create([
            'name' => 'CVS Pharmacy',
            'street_address' => '191 N Main St',
            'city' => 'Attleboro',
            'state' => 'MA',
            'postal_code' => '02703',
        ]);

        // NPPES returns a DIFFERENT CVS branch on Pleasant St
        Http::fake([
            '*npiregistry.cms.hhs.gov/api/*' => Http::response($this->makeNppesResponse([
                $this->buildNppesOrg(
                    '1827364519',
                    'CVS PHARMACY INC',
                    '486 PLEASANT ST', // Different branch!
                    'ATTLEBORO',
                    'MA',
                    '02703',
                    '5082226020',
                    '5082226025'
                )
            ]), 200),
        ]);

        /** @var HealthcareEntityFaxEnrichmentService $service */
        $service = $this->app->make(HealthcareEntityFaxEnrichmentService::class);
        $res = $service->enrichEntity($pharmacy, false);

        // Must reject matching this candidate because the street address does not match
        $this->assertSame('not_found', $res['status']);
        $this->assertNull($pharmacy->fresh()->fax);
    }

    /**
     * Test 11: Different Walgreens branches are not merged.
     */
    public function test_different_walgreens_branches_are_not_merged(): void
    {
        $matcher = new FaxMatchService();
        $local = [
            'name' => 'Walgreens',
            'street_address' => '196 Pleasant St',
            'city' => 'Attleboro',
            'state' => 'MA',
            'postal_code' => '02703',
        ];
        $candidate = [
            'npi' => '1122334455',
            'organization_name' => 'WALGREEN CO',
            'practice_address' => [
                'address_1' => '475 E Washington St', // Different location
                'city' => 'North Attleborough',
                'state' => 'MA',
                'postal_code' => '02760',
                'fax_number' => '5086957519',
            ],
        ];

        $eval = $matcher->scoreCandidate($local, $candidate);
        $this->assertLessThan(80, $eval['score']);
    }

    /**
     * Test 12: Different Quest Diagnostics branches are not merged.
     */
    public function test_different_quest_branches_are_not_merged(): void
    {
        $matcher = new FaxMatchService();
        $local = [
            'name' => 'Quest Diagnostics',
            'street_address' => '500 E Washington St Ste 22',
            'city' => 'North Attleborough',
            'state' => 'MA',
            'postal_code' => '02760',
        ];
        $candidate = [
            'npi' => '9988776655',
            'organization_name' => 'QUEST DIAGNOSTICS LLC',
            'practice_address' => [
                'address_1' => '562 Washington St', // South Attleboro branch
                'city' => 'South Attleboro',
                'state' => 'MA',
                'postal_code' => '02703',
                'fax_number' => '5084064629',
            ],
        ];

        $eval = $matcher->scoreCandidate($local, $candidate);
        $this->assertLessThan(80, $eval['score']);
    }

    /**
     * Test 13: Multiple high-scoring candidates with close scores produce ambiguous status.
     */
    public function test_multiple_high_scoring_candidates_produce_ambiguous_status(): void
    {
        $matcher = new FaxMatchService();
        $local = [
            'name' => 'Care Diagnostics',
            'street_address' => '100 Main St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02111',
        ];

        $candidate1 = [
            'npi' => '1111111111',
            'organization_name' => 'CARE DIAGNOSTICS BOSTON',
            'practice_address' => [
                'address_1' => '100 Main St Ste 1',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02111',
                'fax_number' => '6175551111',
            ],
        ];
        $candidate2 = [
            'npi' => '2222222222',
            'organization_name' => 'CARE DIAGNOSTICS PARTNERS',
            'practice_address' => [
                'address_1' => '100 Main St Ste 2',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02111',
                'fax_number' => '6175552222',
            ],
        ];

        $res = $matcher->findBestMatch($local, [$candidate1, $candidate2]);
        $this->assertSame('ambiguous', $res['status']);
    }

    /**
     * Test 14: Matching NPPES record without fax returns status no_fax.
     */
    public function test_matching_nppes_record_without_fax_returns_no_fax(): void
    {
        $pharmacy = Pharmacy::create([
            'name' => 'North Attleboro Pharmacy',
            'street_address' => '652 E Washington St #12',
            'city' => 'North Attleborough',
            'state' => 'MA',
            'postal_code' => '02760',
        ]);

        Http::fake([
            '*npiregistry.cms.hhs.gov/api/*' => Http::response($this->makeNppesResponse([
                $this->buildNppesOrg(
                    '1738495026',
                    'NORTH ATTLEBORO PHARMACY',
                    '652 E WASHINGTON ST STE 12',
                    'NORTH ATTLEBOROUGH',
                    'MA',
                    '02760',
                    '5087272323',
                    null // No fax registered in NPPES
                )
            ]), 200),
        ]);

        /** @var HealthcareEntityFaxEnrichmentService $service */
        $service = $this->app->make(HealthcareEntityFaxEnrichmentService::class);
        $res = $service->enrichEntity($pharmacy, false);

        $this->assertSame('no_fax', $res['status']);
        $this->assertSame('1738495026', $res['npi']);
        $this->assertNull($res['fax']);

        $pharmacy->refresh();
        $this->assertSame('1738495026', $pharmacy->npi);
        $this->assertNull($pharmacy->fax);
        $this->assertSame('no_fax', $pharmacy->fax_lookup_status);
    }

    /**
     * Test 15: No candidate found produces status not_found.
     */
    public function test_no_candidate_produces_not_found(): void
    {
        $pharmacy = Pharmacy::create([
            'name' => 'Nonexistent Remote Pharmacy',
            'street_address' => '999 Unknown Way',
            'city' => 'Nowhere',
            'state' => 'MA',
            'postal_code' => '09999',
        ]);

        Http::fake([
            '*npiregistry.cms.hhs.gov/api/*' => Http::response($this->makeNppesResponse([]), 200),
        ]);

        /** @var HealthcareEntityFaxEnrichmentService $service */
        $service = $this->app->make(HealthcareEntityFaxEnrichmentService::class);
        $res = $service->enrichEntity($pharmacy, false);

        $this->assertSame('not_found', $res['status']);
        $this->assertSame('not_found', $pharmacy->fresh()->fax_lookup_status);
    }

    /**
     * Test 16: HTTP network/timeout failure produces status lookup_failed.
     */
    public function test_http_failure_produces_lookup_failed(): void
    {
        $pharmacy = Pharmacy::create([
            'name' => 'Timeout Pharmacy',
            'street_address' => '100 Broken St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02114',
        ]);

        Http::fake([
            '*npiregistry.cms.hhs.gov/api/*' => Http::response('Gateway Timeout', 504),
        ]);

        /** @var HealthcareEntityFaxEnrichmentService $service */
        $service = $this->app->make(HealthcareEntityFaxEnrichmentService::class);
        $res = $service->enrichEntity($pharmacy, false);

        $this->assertSame('lookup_failed', $res['status']);
        $this->assertSame('lookup_failed', $pharmacy->fresh()->fax_lookup_status);
    }

    /**
     * Test 17: Existing non-null fax is NOT overwritten by a matching candidate that lacks a fax.
     */
    public function test_existing_fax_is_not_overwritten_by_null(): void
    {
        $pharmacy = Pharmacy::create([
            'name' => 'Avita Pharmacy 1061',
            'street_address' => '163 Pleasant St Ste 3',
            'city' => 'Attleboro',
            'state' => 'MA',
            'postal_code' => '02703',
            'fax' => '+15082229999',
            'fax_source' => 'directory',
        ]);

        Http::fake([
            '*npiregistry.cms.hhs.gov/api/*' => Http::response($this->makeNppesResponse([
                $this->buildNppesOrg(
                    '1829304152',
                    'AVITA PHARMACY 1061',
                    '163 PLEASANT ST STE 3',
                    'ATTLEBORO',
                    'MA',
                    '02703',
                    '8002959564',
                    null // NPPES candidate has no fax
                )
            ]), 200),
        ]);

        /** @var HealthcareEntityFaxEnrichmentService $service */
        $service = $this->app->make(HealthcareEntityFaxEnrichmentService::class);
        $service->enrichEntity($pharmacy, false);

        $pharmacy->refresh();
        $this->assertSame('+15082229999', $pharmacy->fax); // Kept existing
        $this->assertSame('1829304152', $pharmacy->npi); // Updated NPI
    }

    /**
     * Test 18: Manually verified fax is NEVER overwritten by automatic NPPES.
     */
    public function test_manually_verified_fax_is_never_overwritten(): void
    {
        $pharmacy = Pharmacy::create([
            'name' => 'Shaw\'s Pharmacy',
            'street_address' => '125 Robert F Toner Blvd',
            'city' => 'North Attleborough',
            'state' => 'MA',
            'postal_code' => '02763',
            'fax' => '+15086430399',
            'fax_source' => 'manual',
            'fax_lookup_status' => 'verified',
            'fax_verified_at' => now()->subDay(),
        ]);

        Http::fake([
            '*npiregistry.cms.hhs.gov/api/*' => Http::response($this->makeNppesResponse([
                $this->buildNppesOrg(
                    '1928374650',
                    'SHAWS PHARMACY',
                    '125 ROBERT F TONER BLVD',
                    'NORTH ATTLEBOROUGH',
                    'MA',
                    '02763',
                    '5086430312',
                    '5086439999' // Different fax from NPPES
                )
            ]), 200),
        ]);

        /** @var HealthcareEntityFaxEnrichmentService $service */
        $service = $this->app->make(HealthcareEntityFaxEnrichmentService::class);
        $service->enrichEntity($pharmacy, false);

        $pharmacy->refresh();
        $this->assertSame('+15086430399', $pharmacy->fax);
        $this->assertSame('manual', $pharmacy->fax_source);
        $this->assertSame('verified', $pharmacy->fax_lookup_status);
        $this->assertTrue($pharmacy->isFaxVerified());
    }

    /**
     * Test 19: Dry-run makes ZERO database changes.
     */
    public function test_dry_run_makes_zero_db_changes(): void
    {
        $pharmacy = Pharmacy::create([
            'name' => 'Walmart Pharmacy',
            'street_address' => '1470 S Washington St',
            'city' => 'North Attleborough',
            'state' => 'MA',
            'postal_code' => '02760',
            'phone' => '(508) 699-2007',
        ]);

        Http::fake([
            '*npiregistry.cms.hhs.gov/api/*' => Http::response($this->makeNppesResponse([
                $this->buildNppesOrg(
                    '1092837465',
                    'WALMART PHARMACY',
                    '1470 S WASHINGTON ST',
                    'NORTH ATTLEBOROUGH',
                    'MA',
                    '02760',
                    '5086992007',
                    '5086992015'
                )
            ]), 200),
        ]);

        /** @var HealthcareEntityFaxEnrichmentService $service */
        $service = $this->app->make(HealthcareEntityFaxEnrichmentService::class);
        $res = $service->enrichEntity($pharmacy, true); // DRY RUN = TRUE

        $this->assertSame('dry_run_evaluated', $res['action']);
        $this->assertSame('found', $res['status']);
        $this->assertSame('+15086992015', $res['fax']);

        // Database row must be completely untouched
        $fresh = $pharmacy->fresh();
        $this->assertNull($fresh->fax);
        $this->assertNull($fresh->npi);
        $this->assertNull($fresh->fax_lookup_status);
        $this->assertNull($fresh->fax_last_checked_at);
    }

    /**
     * Test 20: Pharmacy artisan command outputs statistics and executes dry run cleanly.
     */
    public function test_pharmacy_artisan_command_executes_dry_run_cleanly(): void
    {
        Pharmacy::create([
            'name' => 'CVS Pharmacy',
            'street_address' => '191 N Main St',
            'city' => 'Attleboro',
            'state' => 'MA',
            'postal_code' => '02703',
        ]);

        Http::fake([
            '*npiregistry.cms.hhs.gov/api/*' => Http::response($this->makeNppesResponse([
                $this->buildNppesOrg(
                    '1649379017',
                    'CVS PHARMACY INC',
                    '191 N MAIN ST',
                    'ATTLEBORO',
                    'MA',
                    '02703',
                    '5082220880',
                    '5082220885'
                )
            ]), 200),
        ]);

        $this->artisan('pharmacies:enrich-fax', [
            '--city' => 'Attleboro',
            '--state' => 'MA',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Pharmacy Fax & NPI Enrichment — DRY RUN')
            ->expectsOutputToContain('DRY RUN COMPLETE: NO DATABASE CHANGES WERE MADE')
            ->assertExitCode(0);

        $this->assertNull(Pharmacy::first()->fax);
    }

    /**
     * Test 21: Laboratory artisan command executes dry run cleanly.
     */
    public function test_lab_artisan_command_executes_dry_run_cleanly(): void
    {
        Laboratory::create([
            'name' => 'Labcorp',
            'street_address' => '758 Eddy St Ste 101',
            'city' => 'Providence',
            'state' => 'RI',
            'postal_code' => '02903',
        ]);

        Http::fake([
            '*npiregistry.cms.hhs.gov/api/*' => Http::response($this->makeNppesResponse([
                $this->buildNppesOrg(
                    '1234567890',
                    'LABORATORY CORPORATION OF AMERICA',
                    '758 EDDY ST STE 101',
                    'PROVIDENCE',
                    'RI',
                    '02903',
                    null,
                    '4015551234'
                )
            ]), 200),
        ]);

        $this->artisan('labs:enrich-fax', [
            '--city' => 'Providence',
            '--state' => 'RI',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Laboratory Fax & NPI Enrichment — DRY RUN')
            ->expectsOutputToContain('DRY RUN COMPLETE: NO DATABASE CHANGES WERE MADE')
            ->assertExitCode(0);

        $this->assertNull(Laboratory::first()->fax);
    }

    /**
     * Test 22: Cached duplicate lookup prevents repeated HTTP calls.
     */
    public function test_cached_lookup_prevents_repeated_http_calls(): void
    {
        $client = new NppesClient();

        Http::fake([
            '*npiregistry.cms.hhs.gov/api/*' => Http::response($this->makeNppesResponse([
                $this->buildNppesOrg('1122334455', 'CACHED PHARMACY', '100 MAIN ST', 'BOSTON', 'MA', '02111', null, '6175551234')
            ]), 200),
        ]);

        $res1 = $client->search('Cached Pharmacy', 'Boston', 'MA', '02111');
        $res2 = $client->search('Cached Pharmacy', 'Boston', 'MA', '02111');

        $this->assertCount(1, $res1);
        $this->assertCount(1, $res2);
        // Only 1 HTTP request should have been made due to caching
        Http::assertSentCount(1);
    }

    /**
     * Helper to seed local NPPES healthcare location record.
     */
    protected function seedLocalLocation(
        string $npi,
        string $orgName,
        string $street,
        string $city,
        string $state,
        string $zip,
        ?string $phone = null,
        ?string $fax = null,
        string $taxonomyCode = '3336C0003X'
    ): NppesHealthcareLocation {
        return NppesHealthcareLocation::create([
            'npi' => $npi,
            'entity_type' => '2',
            'organization_name' => $orgName,
            'taxonomy_code' => $taxonomyCode,
            'taxonomy_description' => 'Healthcare Organization',
            'address_line_1' => $street,
            'city' => $city,
            'state' => $state,
            'postal_code' => $zip,
            'phone' => $phone,
            'fax' => $fax,
            'enumeration_date' => '2006-08-15',
            'last_update_date' => '2023-01-10',
            'source_file' => 'test_bulk.csv',
            'source_row_hash' => hash('sha256', $npi . $orgName . $street),
        ]);
    }

    /**
     * Test 23: In bulk mode, ZERO live HTTP requests are made (proven by Http::preventStrayRequests).
     */
    public function test_bulk_mode_makes_zero_live_http_requests(): void
    {
        config(['nppes.source' => 'bulk']);

        $this->seedLocalLocation(
            '1649379017',
            'CVS PHARMACY INC',
            '191 N Main St',
            'Attleboro',
            'MA',
            '02703',
            '5082220880',
            '+15082220885'
        );

        $pharmacy = Pharmacy::create([
            'name' => 'CVS Pharmacy',
            'street_address' => '191 N Main St',
            'city' => 'Attleboro',
            'state' => 'MA',
            'postal_code' => '02703',
            'phone' => '(508) 222-0880',
        ]);

        /** @var HealthcareEntityFaxEnrichmentService $service */
        $service = $this->app->make(HealthcareEntityFaxEnrichmentService::class);
        $res = $service->enrichEntity($pharmacy, false);

        $this->assertSame('updated', $res['action']);
        $this->assertSame('found', $res['status']);
        $this->assertSame('+15082220885', $res['fax']);
        $this->assertSame('1649379017', $res['npi']);

        // Zero HTTP requests sent
        Http::assertNothingSent();
    }

    /**
     * Test 24: Laboratory strong match loaded from local bulk directory.
     */
    public function test_bulk_laboratory_strong_match_from_local_directory(): void
    {
        config(['nppes.source' => 'bulk']);

        $this->seedLocalLocation(
            '1982736451',
            'QUEST DIAGNOSTICS LLC',
            '562 Washington St',
            'South Attleboro',
            'MA',
            '02703',
            '5084064626',
            '+15084064630',
            '291U00000X'
        );

        $lab = Laboratory::create([
            'name' => 'Quest Diagnostics',
            'street_address' => '562 Washington St',
            'city' => 'South Attleboro',
            'state' => 'MA',
            'postal_code' => '02703',
            'phone' => '(508) 406-4626',
        ]);

        /** @var HealthcareEntityFaxEnrichmentService $service */
        $service = $this->app->make(HealthcareEntityFaxEnrichmentService::class);
        $res = $service->enrichEntity($lab, false);

        $this->assertSame('updated', $res['action']);
        $this->assertSame('found', $res['status']);
        $this->assertSame('+15084064630', $res['fax']);
        $this->assertSame('1982736451', $res['npi']);

        $lab->refresh();
        $this->assertSame('+15084064630', $lab->fax);
        $this->assertSame('1982736451', $lab->npi);
    }

    /**
     * Test 25: Chain branch collision protection with local directory.
     */
    public function test_bulk_chain_branch_collision_protection(): void
    {
        config(['nppes.source' => 'bulk']);

        // Different CVS branch on a different street in same city/ZIP
        $this->seedLocalLocation(
            '1112223334',
            'CVS PHARMACY INC',
            '8 E Washington St',
            'North Attleborough',
            'MA',
            '02760',
            '5086951234',
            '+15086951235'
        );

        $pharmacy = Pharmacy::create([
            'name' => 'CVS Pharmacy',
            'street_address' => '1205 S Washington St',
            'city' => 'North Attleborough',
            'state' => 'MA',
            'postal_code' => '02760',
        ]);

        /** @var HealthcareEntityFaxEnrichmentService $service */
        $service = $this->app->make(HealthcareEntityFaxEnrichmentService::class);
        $res = $service->enrichEntity($pharmacy, false);

        // Street address mismatch on chain must prevent match
        $this->assertSame('not_found', $res['status']);
        $this->assertLessThan(80, $res['match_score']);
        $this->assertNull($pharmacy->refresh()->fax);
    }

    /**
     * Test 26: no_fax behavior with local bulk directory.
     */
    public function test_bulk_no_fax_behavior_saves_npi_without_fax(): void
    {
        config(['nppes.source' => 'bulk']);

        // Seed pharmacy with valid NPI but NULL fax
        $this->seedLocalLocation(
            '1555666777',
            'NO FAX PHARMACY',
            '25 MARKET ST',
            'ATTLEBORO',
            'MA',
            '02703',
            '5082221111',
            null
        );

        $pharmacy = Pharmacy::create([
            'name' => 'No Fax Pharmacy',
            'street_address' => '25 Market St',
            'city' => 'Attleboro',
            'state' => 'MA',
            'postal_code' => '02703',
            'phone' => '5082221111',
        ]);

        /** @var HealthcareEntityFaxEnrichmentService $service */
        $service = $this->app->make(HealthcareEntityFaxEnrichmentService::class);
        $res = $service->enrichEntity($pharmacy, false);

        $this->assertSame('no_fax', $res['status']);
        $this->assertSame('1555666777', $res['npi']);
        $this->assertNull($res['fax']);

        $pharmacy->refresh();
        $this->assertSame('1555666777', $pharmacy->npi);
        $this->assertNull($pharmacy->fax);
        $this->assertSame('no_fax', $pharmacy->fax_lookup_status);
    }

    /**
     * Test 27: Empty local directory returns clear error and does not modify entity status.
     */
    public function test_bulk_empty_directory_returns_clear_error_and_leaves_entity_unmodified(): void
    {
        config(['nppes.source' => 'bulk']);
        // Do NOT seed local directory (it is empty)

        $pharmacy = Pharmacy::create([
            'name' => 'Unchecked Pharmacy',
            'street_address' => '100 Main St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02111',
            'fax_lookup_status' => null,
        ]);

        /** @var HealthcareEntityFaxEnrichmentService $service */
        $service = $this->app->make(HealthcareEntityFaxEnrichmentService::class);
        $res = $service->enrichEntity($pharmacy, false);

        $this->assertSame('failed', $res['action']);
        $this->assertStringContainsString('NPPES local bulk directory is empty', $res['notes']);

        // Database row must NOT be modified
        $pharmacy->refresh();
        $this->assertNull($pharmacy->fax_lookup_status);
        $this->assertNull($pharmacy->fax_last_checked_at);
    }

    /**
     * Test 28: Dry-run with local bulk directory performs zero DB writes.
     */
    public function test_bulk_artisan_dry_run_makes_no_db_changes(): void
    {
        config(['nppes.source' => 'bulk']);

        $this->seedLocalLocation(
            '1649379017',
            'CVS PHARMACY INC',
            '191 N Main St',
            'Attleboro',
            'MA',
            '02703',
            '5082220880',
            '+15082220885'
        );

        Pharmacy::create([
            'name' => 'CVS Pharmacy',
            'street_address' => '191 N Main St',
            'city' => 'Attleboro',
            'state' => 'MA',
            'postal_code' => '02703',
            'phone' => '(508) 222-0880',
        ]);

        $this->artisan('pharmacies:enrich-fax', [
            '--city' => 'Attleboro',
            '--state' => 'MA',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Pharmacy Fax & NPI Enrichment — DRY RUN')
            ->expectsOutputToContain('CMS NPPES Bulk Dataset (Local Directory)')
            ->expectsOutputToContain('DRY RUN COMPLETE: NO DATABASE CHANGES WERE MADE')
            ->assertExitCode(0);

        $this->assertNull(Pharmacy::first()->fax);
    }
}

