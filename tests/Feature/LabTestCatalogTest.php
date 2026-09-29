<?php

namespace Tests\Feature;

use App\Models\LabTest;
use Database\Seeders\LabTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabTestCatalogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the LabTestSeeder runs cleanly and populates the expected catalog size.
     */
    public function test_seeder_populates_lab_tests_catalog_within_range(): void
    {
        $this->seed(LabTestSeeder::class);

        $count = LabTest::count();

        // Must be in the 280-320 range (specifically 300 curated tests)
        $this->assertGreaterThanOrEqual(280, $count, 'Catalog count is below 280');
        $this->assertLessThanOrEqual(320, $count, 'Catalog count is above 320');
        $this->assertSame(300, $count, 'Catalog count does not match the exact curated total of 300');
    }

    /**
     * Test that the seeder is idempotent and does not create duplicate rows on re-run.
     */
    public function test_seeder_is_idempotent(): void
    {
        // First run
        $this->seed(LabTestSeeder::class);
        $firstCount = LabTest::count();

        // Second run
        $this->seed(LabTestSeeder::class);
        $secondCount = LabTest::count();

        $this->assertSame($firstCount, $secondCount, 'Re-running the seeder modified the total row count');
        $this->assertSame(300, $secondCount);
    }

    /**
     * Test that there are no duplicate test names within the same category.
     */
    public function test_no_duplicate_tests_within_categories(): void
    {
        $this->seed(LabTestSeeder::class);

        $duplicates = LabTest::select('name', 'category')
            ->groupBy('name', 'category')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $this->assertEmpty($duplicates, 'Found duplicate test names within the same category: ' . json_encode($duplicates));
    }

    /**
     * Test that all essential core clinical lab tests exist.
     */
    public function test_core_essential_lab_tests_exist(): void
    {
        $this->seed(LabTestSeeder::class);

        $coreTests = [
            'Complete Blood Count',
            'Basic Metabolic Panel',
            'Comprehensive Metabolic Panel',
            'Lipid Panel',
            'Thyroid-Stimulating Hormone',
            'Hemoglobin A1c',
            'Urinalysis, Complete with Microscopy',
            '25-Hydroxy Vitamin D',
            'Ferritin',
            'Troponin I, High Sensitivity',
            'Prothrombin Time and INR',
            'Activated Partial Thromboplastin Time',
            'SARS-CoV-2 (COVID-19) RT-PCR',
            'Streptococcus Group A Rapid Antigen',
            'Urine Culture and Colony Count',
            'Prostate-Specific Antigen, Total',
            'Potassium, Serum',
            'Sodium, Serum',
            'Creatinine, Serum',
            'Alanine Aminotransferase',
            'Aspartate Aminotransferase',
        ];

        foreach ($coreTests as $testName) {
            $this->assertTrue(
                LabTest::where('name', $testName)->exists(),
                "Essential core test [{$testName}] is missing from catalog."
            );
        }
    }

    /**
     * Test that short names and search aliases are populated for high-frequency tests.
     */
    public function test_short_names_and_aliases_populated_for_common_tests(): void
    {
        $this->seed(LabTestSeeder::class);

        $expectedShortNames = [
            'Complete Blood Count' => 'CBC',
            'Basic Metabolic Panel' => 'BMP',
            'Comprehensive Metabolic Panel' => 'CMP',
            'Lipid Panel' => 'Lipid Panel',
            'Thyroid-Stimulating Hormone' => 'TSH',
            'Hemoglobin A1c' => 'HbA1c',
            'C-Reactive Protein, Standard Quantitative' => 'CRP',
            'Erythrocyte Sedimentation Rate' => 'ESR',
            'Prothrombin Time and INR' => 'PT / INR',
            'Prostate-Specific Antigen, Total' => 'PSA',
            'Hepatic Function Panel' => 'LFT',
        ];

        foreach ($expectedShortNames as $fullName => $expectedShort) {
            $test = LabTest::where('name', $fullName)->first();
            $this->assertNotNull($test, "Test [{$fullName}] not found");
            $this->assertSame($expectedShort, $test->short_name, "Short name for [{$fullName}] mismatch");
        }
    }

    /**
     * Test that multi-analyte panels have is_panel = true, and single analytes have is_panel = false.
     */
    public function test_panel_flag_integrity(): void
    {
        $this->seed(LabTestSeeder::class);

        $panels = [
            'Complete Blood Count',
            'Complete Blood Count with Automated Differential',
            'Basic Metabolic Panel',
            'Comprehensive Metabolic Panel',
            'Lipid Panel',
            'Hepatic Function Panel',
            'Renal Function Panel',
            'Acute Hepatitis Panel',
            'Electrolyte Panel',
        ];

        foreach ($panels as $panelName) {
            $test = LabTest::where('name', $panelName)->first();
            $this->assertNotNull($test, "Panel [{$panelName}] not found");
            $this->assertTrue($test->is_panel, "Panel [{$panelName}] should have is_panel = true");
        }

        $singles = [
            'Glucose, Fasting',
            'Potassium, Serum',
            'Sodium, Serum',
            'Troponin I, High Sensitivity',
            'Ferritin',
            'Thyroid-Stimulating Hormone',
        ];

        foreach ($singles as $singleName) {
            $test = LabTest::where('name', $singleName)->first();
            $this->assertNotNull($test, "Single analyte [{$singleName}] not found");
            $this->assertFalse($test->is_panel, "Single analyte [{$singleName}] should have is_panel = false");
        }
    }

    /**
     * Test that all seeded lab tests are active by default.
     */
    public function test_all_seeded_tests_are_active_by_default(): void
    {
        $this->seed(LabTestSeeder::class);

        $inactiveCount = LabTest::where('is_active', false)->count();
        $this->assertSame(0, $inactiveCount, 'All seeded catalog tests should be active by default');
    }

    /**
     * Test that all standard clinical categories are represented with valid test counts.
     */
    public function test_clinical_categories_are_represented(): void
    {
        $this->seed(LabTestSeeder::class);

        $expectedCategories = [
            'Hematology',
            'Chemistry',
            'Liver',
            'Kidney',
            'Endocrinology',
            'Thyroid',
            'Diabetes',
            'Cardiovascular',
            'Coagulation',
            'Immunology',
            'Infectious Disease',
            'Microbiology',
            'Urine & Stool',
            'Vitamins & Minerals',
            'Tumor Markers',
            'Reproductive',
            'Toxicology',
            'Other',
        ];

        foreach ($expectedCategories as $category) {
            $count = LabTest::where('category', $category)->count();
            $this->assertGreaterThan(0, $count, "Category [{$category}] has no tests populated");
        }
    }

    /**
     * Test that running the seeder does not delete or overwrite custom manually created tests.
     */
    public function test_seeder_preserves_custom_manually_created_tests(): void
    {
        // 1. Manually create a custom doctor clinic test
        $customTest = LabTest::create([
            'name' => 'Custom Clinic In-House Rapid Flu',
            'short_name' => 'Clinic Flu',
            'category' => 'Microbiology',
            'description' => 'Custom in-house rapid influenza test developed for clinic use',
            'specimen' => 'Nasal Swab',
            'is_panel' => false,
            'is_active' => true,
            'sort_order' => 999,
        ]);

        $this->assertSame(1, LabTest::count());

        // 2. Run seeder
        $this->seed(LabTestSeeder::class);

        // 3. Verify total is 301 and custom test is intact
        $this->assertSame(301, LabTest::count());
        $this->assertDatabaseHas('lab_tests', [
            'id' => $customTest->id,
            'name' => 'Custom Clinic In-House Rapid Flu',
            'short_name' => 'Clinic Flu',
        ]);
    }

    /**
     * Test search capability by name, short_name, and category.
     */
    public function test_catalog_is_searchable_by_name_and_short_name(): void
    {
        $this->seed(LabTestSeeder::class);

        // Search by short name acronym
        $resultsForCbc = LabTest::where('name', 'like', '%CBC%')
            ->orWhere('short_name', 'like', '%CBC%')
            ->get();

        $this->assertNotEmpty($resultsForCbc);
        $this->assertTrue($resultsForCbc->contains('name', 'Complete Blood Count'));

        // Search by analyte term
        $resultsForA1c = LabTest::where('name', 'like', '%A1c%')
            ->orWhere('short_name', 'like', '%A1c%')
            ->get();

        $this->assertNotEmpty($resultsForA1c);
        $this->assertTrue($resultsForA1c->contains('name', 'Hemoglobin A1c'));
    }
}
