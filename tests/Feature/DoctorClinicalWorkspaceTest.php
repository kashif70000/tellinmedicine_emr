<?php

namespace Tests\Feature;

use App\Mail\LabOrderCreatedMail;
use App\Mail\PrescriptionCreatedMail;
use App\Models\Doctor;
use App\Models\Laboratory;
use App\Models\LabOrder;
use App\Models\LabTest;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\PatientLaboratory;
use App\Models\PatientPharmacy;
use App\Models\Pharmacy;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DoctorClinicalWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function createDoctor(string $name = 'Dr. Alexander Fleming', string $email = 'doctor@preclinic.com'): array
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => 'doctor',
            'is_active' => true,
        ]);

        $doctor = Doctor::create([
            'user_id' => $user->id,
            'specialization' => 'Cardiology',
            'experience' => 10,
            'fees' => 150.00,
            'clinic_name' => 'Heart Clinic',
            'is_verified' => true,
        ]);

        return [$user, $doctor];
    }

    protected function createPatient(string $name = 'John Doe', string $email = 'john@example.com'): array
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => 'patient',
            'is_active' => true,
        ]);

        $patient = Patient::create([
            'user_id' => $user->id,
            'age' => 32,
            'gender' => 'male',
            'blood_group' => 'O+',
            'phone' => '617-555-0100',
            'is_verified' => true,
            'is_payment_method_verified' => true,
        ]);

        return [$user, $patient];
    }

    protected function createPharmacy(string $name = 'Walgreens Pharmacy', string $city = 'Boston'): Pharmacy
    {
        return Pharmacy::create([
            'name' => $name,
            'street_address' => '123 Main St',
            'city' => $city,
            'state' => 'MA',
            'postal_code' => '02115',
            'phone' => '617-555-1111',
        ]);
    }

    protected function createLaboratory(string $name = 'Quest Diagnostics', string $city = 'Boston'): Laboratory
    {
        return Laboratory::create([
            'name' => $name,
            'category' => 'Medical Diagnostic Laboratory',
            'street_address' => '456 Elm St',
            'city' => $city,
            'state' => 'MA',
            'postal_code' => '02115',
            'phone' => '617-555-2222',
        ]);
    }

    protected function createMedicine(
        string $name = 'amlodipine 5 MG Oral Tablet',
        string $generic = 'amlodipine',
        string $strength = '5 MG',
        string $dosageForm = 'Oral Tablet',
        string $route = 'ORAL',
        string $tty = 'SCD',
        ?string $brandName = null,
        bool $active = true
    ): Medicine {
        return Medicine::create([
            'rxcui' => 'RX' . mt_rand(100000, 999999),
            'tty' => $tty,
            'name' => $name,
            'generic_name' => $generic,
            'brand_name' => $brandName,
            'strength' => $strength,
            'dosage_form' => $dosageForm,
            'route' => $route,
            'active' => $active,
        ]);
    }

    protected function createLabTest(string $name = 'Complete Blood Count', string $shortName = 'CBC', string $category = 'Hematology'): LabTest
    {
        return LabTest::create([
            'name' => $name,
            'short_name' => $shortName,
            'category' => $category,
            'loinc_code' => '58410-2',
            'specimen' => 'Whole Blood',
            'is_panel' => true,
            'is_active' => true,
            'sort_order' => 10,
        ]);
    }

    // =========================================================================
    // AUTHORIZATION & PREFERRED DESTINATIONS TESTS
    // =========================================================================

    public function test_unauthenticated_user_cannot_access_clinical_workspace_endpoints(): void
    {
        [$patientUser, $patient] = $this->createPatient();

        $response = $this->getJson(route('doctor.clinical.preferred-destinations', $patient->getRouteKey()));
        $response->assertStatus(401);
    }

    public function test_patient_cannot_access_clinical_workspace_endpoints(): void
    {
        [$patientUser, $patient] = $this->createPatient();

        $response = $this->actingAs($patientUser)
            ->getJson(route('doctor.clinical.preferred-destinations', $patient->getRouteKey()));
        $response->assertStatus(403);
    }

    public function test_doctor_can_fetch_patient_preferred_destinations(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient();
        $pharmacy = $this->createPharmacy('CVS Pharmacy');
        $laboratory = $this->createLaboratory('Labcorp');

        // Set preferences
        PatientPharmacy::create(['patient_id' => $patient->id, 'pharmacy_id' => $pharmacy->id, 'is_preferred' => true]);
        PatientLaboratory::create(['patient_id' => $patient->id, 'laboratory_id' => $laboratory->id, 'is_preferred' => true]);

        $response = $this->actingAs($doctorUser)
            ->getJson(route('doctor.clinical.preferred-destinations', $patient->getRouteKey()));

        $response->assertOk()
            ->assertJsonPath('preferred_pharmacy.id', $pharmacy->id)
            ->assertJsonPath('preferred_pharmacy.name', 'CVS Pharmacy')
            ->assertJsonPath('preferred_laboratory.id', $laboratory->id)
            ->assertJsonPath('preferred_laboratory.name', 'Labcorp');
    }

    // =========================================================================
    // GROUPED MEDICINE SEARCH & RXNORM VARIANT TESTS
    // =========================================================================

    public function test_only_active_medicines_are_returned_in_search(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient();

        $this->createMedicine('amlodipine 5 MG Oral Tablet', 'amlodipine', '5 MG', 'Oral Tablet', 'ORAL', 'SCD', null, true);
        $this->createMedicine('amlodipine 10 MG Oral Tablet (Inactive)', 'amlodipine', '10 MG', 'Oral Tablet', 'ORAL', 'SCD', null, false);

        $response = $this->actingAs($doctorUser)
            ->getJson(route('doctor.clinical.medicines.search', ['patient' => $patient->getRouteKey(), 'q' => 'amlo']));

        $response->assertOk();
        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertCount(1, $results[0]['variants']);
        $this->assertEquals('amlodipine 5 MG Oral Tablet', $results[0]['variants'][0]['name']);
    }

    public function test_generic_name_and_brand_name_search_work(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient();

        $this->createMedicine('amlodipine 5 MG Oral Tablet [Norvasc]', 'amlodipine', '5 MG', 'Oral Tablet', 'ORAL', 'SBD', 'Norvasc');

        // Search by brand name
        $responseBrand = $this->actingAs($doctorUser)
            ->getJson(route('doctor.clinical.medicines.search', ['patient' => $patient->getRouteKey(), 'q' => 'Norvasc']));
        $responseBrand->assertOk();
        $this->assertNotEmpty($responseBrand->json('results'));

        // Search by generic name
        $responseGeneric = $this->actingAs($doctorUser)
            ->getJson(route('doctor.clinical.medicines.search', ['patient' => $patient->getRouteKey(), 'q' => 'amlodipine']));
        $responseGeneric->assertOk();
        $this->assertNotEmpty($responseGeneric->json('results'));
    }

    public function test_scd_and_sbd_variants_are_selectable_and_raw_ingredients_are_excluded(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient();

        // Prescribable variants
        $this->createMedicine('amlodipine 5 MG Oral Tablet', 'amlodipine', '5 MG', 'Oral Tablet', 'ORAL', 'SCD');
        $this->createMedicine('amlodipine 5 MG Oral Tablet [Norvasc]', 'amlodipine', '5 MG', 'Oral Tablet', 'ORAL', 'SBD', 'Norvasc');

        // Raw non-prescribable concepts (IN, PIN, BN, SCDF)
        $this->createMedicine('Amlodipine Ingredient', 'amlodipine', null, null, null, 'IN');
        $this->createMedicine('Amlodipine Besylate Precise', 'amlodipine', null, null, null, 'PIN');
        $this->createMedicine('Norvasc Brand Concept', null, null, null, null, 'BN', 'Norvasc');
        $this->createMedicine('Amlodipine Oral Tablet Concept', 'amlodipine', null, 'Oral Tablet', null, 'SCDF');

        $response = $this->actingAs($doctorUser)
            ->getJson(route('doctor.clinical.medicines.search', ['patient' => $patient->getRouteKey(), 'q' => 'amlo']));

        $response->assertOk();
        $results = $response->json('results');
        $this->assertCount(1, $results);

        // Check only SCD and SBD are in variants
        $variantNames = array_column($results[0]['variants'], 'name');
        $this->assertContains('amlodipine 5 MG Oral Tablet', $variantNames);
        $this->assertContains('amlodipine 5 MG Oral Tablet [Norvasc]', $variantNames);
        $this->assertNotContains('Amlodipine Ingredient', $variantNames);
        $this->assertNotContains('Amlodipine Besylate Precise', $variantNames);
        $this->assertNotContains('Norvasc Brand Concept', $variantNames);
        $this->assertNotContains('Amlodipine Oral Tablet Concept', $variantNames);
    }

    public function test_amlodipine_returns_grouped_results_with_variants(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient();

        $this->createMedicine('amlodipine 2.5 MG Oral Tablet', 'amlodipine', '2.5 MG', 'Oral Tablet', 'ORAL', 'SCD');
        $this->createMedicine('amlodipine 5 MG Oral Tablet', 'amlodipine', '5 MG', 'Oral Tablet', 'ORAL', 'SCD');
        $this->createMedicine('amlodipine 10 MG Oral Tablet', 'amlodipine', '10 MG', 'Oral Tablet', 'ORAL', 'SCD');

        $response = $this->actingAs($doctorUser)
            ->getJson(route('doctor.clinical.medicines.search', ['patient' => $patient->getRouteKey(), 'q' => 'amlo']));

        $response->assertOk();
        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertEquals('Amlodipine', $results[0]['group_name']);

        $variants = $results[0]['variants'];
        $this->assertCount(3, $variants);
        $this->assertEquals('2.5 mg oral tablet', $variants[0]['display_name']);
        $this->assertEquals('5 mg oral tablet', $variants[1]['display_name']);
        $this->assertEquals('10 mg oral tablet', $variants[2]['display_name']);
    }

    public function test_combination_medications_remain_separate_groups(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient();

        $this->createMedicine('amlodipine 5 MG Oral Tablet', 'amlodipine', '5 MG', 'Oral Tablet', 'ORAL', 'SCD');
        $this->createMedicine('amlodipine 5 MG / atorvastatin 10 MG Oral Tablet', 'amlodipine / atorvastatin', '5 MG / 10 MG', 'Oral Tablet', 'ORAL', 'SCD');
        $this->createMedicine('amlodipine 5 MG / benazepril 10 MG Oral Capsule', 'amlodipine / benazepril', '5 MG / 10 MG', 'Oral Capsule', 'ORAL', 'SCD');
        $this->createMedicine('amlodipine 5 MG / valsartan 160 MG Oral Tablet', 'amlodipine / valsartan', '5 MG / 160 MG', 'Oral Tablet', 'ORAL', 'SCD');

        $response = $this->actingAs($doctorUser)
            ->getJson(route('doctor.clinical.medicines.search', ['patient' => $patient->getRouteKey(), 'q' => 'amlo']));

        $response->assertOk();
        $results = $response->json('results');

        // Four distinct groups must be returned, not combined into plain Amlodipine
        $groupNames = array_column($results, 'group_name');
        $this->assertContains('Amlodipine', $groupNames);
        $this->assertContains('Amlodipine / Atorvastatin', $groupNames);
        $this->assertContains('Amlodipine / Benazepril', $groupNames);
        $this->assertContains('Amlodipine / Valsartan', $groupNames);
    }

    public function test_query_under_two_characters_returns_empty_results(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient();
        $this->createMedicine('amlodipine 5 MG Oral Tablet', 'amlodipine');

        $response = $this->actingAs($doctorUser)
            ->getJson(route('doctor.clinical.medicines.search', ['patient' => $patient->getRouteKey(), 'q' => 'a']));

        $response->assertOk()
            ->assertJsonPath('results', []);
    }

    // =========================================================================
    // PRESCRIPTION CREATION & ENRICHED STORAGE TESTS
    // =========================================================================

    public function test_doctor_can_create_prescription_with_exact_variant_attributes(): void
    {
        Mail::fake();

        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient();
        $pharmacy = $this->createPharmacy('Walgreens');
        $med = $this->createMedicine('amlodipine 5 MG Oral Tablet', 'amlodipine', '5 MG', 'Oral Tablet', 'ORAL', 'SCD');

        $response = $this->actingAs($doctorUser)
            ->postJson(route('doctor.clinical.prescriptions.store', $patient->getRouteKey()), [
                'pharmacy_id' => $pharmacy->id,
                'diagnosis' => 'Essential Hypertension',
                'notes' => 'Take in morning with food',
                'medicines' => [
                    [
                        'medicine_id' => $med->id,
                        'rxcui' => $med->rxcui,
                        'name' => $med->name,
                        'generic_name' => $med->generic_name,
                        'strength' => '5 MG',
                        'dosage_form' => 'Oral Tablet',
                        'route' => 'ORAL',
                        'frequency' => 'Once daily',
                        'dosage' => '5 MG',
                        'timing' => ['Morning'],
                        'intake' => 'After Food',
                        'duration' => '30 days',
                        'quantity' => '30',
                        'unit' => 'Tablet',
                        'refills' => 1,
                        'substitutions_allowed' => true,
                        'directions' => 'Take 1 tablet daily by mouth with food',
                        'pharmacy_instructions' => 'Dispense 30 day supply',
                    ]
                ]
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('prescription.pharmacy', 'Walgreens')
            ->assertJsonPath('prescription.medication_count', 1);

        $rx = Prescription::where('patient_id', $patient->id)->first();
        $this->assertNotNull($rx);
        $this->assertCount(1, $rx->medicines);
        $savedMed = $rx->medicines[0];

        $this->assertEquals($med->id, $savedMed['medicine_id']);
        $this->assertEquals($med->rxcui, $savedMed['rxcui']);
        $this->assertEquals('5 MG', $savedMed['strength']);
        $this->assertEquals('Oral Tablet', $savedMed['dosage_form']);
        $this->assertEquals('ORAL', $savedMed['route']);
        $this->assertEquals('Once daily', $savedMed['frequency']);
        $this->assertEquals('After Food', $savedMed['intake']);
        $this->assertEquals('30', $savedMed['quantity']);
        $this->assertEquals('Tablet', $savedMed['unit']);
        $this->assertEquals(1, $savedMed['refills']);
        $this->assertTrue($savedMed['substitutions_allowed']);

        Mail::assertSent(PrescriptionCreatedMail::class);
    }

    public function test_multi_medication_prescription_stores_all_items_with_single_destination_pharmacy(): void
    {
        Mail::fake();

        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient();
        $pharmacy = $this->createPharmacy('CVS Health');

        $med1 = $this->createMedicine('amlodipine 5 MG Oral Tablet', 'amlodipine', '5 MG', 'Oral Tablet', 'ORAL');
        $med2 = $this->createMedicine('metformin 500 MG Oral Tablet', 'metformin', '500 MG', 'Oral Tablet', 'ORAL');

        $response = $this->actingAs($doctorUser)
            ->postJson(route('doctor.clinical.prescriptions.store', $patient->getRouteKey()), [
                'pharmacy_id' => $pharmacy->id,
                'medicines' => [
                    [
                        'medicine_id' => $med1->id,
                        'name' => $med1->name,
                        'frequency' => 'Once daily',
                        'dosage' => '5 MG',
                        'timing' => ['Morning'],
                        'intake' => 'After Food',
                        'duration' => '30 days',
                        'quantity' => '30',
                        'unit' => 'Tablet',
                        'refills' => 0,
                    ],
                    [
                        'medicine_id' => $med2->id,
                        'name' => $med2->name,
                        'frequency' => 'Twice daily',
                        'dosage' => '500 MG',
                        'timing' => ['Morning', 'Evening'],
                        'intake' => 'With Food',
                        'duration' => '30 days',
                        'quantity' => '60',
                        'unit' => 'Tablet',
                        'refills' => 2,
                    ]
                ]
            ]);

        $response->assertOk()
            ->assertJsonPath('prescription.medication_count', 2);

        $rx = Prescription::where('patient_id', $patient->id)->first();
        $this->assertCount(2, $rx->medicines);
        $this->assertEquals('amlodipine 5 MG Oral Tablet', $rx->medicines[0]['name']);
        $this->assertEquals('metformin 500 MG Oral Tablet', $rx->medicines[1]['name']);
        $this->assertEquals('CVS Health', $rx->pharmacy_name_snapshot);
    }

    public function test_prescription_requires_pharmacy_and_saves_snapshot(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient();
        $pharmacy = $this->createPharmacy('Costco Pharmacy', 'San Francisco');

        // Fails when no pharmacy selected and patient has no preference
        $resNoPharma = $this->actingAs($doctorUser)
            ->postJson(route('doctor.clinical.prescriptions.store', $patient->getRouteKey()), [
                'medicines' => [['name' => 'Aspirin 81mg']]
            ]);
        $resNoPharma->assertStatus(422)
            ->assertJsonValidationErrors(['pharmacy_id']);

        // Succeeds when pharmacy provided and saves address/phone snapshot
        $resSuccess = $this->actingAs($doctorUser)
            ->postJson(route('doctor.clinical.prescriptions.store', $patient->getRouteKey()), [
                'pharmacy_id' => $pharmacy->id,
                'medicines' => [['name' => 'Aspirin 81mg']]
            ]);
        $resSuccess->assertOk();

        $rx = Prescription::where('patient_id', $patient->id)->first();
        $this->assertEquals('Costco Pharmacy', $rx->pharmacy_name_snapshot);
        $this->assertEquals('123 Main St', $rx->pharmacy_address_snapshot);
        $this->assertEquals('San Francisco', $rx->pharmacy_city_snapshot);
    }

    public function test_doctor_can_override_preferred_pharmacy_without_altering_patient_preference(): void
    {
        Mail::fake();

        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient();
        $prefPharmacy = $this->createPharmacy('Patient Preferred CVS');
        $overridePharmacy = $this->createPharmacy('Doctor Selected Walgreens');

        // Set patient preferred pharmacy
        PatientPharmacy::create(['patient_id' => $patient->id, 'pharmacy_id' => $prefPharmacy->id, 'is_preferred' => true]);

        // Doctor chooses override pharmacy
        $response = $this->actingAs($doctorUser)
            ->postJson(route('doctor.clinical.prescriptions.store', $patient->getRouteKey()), [
                'pharmacy_id' => $overridePharmacy->id,
                'medicines' => [['name' => 'Amoxicillin', 'dosage' => '500 mg', 'timing' => ['Morning']]]
            ]);

        $response->assertOk();

        // Check prescription points to override pharmacy
        $this->assertDatabaseHas('prescriptions', [
            'patient_id' => $patient->id,
            'pharmacy_id' => $overridePharmacy->id,
            'pharmacy_name_snapshot' => 'Doctor Selected Walgreens',
        ]);

        // Verify patient preference in DB is STILL the preferred CVS
        $patient->refresh();
        $this->assertEquals($prefPharmacy->id, $patient->preferredPharmacy->id);
    }

    public function test_legacy_prescription_and_enriched_prescription_render_in_report(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient();
        $pharmacy = $this->createPharmacy('Community Pharmacy');

        // Legacy format prescription
        $legacyRx = Prescription::create([
            'reference_number' => 'RX-LEGACY-001',
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'pharmacy_id' => $pharmacy->id,
            'pharmacy_name_snapshot' => 'Community Pharmacy',
            'medicines' => [
                ['name' => 'Legacy Penicillin', 'dosage' => '250 mg', 'timing' => ['Morning'], 'intake' => 'Before Food']
            ]
        ]);

        // Enriched format prescription
        $enrichedRx = Prescription::create([
            'reference_number' => 'RX-ENRICHED-001',
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'pharmacy_id' => $pharmacy->id,
            'pharmacy_name_snapshot' => 'Community Pharmacy',
            'medicines' => [
                [
                    'name' => 'amlodipine 5 MG Oral Tablet',
                    'strength' => '5 MG',
                    'dosage_form' => 'Oral Tablet',
                    'route' => 'ORAL',
                    'frequency' => 'Once daily',
                    'quantity' => '30',
                    'unit' => 'Tablet',
                    'refills' => 2,
                    'directions' => 'Take 1 tablet daily with food',
                ]
            ]
        ]);

        // View both in report
        $this->actingAs($doctorUser)
            ->get(route('prescriptions.show', $legacyRx->id))
            ->assertOk()
            ->assertSee('Legacy Penicillin')
            ->assertSee('250 mg');

        $this->actingAs($doctorUser)
            ->get(route('prescriptions.show', $enrichedRx->id))
            ->assertOk()
            ->assertSee('amlodipine 5 MG Oral Tablet')
            ->assertSee('Once daily')
            ->assertSee('Refills: 2');
    }

    public function test_mail_failure_does_not_rollback_prescription(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        // Patient with no valid email
        $patientUserNoEmail = User::create([
            'name' => 'No Mail User',
            'email' => '',
            'password' => bcrypt('password'),
            'role' => 'patient',
        ]);
        $patientNoEmail = Patient::create([
            'user_id' => $patientUserNoEmail->id,
            'age' => 45,
            'is_verified' => true,
            'is_payment_method_verified' => true,
        ]);
        $pharmacy = $this->createPharmacy();

        $response = $this->actingAs($doctorUser)
            ->postJson(route('doctor.clinical.prescriptions.store', $patientNoEmail->getRouteKey()), [
                'pharmacy_id' => $pharmacy->id,
                'medicines' => [['name' => 'Metformin 500mg']]
            ]);

        $response->assertOk();

        // Prescription remains saved in DB
        $rx = Prescription::where('patient_id', $patientNoEmail->id)->first();
        $this->assertNotNull($rx);
        $this->assertEquals('failed', $rx->email_status);
        $this->assertNotNull($rx->email_error);
    }

    public function test_changing_patient_preferred_pharmacy_later_does_not_change_historical_prescription_snapshot(): void
    {
        Mail::fake();

        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient();
        $originalPharmacy = $this->createPharmacy('Original Pharmacy 123');

        $this->actingAs($doctorUser)
            ->postJson(route('doctor.clinical.prescriptions.store', $patient->getRouteKey()), [
                'pharmacy_id' => $originalPharmacy->id,
                'medicines' => [['name' => 'Aspirin', 'dosage' => '81 mg']]
            ]);

        $rx = Prescription::where('patient_id', $patient->id)->first();
        $this->assertEquals('Original Pharmacy 123', $rx->pharmacy_name_snapshot);

        // Later, patient changes preferred pharmacy or original pharmacy is deleted/modified
        $newPharmacy = $this->createPharmacy('New Preferred Pharmacy 999');
        PatientPharmacy::where('patient_id', $patient->id)->delete();
        PatientPharmacy::create(['patient_id' => $patient->id, 'pharmacy_id' => $newPharmacy->id, 'is_preferred' => true]);

        $rx->refresh();
        $this->assertEquals('Original Pharmacy 123', $rx->destination_pharmacy_name);
        $this->assertEquals('Original Pharmacy 123', $rx->pharmacy_name_snapshot);
    }

    // =========================================================================
    // LAB TEST CATALOG SEARCH & LAB ORDER CREATION TESTS
    // =========================================================================

    public function test_lab_test_search_returns_active_catalog_tests(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient();
        $this->createLabTest('Complete Blood Count', 'CBC', 'Hematology');
        $this->createLabTest('Comprehensive Metabolic Panel', 'CMP', 'Chemistry');

        $response = $this->actingAs($doctorUser)
            ->getJson(route('doctor.clinical.lab-tests.search', ['patient' => $patient->getRouteKey(), 'q' => 'CBC']));

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.short_name', 'CBC');
    }

    public function test_doctor_can_create_multi_test_lab_order(): void
    {
        Mail::fake();

        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient();
        $lab = $this->createLaboratory('Quest Diagnostics');
        $test1 = $this->createLabTest('Thyroid Stimulating Hormone', 'TSH', 'Endocrinology');
        $test2 = $this->createLabTest('Lipid Panel', 'LIPID', 'Cardiology');

        $response = $this->actingAs($doctorUser)
            ->postJson(route('doctor.clinical.lab-orders.store', $patient->getRouteKey()), [
                'laboratory_id' => $lab->id,
                'clinical_notes' => '12-hour fasting required',
                'test_ids' => [$test1->id, $test2->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lab_order.laboratory', 'Quest Diagnostics')
            ->assertJsonPath('lab_order.test_count', 2);

        $this->assertDatabaseHas('lab_orders', [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'laboratory_id' => $lab->id,
            'laboratory_name_snapshot' => 'Quest Diagnostics',
            'clinical_notes' => '12-hour fasting required',
        ]);

        $this->assertDatabaseHas('lab_order_items', [
            'lab_test_id' => $test1->id,
            'test_name_snapshot' => 'Thyroid Stimulating Hormone',
            'short_name_snapshot' => 'TSH',
        ]);

        $this->assertDatabaseHas('lab_order_items', [
            'lab_test_id' => $test2->id,
            'test_name_snapshot' => 'Lipid Panel',
            'short_name_snapshot' => 'LIPID',
        ]);

        Mail::assertSent(LabOrderCreatedMail::class, function ($mail) use ($patientUser) {
            return $mail->hasTo($patientUser->email);
        });
    }

    public function test_duplicate_selected_test_ids_are_deduplicated(): void
    {
        Mail::fake();

        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient();
        $lab = $this->createLaboratory();
        $test = $this->createLabTest('Hemoglobin A1c', 'HbA1c', 'Endocrinology');

        $response = $this->actingAs($doctorUser)
            ->postJson(route('doctor.clinical.lab-orders.store', $patient->getRouteKey()), [
                'laboratory_id' => $lab->id,
                'test_ids' => [$test->id, $test->id, $test->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('lab_order.test_count', 1);

        $order = LabOrder::where('patient_id', $patient->id)->first();
        $this->assertCount(1, $order->items);
    }

    public function test_doctor_can_override_preferred_laboratory_without_altering_patient_preference(): void
    {
        Mail::fake();

        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient();
        $prefLab = $this->createLaboratory('Patient Preferred Labcorp');
        $overrideLab = $this->createLaboratory('Doctor Selected Quest');
        $test = $this->createLabTest('Vitamin D, 25-Hydroxy', 'Vit D', 'Chemistry');

        // Set patient preferred laboratory
        PatientLaboratory::create(['patient_id' => $patient->id, 'laboratory_id' => $prefLab->id, 'is_preferred' => true]);

        // Doctor chooses override lab
        $response = $this->actingAs($doctorUser)
            ->postJson(route('doctor.clinical.lab-orders.store', $patient->getRouteKey()), [
                'laboratory_id' => $overrideLab->id,
                'test_ids' => [$test->id],
            ]);

        $response->assertOk();

        // Check lab order points to override lab
        $this->assertDatabaseHas('lab_orders', [
            'patient_id' => $patient->id,
            'laboratory_id' => $overrideLab->id,
            'laboratory_name_snapshot' => 'Doctor Selected Quest',
        ]);

        // Verify patient preference in DB is STILL the preferred Labcorp
        $patient->refresh();
        $this->assertEquals($prefLab->id, $patient->preferredLaboratory->id);
    }

    public function test_creating_both_prescription_and_lab_order_dispatches_two_separate_mailables(): void
    {
        Mail::fake();

        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient();
        $pharmacy = $this->createPharmacy();
        $lab = $this->createLaboratory();
        $test = $this->createLabTest();

        // 1. Create Prescription
        $this->actingAs($doctorUser)
            ->postJson(route('doctor.clinical.prescriptions.store', $patient->getRouteKey()), [
                'pharmacy_id' => $pharmacy->id,
                'medicines' => [['name' => 'Amoxicillin 500mg']]
            ])
            ->assertOk();

        // 2. Create Lab Order
        $this->actingAs($doctorUser)
            ->postJson(route('doctor.clinical.lab-orders.store', $patient->getRouteKey()), [
                'laboratory_id' => $lab->id,
                'test_ids' => [$test->id]
            ])
            ->assertOk();

        // Verify separate mailables sent
        Mail::assertSent(PrescriptionCreatedMail::class, 1);
        Mail::assertSent(LabOrderCreatedMail::class, 1);
    }

    // =========================================================================
    // CLINICAL HISTORY & REPORT TESTS
    // =========================================================================

    public function test_doctor_can_view_prescription_and_lab_order_history(): void
    {
        Mail::fake();

        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient();
        $pharmacy = $this->createPharmacy();
        $lab = $this->createLaboratory();
        $test = $this->createLabTest();

        // Create prescription
        $this->actingAs($doctorUser)
            ->postJson(route('doctor.clinical.prescriptions.store', $patient->getRouteKey()), [
                'pharmacy_id' => $pharmacy->id,
                'medicines' => [['name' => 'Metformin 500mg']]
            ]);

        // Create lab order
        $this->actingAs($doctorUser)
            ->postJson(route('doctor.clinical.lab-orders.store', $patient->getRouteKey()), [
                'laboratory_id' => $lab->id,
                'test_ids' => [$test->id]
            ]);

        // History listing
        $rxHistory = $this->actingAs($doctorUser)
            ->getJson(route('doctor.clinical.prescriptions.index', $patient->getRouteKey()));
        $rxHistory->assertOk()->assertJsonCount(1, 'prescriptions');

        $labHistory = $this->actingAs($doctorUser)
            ->getJson(route('doctor.clinical.lab-orders.index', $patient->getRouteKey()));
        $labHistory->assertOk()->assertJsonCount(1, 'lab_orders');
    }

    public function test_doctor_can_view_html_and_pdf_reports(): void
    {
        Mail::fake();

        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient();
        $pharmacy = $this->createPharmacy('Corner Pharmacy');
        $lab = $this->createLaboratory('Metro Lab');
        $test = $this->createLabTest('Serum Electrolytes', 'Lytes');

        $this->actingAs($doctorUser)
            ->postJson(route('doctor.clinical.prescriptions.store', $patient->getRouteKey()), [
                'pharmacy_id' => $pharmacy->id,
                'medicines' => [['name' => 'Hydrochlorothiazide 25mg']]
            ]);

        $this->actingAs($doctorUser)
            ->postJson(route('doctor.clinical.lab-orders.store', $patient->getRouteKey()), [
                'laboratory_id' => $lab->id,
                'test_ids' => [$test->id]
            ]);

        $rx = Prescription::where('patient_id', $patient->id)->first();
        $order = LabOrder::where('patient_id', $patient->id)->first();

        // View Prescription Report via canonical route
        $this->actingAs($doctorUser)
            ->get(route('prescriptions.show', $rx->id))
            ->assertOk()
            ->assertSee('Corner Pharmacy')
            ->assertSee('Hydrochlorothiazide 25mg')
            ->assertSee('Clinical Workspace');

        // View Prescription Report via clinical prefix route
        $this->actingAs($doctorUser)
            ->get(route('doctor.clinical.prescriptions.show', [$patient->getRouteKey(), $rx->id]))
            ->assertOk()
            ->assertSee('Corner Pharmacy')
            ->assertSee('Hydrochlorothiazide 25mg');

        // View Lab Order Report
        $this->actingAs($doctorUser)
            ->get(route('doctor.clinical.lab-orders.show', [$patient->getRouteKey(), $order->id]))
            ->assertOk()
            ->assertSee('Metro Lab')
            ->assertSee('Serum Electrolytes');
    }

    public function test_prescription_created_from_workspace_has_source_and_nullable_appointment_id(): void
    {
        Mail::fake();

        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient();
        $pharmacy = $this->createPharmacy();

        $this->actingAs($doctorUser)
            ->postJson(route('doctor.clinical.prescriptions.store', $patient->getRouteKey()), [
                'pharmacy_id' => $pharmacy->id,
                'medicines' => [['name' => 'Atorvastatin 20mg']]
            ])
            ->assertOk();

        $rx = Prescription::where('patient_id', $patient->id)->first();
        $this->assertNotNull($rx);
        $this->assertNull($rx->appointment_id);
        $this->assertEquals('patient_profile', $rx->source);
    }
}
