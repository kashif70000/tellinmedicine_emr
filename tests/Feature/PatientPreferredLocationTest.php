<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\Patient;
use App\Models\PatientLaboratory;
use App\Models\PatientPharmacy;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientPreferredLocationTest extends TestCase
{
    use RefreshDatabase;

    protected User $patientUser;
    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->patientUser = User::factory()->create([
            'role' => 'patient',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $this->patient = Patient::create([
            'user_id' => $this->patientUser->id,
            'phone' => '555-0199',
            'age' => 30,
            'gender' => 'female',
            'blood_group' => 'O+',
        ]);
    }

    // ==========================================
    // PHARMACY TESTS
    // ==========================================

    public function test_authenticated_patient_can_search_pharmacies(): void
    {
        Pharmacy::create([
            'name' => 'CVS Pharmacy #1024',
            'street_address' => '35 Kneeland St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02111',
            'phone' => '(617) 542-1885',
        ]);

        $response = $this->actingAs($this->patientUser)
            ->getJson(route('patient.pharmacies.search', ['q' => 'CVS']));

        $response->assertOk()
            ->assertJsonStructure([
                'results' => [
                    '*' => ['id', 'name', 'street_address', 'city', 'state', 'postal_code', 'phone'],
                ],
            ])
            ->assertJsonCount(1, 'results')
            ->assertJsonFragment(['name' => 'CVS Pharmacy #1024']);
    }

    public function test_pharmacy_and_lab_search_shows_clean_business_name(): void
    {
        Pharmacy::create([
            'name' => 'COVID-19 Drive-Thru Testing at Walgreens',
            'city' => 'Boston',
            'state' => 'MA',
        ]);

        Laboratory::create([
            'name' => 'Drive-Thru PCR Testing at Quest Diagnostics',
            'city' => 'Boston',
            'state' => 'MA',
        ]);

        $pharmacyResponse = $this->actingAs($this->patientUser)
            ->getJson(route('patient.pharmacies.search', ['q' => 'Walgreens']));

        $pharmacyResponse->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.name', 'Walgreens');

        $labResponse = $this->actingAs($this->patientUser)
            ->getJson(route('patient.laboratories.search', ['q' => 'Quest']));

        $labResponse->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.name', 'Quest Diagnostics');
    }

    public function test_pharmacy_search_by_name(): void
    {
        Pharmacy::create(['name' => 'Walgreens Specialty Pharmacy', 'city' => 'Boston', 'state' => 'MA']);
        Pharmacy::create(['name' => 'Gary Drug Co.', 'city' => 'Boston', 'state' => 'MA']);

        $response = $this->actingAs($this->patientUser)
            ->getJson(route('patient.pharmacies.search', ['q' => 'Walgreens']));

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonFragment(['name' => 'Walgreens Specialty Pharmacy']);
    }

    public function test_pharmacy_search_by_city(): void
    {
        Pharmacy::create(['name' => 'City Center Pharmacy', 'city' => 'Worcester', 'state' => 'MA']);
        Pharmacy::create(['name' => 'Boston East Pharmacy', 'city' => 'Boston', 'state' => 'MA']);

        $response = $this->actingAs($this->patientUser)
            ->getJson(route('patient.pharmacies.search', ['q' => 'Worcester']));

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonFragment(['name' => 'City Center Pharmacy']);
    }

    public function test_pharmacy_search_by_zip(): void
    {
        Pharmacy::create(['name' => 'Downtown Rx', 'postal_code' => '02111', 'state' => 'MA']);
        Pharmacy::create(['name' => 'Uptown Rx', 'postal_code' => '02138', 'state' => 'MA']);

        $response = $this->actingAs($this->patientUser)
            ->getJson(route('patient.pharmacies.search', ['q' => '02111']));

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonFragment(['name' => 'Downtown Rx']);
    }

    public function test_pharmacy_search_by_street(): void
    {
        Pharmacy::create(['name' => 'Kneeland Drug Store', 'street_address' => '35 Kneeland St', 'city' => 'Boston', 'state' => 'MA']);
        Pharmacy::create(['name' => 'Charles Drug Store', 'street_address' => '59 Charles St', 'city' => 'Boston', 'state' => 'MA']);

        $response = $this->actingAs($this->patientUser)
            ->getJson(route('patient.pharmacies.search', ['q' => 'Kneeland']));

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonFragment(['name' => 'Kneeland Drug Store']);
    }

    public function test_pharmacy_search_under_two_chars_returns_empty(): void
    {
        Pharmacy::create(['name' => 'CVS Pharmacy', 'city' => 'Boston', 'state' => 'MA']);

        $response = $this->actingAs($this->patientUser)
            ->getJson(route('patient.pharmacies.search', ['q' => 'C']));

        $response->assertOk()
            ->assertJsonCount(0, 'results');
    }

    public function test_pharmacy_search_results_are_limited(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            Pharmacy::create([
                'name' => "Community Pharmacy #{$i}",
                'city' => 'Boston',
                'state' => 'MA',
            ]);
        }

        $response = $this->actingAs($this->patientUser)
            ->getJson(route('patient.pharmacies.search', ['q' => 'Community']));

        $response->assertOk();
        $this->assertLessThanOrEqual(15, count($response->json('results')));
    }

    public function test_patient_can_save_preferred_pharmacy(): void
    {
        $pharmacy = Pharmacy::create([
            'name' => 'CVS Pharmacy',
            'street_address' => '35 Kneeland St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02111',
            'phone' => '(617) 542-1885',
        ]);

        $response = $this->actingAs($this->patientUser)
            ->postJson(route('patient.preferred-pharmacy.store'), [
                'pharmacy_id' => $pharmacy->id,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('pharmacy.name', 'CVS Pharmacy');

        $this->assertDatabaseHas('patient_pharmacies', [
            'patient_id' => $this->patient->id,
            'pharmacy_id' => $pharmacy->id,
            'is_preferred' => true,
        ]);
    }

    public function test_preferred_pharmacy_survives_reload(): void
    {
        $pharmacy = Pharmacy::create([
            'name' => 'CVS Pharmacy',
            'city' => 'Boston',
            'state' => 'MA',
        ]);

        PatientPharmacy::create([
            'patient_id' => $this->patient->id,
            'pharmacy_id' => $pharmacy->id,
            'is_preferred' => true,
        ]);

        $reloadedPatient = Patient::find($this->patient->id);
        $this->assertNotNull($reloadedPatient->preferredPharmacy);
        $this->assertSame($pharmacy->id, $reloadedPatient->preferredPharmacy->id);
    }

    public function test_selecting_second_pharmacy_replaces_previous(): void
    {
        $pharmacy1 = Pharmacy::create(['name' => 'CVS Pharmacy #1', 'city' => 'Boston', 'state' => 'MA']);
        $pharmacy2 = Pharmacy::create(['name' => 'Walgreens #2', 'city' => 'Boston', 'state' => 'MA']);

        // First choice
        $this->actingAs($this->patientUser)
            ->postJson(route('patient.preferred-pharmacy.store'), ['pharmacy_id' => $pharmacy1->id])
            ->assertOk();

        // Second choice
        $this->actingAs($this->patientUser)
            ->postJson(route('patient.preferred-pharmacy.store'), ['pharmacy_id' => $pharmacy2->id])
            ->assertOk();

        // Verify only pharmacy2 is preferred
        $this->assertDatabaseHas('patient_pharmacies', [
            'patient_id' => $this->patient->id,
            'pharmacy_id' => $pharmacy1->id,
            'is_preferred' => false,
        ]);

        $this->assertDatabaseHas('patient_pharmacies', [
            'patient_id' => $this->patient->id,
            'pharmacy_id' => $pharmacy2->id,
            'is_preferred' => true,
        ]);

        $this->assertSame(
            1,
            PatientPharmacy::where('patient_id', $this->patient->id)->where('is_preferred', true)->count()
        );
    }

    public function test_patient_pharmacy_is_not_duplicated(): void
    {
        $pharmacy = Pharmacy::create(['name' => 'CVS Pharmacy', 'city' => 'Boston', 'state' => 'MA']);

        // Select twice
        $this->actingAs($this->patientUser)
            ->postJson(route('patient.preferred-pharmacy.store'), ['pharmacy_id' => $pharmacy->id]);

        $this->actingAs($this->patientUser)
            ->postJson(route('patient.preferred-pharmacy.store'), ['pharmacy_id' => $pharmacy->id]);

        $this->assertSame(
            1,
            PatientPharmacy::where('patient_id', $this->patient->id)->where('pharmacy_id', $pharmacy->id)->count()
        );
    }

    public function test_invalid_pharmacy_id_is_rejected(): void
    {
        $response = $this->actingAs($this->patientUser)
            ->postJson(route('patient.preferred-pharmacy.store'), [
                'pharmacy_id' => 999999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pharmacy_id']);
    }

    public function test_unauthenticated_user_cannot_search_or_save_pharmacy(): void
    {
        $this->getJson(route('patient.pharmacies.search', ['q' => 'CVS']))
            ->assertStatus(401);

        $this->postJson(route('patient.preferred-pharmacy.store'), ['pharmacy_id' => 1])
            ->assertStatus(401);
    }

    public function test_patient_cannot_modify_another_patients_pharmacy_preference(): void
    {
        $otherUser = User::factory()->create(['role' => 'patient']);
        $otherPatient = Patient::create(['user_id' => $otherUser->id]);

        $pharmacy = Pharmacy::create(['name' => 'Target Pharmacy', 'city' => 'Boston', 'state' => 'MA']);

        // Authenticated as Jane, attempts to send patient_id in body
        $this->actingAs($this->patientUser)
            ->postJson(route('patient.preferred-pharmacy.store'), [
                'pharmacy_id' => $pharmacy->id,
                'patient_id' => $otherPatient->id,
            ])
            ->assertOk();

        // Jane's record updated, NOT otherPatient's
        $this->assertDatabaseHas('patient_pharmacies', [
            'patient_id' => $this->patient->id,
            'pharmacy_id' => $pharmacy->id,
            'is_preferred' => true,
        ]);

        $this->assertDatabaseMissing('patient_pharmacies', [
            'patient_id' => $otherPatient->id,
        ]);
    }

    public function test_pharmacy_master_record_remains_untouched(): void
    {
        $pharmacy = Pharmacy::create([
            'name' => 'Original CVS Name',
            'street_address' => '100 Main St',
            'city' => 'Boston',
            'state' => 'MA',
        ]);

        $this->actingAs($this->patientUser)
            ->postJson(route('patient.preferred-pharmacy.store'), ['pharmacy_id' => $pharmacy->id]);

        $reloadedPharmacy = Pharmacy::find($pharmacy->id);
        $this->assertSame('Original CVS Name', $reloadedPharmacy->name);
        $this->assertSame('100 Main St', $reloadedPharmacy->street_address);
    }

    // ==========================================
    // LABORATORY TESTS
    // ==========================================

    public function test_authenticated_patient_can_search_laboratories(): void
    {
        Laboratory::create([
            'name' => 'Quest Diagnostics',
            'category' => 'Medical Laboratory',
            'street_address' => '319 Longwood Ave',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02115',
            'phone' => '(617) 731-2240',
        ]);

        $response = $this->actingAs($this->patientUser)
            ->getJson(route('patient.laboratories.search', ['q' => 'Quest']));

        $response->assertOk()
            ->assertJsonStructure([
                'results' => [
                    '*' => ['id', 'name', 'category', 'street_address', 'city', 'state', 'postal_code', 'phone'],
                ],
            ])
            ->assertJsonCount(1, 'results')
            ->assertJsonFragment(['name' => 'Quest Diagnostics']);
    }

    public function test_laboratory_search_by_name(): void
    {
        Laboratory::create(['name' => 'Labcorp Boston', 'city' => 'Boston', 'state' => 'MA']);
        Laboratory::create(['name' => 'BioReference Laboratories', 'city' => 'Boston', 'state' => 'MA']);

        $response = $this->actingAs($this->patientUser)
            ->getJson(route('patient.laboratories.search', ['q' => 'Labcorp']));

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonFragment(['name' => 'Labcorp Boston']);
    }

    public function test_laboratory_search_by_category(): void
    {
        Laboratory::create(['name' => 'Pathology Services Inc', 'category' => 'Diagnostic Pathology', 'city' => 'Boston', 'state' => 'MA']);
        Laboratory::create(['name' => 'General Blood Work Lab', 'category' => 'Clinical Laboratory', 'city' => 'Boston', 'state' => 'MA']);

        $response = $this->actingAs($this->patientUser)
            ->getJson(route('patient.laboratories.search', ['q' => 'Pathology']));

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonFragment(['name' => 'Pathology Services Inc']);
    }

    public function test_laboratory_search_by_city(): void
    {
        Laboratory::create(['name' => 'Cambridge Diagnostic Lab', 'city' => 'Cambridge', 'state' => 'MA']);
        Laboratory::create(['name' => 'Boston Medical Lab', 'city' => 'Boston', 'state' => 'MA']);

        $response = $this->actingAs($this->patientUser)
            ->getJson(route('patient.laboratories.search', ['q' => 'Cambridge']));

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonFragment(['name' => 'Cambridge Diagnostic Lab']);
    }

    public function test_laboratory_search_by_zip(): void
    {
        Laboratory::create(['name' => 'Longwood Lab', 'postal_code' => '02115', 'state' => 'MA']);
        Laboratory::create(['name' => 'Harvard Lab', 'postal_code' => '02138', 'state' => 'MA']);

        $response = $this->actingAs($this->patientUser)
            ->getJson(route('patient.laboratories.search', ['q' => '02115']));

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonFragment(['name' => 'Longwood Lab']);
    }

    public function test_laboratory_search_results_are_limited(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            Laboratory::create([
                'name' => "Specialty Laboratory #{$i}",
                'city' => 'Boston',
                'state' => 'MA',
            ]);
        }

        $response = $this->actingAs($this->patientUser)
            ->getJson(route('patient.laboratories.search', ['q' => 'Specialty']));

        $response->assertOk();
        $this->assertLessThanOrEqual(15, count($response->json('results')));
    }

    public function test_patient_can_save_preferred_laboratory(): void
    {
        $lab = Laboratory::create([
            'name' => 'Quest Diagnostics',
            'category' => 'Medical Laboratory',
            'street_address' => '319 Longwood Ave',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02115',
            'phone' => '(617) 731-2240',
        ]);

        $response = $this->actingAs($this->patientUser)
            ->postJson(route('patient.preferred-laboratory.store'), [
                'laboratory_id' => $lab->id,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('laboratory.name', 'Quest Diagnostics');

        $this->assertDatabaseHas('patient_laboratories', [
            'patient_id' => $this->patient->id,
            'laboratory_id' => $lab->id,
            'is_preferred' => true,
        ]);
    }

    public function test_preferred_laboratory_survives_reload(): void
    {
        $lab = Laboratory::create([
            'name' => 'Quest Diagnostics',
            'city' => 'Boston',
            'state' => 'MA',
        ]);

        PatientLaboratory::create([
            'patient_id' => $this->patient->id,
            'laboratory_id' => $lab->id,
            'is_preferred' => true,
        ]);

        $reloadedPatient = Patient::find($this->patient->id);
        $this->assertNotNull($reloadedPatient->preferredLaboratory);
        $this->assertSame($lab->id, $reloadedPatient->preferredLaboratory->id);
    }

    public function test_selecting_second_lab_replaces_previous(): void
    {
        $lab1 = Laboratory::create(['name' => 'Quest Diagnostics #1', 'city' => 'Boston', 'state' => 'MA']);
        $lab2 = Laboratory::create(['name' => 'Labcorp #2', 'city' => 'Boston', 'state' => 'MA']);

        // First choice
        $this->actingAs($this->patientUser)
            ->postJson(route('patient.preferred-laboratory.store'), ['laboratory_id' => $lab1->id])
            ->assertOk();

        // Second choice
        $this->actingAs($this->patientUser)
            ->postJson(route('patient.preferred-laboratory.store'), ['laboratory_id' => $lab2->id])
            ->assertOk();

        $this->assertDatabaseHas('patient_laboratories', [
            'patient_id' => $this->patient->id,
            'laboratory_id' => $lab1->id,
            'is_preferred' => false,
        ]);

        $this->assertDatabaseHas('patient_laboratories', [
            'patient_id' => $this->patient->id,
            'laboratory_id' => $lab2->id,
            'is_preferred' => true,
        ]);

        $this->assertSame(
            1,
            PatientLaboratory::where('patient_id', $this->patient->id)->where('is_preferred', true)->count()
        );
    }

    public function test_patient_lab_is_not_duplicated(): void
    {
        $lab = Laboratory::create(['name' => 'Quest Diagnostics', 'city' => 'Boston', 'state' => 'MA']);

        $this->actingAs($this->patientUser)
            ->postJson(route('patient.preferred-laboratory.store'), ['laboratory_id' => $lab->id]);

        $this->actingAs($this->patientUser)
            ->postJson(route('patient.preferred-laboratory.store'), ['laboratory_id' => $lab->id]);

        $this->assertSame(
            1,
            PatientLaboratory::where('patient_id', $this->patient->id)->where('laboratory_id', $lab->id)->count()
        );
    }

    public function test_invalid_laboratory_id_is_rejected(): void
    {
        $response = $this->actingAs($this->patientUser)
            ->postJson(route('patient.preferred-laboratory.store'), [
                'laboratory_id' => 999999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['laboratory_id']);
    }

    public function test_unauthenticated_user_cannot_search_or_save_lab(): void
    {
        $this->getJson(route('patient.laboratories.search', ['q' => 'Quest']))
            ->assertStatus(401);

        $this->postJson(route('patient.preferred-laboratory.store'), ['laboratory_id' => 1])
            ->assertStatus(401);
    }

    public function test_patient_cannot_modify_another_patients_lab_preference(): void
    {
        $otherUser = User::factory()->create(['role' => 'patient']);
        $otherPatient = Patient::create(['user_id' => $otherUser->id]);

        $lab = Laboratory::create(['name' => 'Metro Lab', 'city' => 'Boston', 'state' => 'MA']);

        $this->actingAs($this->patientUser)
            ->postJson(route('patient.preferred-laboratory.store'), [
                'laboratory_id' => $lab->id,
                'patient_id' => $otherPatient->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('patient_laboratories', [
            'patient_id' => $this->patient->id,
            'laboratory_id' => $lab->id,
            'is_preferred' => true,
        ]);

        $this->assertDatabaseMissing('patient_laboratories', [
            'patient_id' => $otherPatient->id,
        ]);
    }

    public function test_laboratory_master_record_remains_untouched(): void
    {
        $lab = Laboratory::create([
            'name' => 'Original Lab Name',
            'street_address' => '200 Science Way',
            'city' => 'Boston',
            'state' => 'MA',
        ]);

        $this->actingAs($this->patientUser)
            ->postJson(route('patient.preferred-laboratory.store'), ['laboratory_id' => $lab->id]);

        $reloadedLab = Laboratory::find($lab->id);
        $this->assertSame('Original Lab Name', $reloadedLab->name);
        $this->assertSame('200 Science Way', $reloadedLab->street_address);
    }

    // ==========================================
    // RELATIONSHIP & INDEPENDENCE TESTS
    // ==========================================

    public function test_patient_preferred_pharmacy_relationship(): void
    {
        $pharmacy = Pharmacy::create(['name' => 'Preferred CVS', 'city' => 'Boston', 'state' => 'MA']);
        PatientPharmacy::create([
            'patient_id' => $this->patient->id,
            'pharmacy_id' => $pharmacy->id,
            'is_preferred' => true,
        ]);

        $this->assertNotNull($this->patient->preferredPharmacy);
        $this->assertSame('Preferred CVS', $this->patient->preferredPharmacy->name);
    }

    public function test_patient_preferred_laboratory_relationship(): void
    {
        $lab = Laboratory::create(['name' => 'Preferred Quest', 'city' => 'Boston', 'state' => 'MA']);
        PatientLaboratory::create([
            'patient_id' => $this->patient->id,
            'laboratory_id' => $lab->id,
            'is_preferred' => true,
        ]);

        $this->assertNotNull($this->patient->preferredLaboratory);
        $this->assertSame('Preferred Quest', $this->patient->preferredLaboratory->name);
    }

    public function test_changing_pharmacy_does_not_affect_lab_preference(): void
    {
        $lab = Laboratory::create(['name' => 'Anchor Lab', 'city' => 'Boston', 'state' => 'MA']);
        PatientLaboratory::create([
            'patient_id' => $this->patient->id,
            'laboratory_id' => $lab->id,
            'is_preferred' => true,
        ]);

        $pharmacy1 = Pharmacy::create(['name' => 'Pharm 1', 'city' => 'Boston', 'state' => 'MA']);
        $pharmacy2 = Pharmacy::create(['name' => 'Pharm 2', 'city' => 'Boston', 'state' => 'MA']);

        $this->actingAs($this->patientUser)
            ->postJson(route('patient.preferred-pharmacy.store'), ['pharmacy_id' => $pharmacy1->id]);

        $this->actingAs($this->patientUser)
            ->postJson(route('patient.preferred-pharmacy.store'), ['pharmacy_id' => $pharmacy2->id]);

        // Lab preference remains intact
        $this->assertDatabaseHas('patient_laboratories', [
            'patient_id' => $this->patient->id,
            'laboratory_id' => $lab->id,
            'is_preferred' => true,
        ]);

        $reloadedPatient = Patient::find($this->patient->id);
        $this->assertSame($lab->id, $reloadedPatient->preferredLaboratory->id);
    }

    public function test_changing_lab_does_not_affect_pharmacy_preference(): void
    {
        $pharmacy = Pharmacy::create(['name' => 'Anchor Pharmacy', 'city' => 'Boston', 'state' => 'MA']);
        PatientPharmacy::create([
            'patient_id' => $this->patient->id,
            'pharmacy_id' => $pharmacy->id,
            'is_preferred' => true,
        ]);

        $lab1 = Laboratory::create(['name' => 'Lab 1', 'city' => 'Boston', 'state' => 'MA']);
        $lab2 = Laboratory::create(['name' => 'Lab 2', 'city' => 'Boston', 'state' => 'MA']);

        $this->actingAs($this->patientUser)
            ->postJson(route('patient.preferred-laboratory.store'), ['laboratory_id' => $lab1->id]);

        $this->actingAs($this->patientUser)
            ->postJson(route('patient.preferred-laboratory.store'), ['laboratory_id' => $lab2->id]);

        // Pharmacy preference remains intact
        $this->assertDatabaseHas('patient_pharmacies', [
            'patient_id' => $this->patient->id,
            'pharmacy_id' => $pharmacy->id,
            'is_preferred' => true,
        ]);

        $reloadedPatient = Patient::find($this->patient->id);
        $this->assertSame($pharmacy->id, $reloadedPatient->preferredPharmacy->id);
    }

    public function test_patient_can_remove_preferred_pharmacy_and_laboratory(): void
    {
        $pharmacy = Pharmacy::create(['name' => 'CVS', 'city' => 'Boston', 'state' => 'MA']);
        $lab = Laboratory::create(['name' => 'Quest', 'city' => 'Boston', 'state' => 'MA']);

        PatientPharmacy::create([
            'patient_id' => $this->patient->id,
            'pharmacy_id' => $pharmacy->id,
            'is_preferred' => true,
        ]);

        PatientLaboratory::create([
            'patient_id' => $this->patient->id,
            'laboratory_id' => $lab->id,
            'is_preferred' => true,
        ]);

        // Remove pharmacy
        $this->actingAs($this->patientUser)
            ->deleteJson(route('patient.preferred-pharmacy.destroy'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('patient_pharmacies', [
            'patient_id' => $this->patient->id,
            'is_preferred' => false,
        ]);

        // Remove lab
        $this->actingAs($this->patientUser)
            ->deleteJson(route('patient.preferred-laboratory.destroy'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('patient_laboratories', [
            'patient_id' => $this->patient->id,
            'is_preferred' => false,
        ]);

        $reloadedPatient = Patient::find($this->patient->id);
        $this->assertNull($reloadedPatient->preferredPharmacy);
        $this->assertNull($reloadedPatient->preferredLaboratory);
    }
}
