<?php

namespace Tests\Feature;

use App\Models\NppesHealthcareLocation;
use App\Models\NppesImport;
use App\Services\Nppes\NppesBulkImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class NppesBulkImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Critical Database Safety Assertion: Ensure tests NEVER run against MySQL or non-isolated DB
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
    }

    /**
     * Helper to create a temporary CSV fixture file.
     *
     * @param array<int, array<int, string>> $rows
     */
    protected function createTempCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'nppes_test_') . '.csv';
        $fp = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);

        return $path;
    }

    /**
     * Standard CMS NPPES Version 2 Headers.
     */
    protected function standardHeaders(): array
    {
        return [
            'NPI',
            'Entity Type Code',
            'Provider Organization Name (Legal Business Name)',
            'Provider Other Organization Name',
            'Provider First Line Business Practice Location Address',
            'Provider Second Line Business Practice Location Address',
            'Provider Business Practice Location Address City Name',
            'Provider Business Practice Location Address State Name',
            'Provider Business Practice Location Address Postal Code',
            'Provider Business Practice Location Address Telephone Number',
            'Provider Business Practice Location Address Fax Number',
            'Provider First Line Business Mailing Address',
            'Provider Business Mailing Address Fax Number',
            'Healthcare Provider Taxonomy Code_1',
            'Healthcare Provider Taxonomy Code_2',
            'Provider Enumeration Date',
            'Last Update Date',
        ];
    }

    /**
     * Test 1: Header mapping works and parses real NPPES V2 header names.
     */
    public function test_header_mapping_works_with_v2_headers(): void
    {
        /** @var NppesBulkImportService $service */
        $service = $this->app->make(NppesBulkImportService::class);
        $headers = $this->standardHeaders();

        $map = $service->identifyHeaders($headers);

        $this->assertSame(0, $map['npi']);
        $this->assertSame(1, $map['entity_type']);
        $this->assertSame(2, $map['org_name']);
        $this->assertSame(4, $map['practice_address_1']);
        $this->assertCount(2, $map['taxonomy_indices']);
    }

    /**
     * Test 2 & 3: Organization rows imported, individual practitioners skipped.
     */
    public function test_organization_rows_imported_and_individuals_skipped(): void
    {
        $csv = $this->createTempCsv([
            $this->standardHeaders(),
            // Org row (Entity Type = 2) -> Should import
            ['1234567890', '2', 'CVS PHARMACY #101', '', '100 MAIN ST', '', 'BOSTON', 'MA', '02111', '6175551000', '6175551001', '1 CVS DR', '4015559999', '3336C0003X', '', '2010-01-01', '2023-01-01'],
            // Individual row (Entity Type = 1) -> Must skip
            ['9876543210', '1', 'DR JOHN DOE', '', '200 BEACON ST', '', 'BOSTON', 'MA', '02116', '6175552000', '6175552001', '200 BEACON ST', '6175552001', '207Q00000X', '', '2012-05-01', '2022-01-01'],
        ]);

        /** @var NppesBulkImportService $service */
        $service = $this->app->make(NppesBulkImportService::class);
        $stats = $service->import($csv, ['type' => 'all']);

        $this->assertSame(2, $stats['rows_scanned']);
        $this->assertSame(1, $stats['organizations']);
        $this->assertSame(1, $stats['imported']);
        $this->assertSame(1, NppesHealthcareLocation::count());
        $this->assertDatabaseHas('nppes_healthcare_locations', [
            'npi' => '1234567890',
            'organization_name' => 'CVS PHARMACY #101',
        ]);
        $this->assertDatabaseMissing('nppes_healthcare_locations', [
            'npi' => '9876543210',
        ]);

        unlink($csv);
    }

    /**
     * Test 4 & 5: Pharmacy and laboratory taxonomies imported.
     */
    public function test_pharmacy_and_laboratory_taxonomies_imported(): void
    {
        $csv = $this->createTempCsv([
            $this->standardHeaders(),
            // Pharmacy taxonomy 3336C0003X
            ['1111111111', '2', 'WALGREENS #500', '', '50 PARK AVE', '', 'WORCESTER', 'MA', '01609', '5085551234', '5085551235', '', '', '3336C0003X', '', '2008-01-01', '2023-01-01'],
            // Laboratory taxonomy 291U00000X
            ['2222222222', '2', 'QUEST DIAGNOSTICS INC', '', '400 LINCOLN ST', '', 'WORCESTER', 'MA', '01605', '5085559876', '5085559877', '', '', '291U00000X', '', '2009-02-01', '2023-02-01'],
        ]);

        /** @var NppesBulkImportService $service */
        $service = $this->app->make(NppesBulkImportService::class);
        $stats = $service->import($csv, ['type' => 'all']);

        $this->assertSame(1, $stats['pharmacy_rows']);
        $this->assertSame(1, $stats['laboratory_rows']);
        $this->assertSame(2, $stats['imported']);

        unlink($csv);
    }

    /**
     * Test 6: Irrelevant taxonomies (e.g. dental, veterinary, chiropractic) skipped.
     */
    public function test_irrelevant_taxonomies_skipped(): void
    {
        $csv = $this->createTempCsv([
            $this->standardHeaders(),
            // Dental Laboratory 292200000X -> explicitly excluded
            ['3333333333', '2', 'ACME DENTAL LAB', '', '10 DENTAL RD', '', 'BOSTON', 'MA', '02115', '6175553333', '6175553334', '', '', '292200000X', '', '2015-01-01', '2023-01-01'],
            // Chiropractic Facility
            ['4444444444', '2', 'SPINE CARE CENTER', '', '20 BACK ST', '', 'BOSTON', 'MA', '02115', '6175554444', '6175554445', '', '', '111N00000X', '', '2016-01-01', '2023-01-01'],
        ]);

        /** @var NppesBulkImportService $service */
        $service = $this->app->make(NppesBulkImportService::class);
        $stats = $service->import($csv, ['type' => 'all']);

        $this->assertSame(0, $stats['pharmacy_rows']);
        $this->assertSame(0, $stats['laboratory_rows']);
        $this->assertSame(0, $stats['imported']);
        $this->assertSame(0, NppesHealthcareLocation::count());

        unlink($csv);
    }

    /**
     * Test 7 & 8: Leading-zero ZIP code preserved and ZIP+4 preserved.
     */
    public function test_leading_zero_zip_and_zip4_preserved(): void
    {
        $csv = $this->createTempCsv([
            $this->standardHeaders(),
            // Leading zero ZIP (e.g. 02703) and 9-digit zip
            ['5555555555', '2', 'NORTH MAIN PHARMACY', '', '191 N MAIN ST', '', 'ATTLEBORO', 'MA', '027031234', '5082220880', '5082220885', '', '', '3336C0003X', '', '2006-08-15', '2023-01-10'],
        ]);

        /** @var NppesBulkImportService $service */
        $service = $this->app->make(NppesBulkImportService::class);
        $service->import($csv);

        $loc = NppesHealthcareLocation::first();
        $this->assertNotNull($loc);
        $this->assertSame('02703-1234', $loc->postal_code);

        unlink($csv);
    }

    /**
     * Test 9 & 10: Phone and Fax normalized to E.164.
     */
    public function test_phone_and_fax_normalized_to_e164(): void
    {
        $csv = $this->createTempCsv([
            $this->standardHeaders(),
            ['6666666666', '2', 'PROVIDENCE LAB', '', '758 EDDY ST', '', 'PROVIDENCE', 'RI', '02903', '(401) 555-1212', '401-555-9898', '', '', '291U00000X', '', '2007-01-01', '2023-01-01'],
        ]);

        /** @var NppesBulkImportService $service */
        $service = $this->app->make(NppesBulkImportService::class);
        $service->import($csv);

        $loc = NppesHealthcareLocation::first();
        $this->assertSame('+14015551212', $loc->phone);
        $this->assertSame('+14015559898', $loc->fax);

        unlink($csv);
    }

    /**
     * Test 11: NPI validated (must be 10 digits).
     */
    public function test_invalid_npis_are_skipped(): void
    {
        $csv = $this->createTempCsv([
            $this->standardHeaders(),
            ['INVALID_NPI', '2', 'BAD NPI PHARMACY', '', '100 MAIN ST', '', 'BOSTON', 'MA', '02111', '6175550000', '6175550001', '', '', '3336C0003X', '', '2010-01-01', '2023-01-01'],
            ['12345', '2', 'SHORT NPI PHARMACY', '', '100 MAIN ST', '', 'BOSTON', 'MA', '02111', '6175550000', '6175550001', '', '', '3336C0003X', '', '2010-01-01', '2023-01-01'],
        ]);

        /** @var NppesBulkImportService $service */
        $service = $this->app->make(NppesBulkImportService::class);
        $stats = $service->import($csv);

        $this->assertSame(2, $stats['skipped_invalid']);
        $this->assertSame(0, NppesHealthcareLocation::count());

        unlink($csv);
    }

    /**
     * Test 12: Mailing fax is NOT used as practice fax.
     */
    public function test_mailing_fax_is_never_used_as_practice_fax(): void
    {
        $csv = $this->createTempCsv([
            $this->standardHeaders(),
            // Practice fax empty (''), mailing fax filled ('4015559999')
            ['7777777777', '2', 'CVS BRANCH #88', '', '10 OAK ST', '', 'BOSTON', 'MA', '02111', '6175551111', '', '1 CVS DR', '4015559999', '3336C0003X', '', '2010-01-01', '2023-01-01'],
        ]);

        /** @var NppesBulkImportService $service */
        $service = $this->app->make(NppesBulkImportService::class);
        $service->import($csv);

        $loc = NppesHealthcareLocation::first();
        $this->assertNotNull($loc);
        $this->assertNull($loc->fax, 'Practice location fax must remain null if practice fax was empty in bulk row');

        unlink($csv);
    }

    /**
     * Test 13 & 14: Duplicate source rows do not duplicate local directory and import is idempotent.
     */
    public function test_import_is_strictly_idempotent(): void
    {
        $csv = $this->createTempCsv([
            $this->standardHeaders(),
            ['8888888888', '2', 'WALGREENS #10', '', '100 ELM ST', '', 'BOSTON', 'MA', '02111', '6175558888', '6175558889', '', '', '3336C0003X', '', '2010-01-01', '2023-01-01'],
            ['8888888888', '2', 'WALGREENS #10', '', '100 ELM ST', '', 'BOSTON', 'MA', '02111', '6175558888', '6175558889', '', '', '3336C0003X', '', '2010-01-01', '2023-01-01'],
        ]);

        /** @var NppesBulkImportService $service */
        $service = $this->app->make(NppesBulkImportService::class);

        // First run
        $service->import($csv);
        $this->assertSame(1, NppesHealthcareLocation::count());

        // Second run with --force
        $service->import($csv, ['force' => true]);
        $this->assertSame(1, NppesHealthcareLocation::count(), 'Running twice must never duplicate records');

        unlink($csv);
    }

    /**
     * Test 15: Source row update safely updates existing directory record without truncation.
     */
    public function test_source_row_update_safely_updates_record(): void
    {
        $csv1 = $this->createTempCsv([
            $this->standardHeaders(),
            ['9999999999', '2', 'OLD LAB NAME', '', '50 BROADWAY', '', 'CAMBRIDGE', 'MA', '02142', '6175559999', '', '', '', '291U00000X', '', '2010-01-01', '2020-01-01'],
        ]);

        /** @var NppesBulkImportService $service */
        $service = $this->app->make(NppesBulkImportService::class);
        $service->import($csv1);
        unlink($csv1);

        $this->assertSame('OLD LAB NAME', NppesHealthcareLocation::first()->organization_name);
        $this->assertNull(NppesHealthcareLocation::first()->fax);

        // Updated record with new name and new fax
        $csv2 = $this->createTempCsv([
            $this->standardHeaders(),
            ['9999999999', '2', 'NEW LAB NAME INC', '', '50 BROADWAY', '', 'CAMBRIDGE', 'MA', '02142', '6175559999', '6175559990', '', '', '291U00000X', '', '2010-01-01', '2024-01-01'],
        ]);

        $service->import($csv2, ['force' => true]);
        unlink($csv2);

        $this->assertSame(1, NppesHealthcareLocation::count());
        $loc = NppesHealthcareLocation::first();
        $this->assertSame('NEW LAB NAME INC', $loc->organization_name);
        $this->assertSame('+16175559990', $loc->fax);
    }

    /**
     * Test 16: Dry-run makes zero DB writes.
     */
    public function test_dry_run_makes_zero_db_writes(): void
    {
        $csv = $this->createTempCsv([
            $this->standardHeaders(),
            ['1010101010', '2', 'DRY RUN PHARMACY', '', '1 TEST WAY', '', 'BOSTON', 'MA', '02111', '6175551010', '6175551011', '', '', '3336C0003X', '', '2015-01-01', '2023-01-01'],
        ]);

        /** @var NppesBulkImportService $service */
        $service = $this->app->make(NppesBulkImportService::class);
        $stats = $service->import($csv, ['dry_run' => true]);

        $this->assertTrue($stats['dry_run']);
        $this->assertSame(1, $stats['would_insert']);
        $this->assertSame(0, $stats['imported']);
        $this->assertSame(0, NppesHealthcareLocation::count());
        $this->assertSame(0, NppesImport::count());

        unlink($csv);
    }

    /**
     * Test 17: Missing required headers fails safely.
     */
    public function test_missing_required_headers_throws_exception(): void
    {
        $csv = $this->createTempCsv([
            ['Col1', 'Col2', 'Col3'],
            ['A', 'B', 'C'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required NPPES headers');

        try {
            /** @var NppesBulkImportService $service */
            $service = $this->app->make(NppesBulkImportService::class);
            $service->import($csv);
        } finally {
            unlink($csv);
        }
    }

    /**
     * Test 18: File hash duplicate detection requires --force.
     */
    public function test_duplicate_file_hash_prevents_accidental_reimport(): void
    {
        $csv = $this->createTempCsv([
            $this->standardHeaders(),
            ['2020202020', '2', 'HASH PHARMACY', '', '1 HASH RD', '', 'BOSTON', 'MA', '02111', '6175552020', '6175552021', '', '', '3336C0003X', '', '2015-01-01', '2023-01-01'],
        ]);

        /** @var NppesBulkImportService $service */
        $service = $this->app->make(NppesBulkImportService::class);
        $service->import($csv);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This NPPES source file has already been imported');

        try {
            // Running again without --force
            $service->import($csv);
        } finally {
            unlink($csv);
        }
    }

    /**
     * Test 19: Artisan command executes dry run cleanly.
     */
    public function test_artisan_import_command_dry_run(): void
    {
        $csv = $this->createTempCsv([
            $this->standardHeaders(),
            ['3030303030', '2', 'CLI PHARMACY', '', '5 MAIN ST', '', 'BOSTON', 'MA', '02111', '6175553030', '6175553031', '', '', '3336C0003X', '', '2015-01-01', '2023-01-01'],
        ]);

        $this->artisan('nppes:import', [
            'file' => $csv,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('NPPES Bulk Dataset Ingestion — DRY RUN')
            ->expectsOutputToContain('DRY RUN COMPLETE: NO DATABASE CHANGES WERE MADE')
            ->assertExitCode(0);

        $this->assertSame(0, NppesHealthcareLocation::count());

        unlink($csv);
    }
}
