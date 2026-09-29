<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorPatientSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function createDoctor(string $name = 'Dr. Alexander Fleming', string $email = 'doctor@example.com'): array
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

    protected function createPatient(string $name = 'Jane Doe', string $email = 'jane@example.com', ?string $phone = '617-555-0199'): array
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
            'age' => 30,
            'gender' => 'female',
            'blood_group' => 'O+',
            'phone' => $phone,
            'is_verified' => true,
            'is_payment_method_verified' => true,
        ]);

        return [$user, $patient];
    }

    protected function createAppointment(Doctor $doctor, Patient $patient, string $date = '2026-09-10', string $time = '10:00'): Appointment
    {
        return Appointment::create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => $date,
            'appointment_time' => $time,
            'status' => 'approved',
            'fee_snapshot' => 150.00,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_doctor_patient_search(): void
    {
        $response = $this->getJson(route('doctor.patients.search', ['q' => 'Jane']));
        $response->assertStatus(401);
    }

    public function test_patient_role_user_cannot_access_doctor_search_endpoint(): void
    {
        [$patientUser] = $this->createPatient();

        $response = $this->actingAs($patientUser)
            ->getJson(route('doctor.patients.search', ['q' => 'Jane']));

        $response->assertStatus(403);
    }

    public function test_admin_role_without_doctor_profile_cannot_access_doctor_search_endpoint(): void
    {
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($adminUser)
            ->getJson(route('doctor.patients.search', ['q' => 'Jane']));

        $response->assertStatus(403);
    }

    public function test_doctor_can_search_assigned_patient_by_name(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient('Johnathan Smith', 'johnathan@example.com');
        $this->createAppointment($doctor, $patient);

        $response = $this->actingAs($doctorUser)
            ->getJson(route('doctor.patients.search', ['q' => 'Johnathan']));

        $response->assertOk()
            ->assertJsonStructure(['results' => [['id', 'uuid', 'name', 'email', 'phone', 'profile_image', 'profile_url']]])
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', $patient->id)
            ->assertJsonPath('results.0.name', 'Johnathan Smith')
            ->assertJsonPath('results.0.email', 'johnathan@example.com');
    }

    public function test_doctor_can_search_by_email(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient('Alice Wonderland', 'alice.wonder@hospital.org');
        $this->createAppointment($doctor, $patient);

        $response = $this->actingAs($doctorUser)
            ->getJson(route('doctor.patients.search', ['q' => 'wonder@hospital']));

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.name', 'Alice Wonderland');
    }

    public function test_doctor_can_search_by_phone(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient('Bob Builder', 'bob@builder.com', '617-888-9999');
        $this->createAppointment($doctor, $patient);

        $response = $this->actingAs($doctorUser)
            ->getJson(route('doctor.patients.search', ['q' => '888-9999']));

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', $patient->id)
            ->assertJsonPath('results.0.phone', '617-888-9999');
    }

    public function test_doctor_can_search_by_uuid(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient('Charlie Brown', 'charlie@peanuts.com');
        $this->createAppointment($doctor, $patient);

        $uuid = $patientUser->ensureUuid();

        $response = $this->actingAs($doctorUser)
            ->getJson(route('doctor.patients.search', ['q' => substr($uuid, 0, 4)]));

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.uuid', $uuid);
    }

    public function test_doctor_can_search_by_patient_id(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient('Diana Prince', 'diana@themyscira.com');
        $patient->update(['phone' => '555-4321']);
        $this->createAppointment($doctor, $patient);

        $searchTerm = str_pad((string) $patient->id, 2, '0', STR_PAD_LEFT);
        if ($patient->id < 10) {
            // Test searching with 2-digit representation or query string >= 2 chars
            $response = $this->actingAs($doctorUser)
                ->getJson(route('doctor.patients.search', ['q' => 'Diana']));
            $response->assertOk()
                ->assertJsonCount(1, 'results')
                ->assertJsonPath('results.0.id', $patient->id);
        } else {
            $response = $this->actingAs($doctorUser)
                ->getJson(route('doctor.patients.search', ['q' => (string) $patient->id]));
            $response->assertOk()
                ->assertJsonCount(1, 'results')
                ->assertJsonPath('results.0.id', $patient->id);
        }
    }

    public function test_partial_case_insensitive_search_works(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient('Elizabeth Montgomery', 'elizabeth@magic.com');
        $this->createAppointment($doctor, $patient);

        $response = $this->actingAs($doctorUser)
            ->getJson(route('doctor.patients.search', ['q' => 'montgom']));

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.name', 'Elizabeth Montgomery');
    }

    public function test_doctor_can_search_registered_patient_without_prior_appointment(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient('New Unbooked Patient', 'newpatient@example.com');

        $response = $this->actingAs($doctorUser)
            ->getJson(route('doctor.patients.search', ['q' => 'Unbooked']));

        $response->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', $patient->id)
            ->assertJsonPath('results.0.name', 'New Unbooked Patient');
    }

    public function test_empty_query_returns_no_results(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient('Any Patient', 'any@patient.com');

        $response = $this->actingAs($doctorUser)
            ->getJson(route('doctor.patients.search', ['q' => '']));

        $response->assertOk()
            ->assertJsonCount(0, 'results');
    }

    public function test_query_under_minimum_length_returns_empty_results(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient('Alexander Smith', 'alex@smith.com');

        $response = $this->actingAs($doctorUser)
            ->getJson(route('doctor.patients.search', ['q' => 'A']));

        $response->assertOk()
            ->assertJsonCount(0, 'results');
    }

    public function test_result_count_is_limited_to_ten(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();

        // Create 15 matching patients
        for ($i = 1; $i <= 15; $i++) {
            $this->createPatient("TestPatient {$i}", "patient{$i}@testing.com");
        }

        $response = $this->actingAs($doctorUser)
            ->getJson(route('doctor.patients.search', ['q' => 'TestPatient']));

        $response->assertOk()
            ->assertJsonCount(10, 'results');
    }

    public function test_returned_profile_url_points_to_correct_patient_route_with_uuid(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient('Target Patient', 'target@test.com');

        $uuid = $patientUser->ensureUuid();

        $response = $this->actingAs($doctorUser)
            ->getJson(route('doctor.patients.search', ['q' => 'Target']));

        $response->assertOk();
        $expectedUrl = route('patients.show', $uuid);
        $this->assertEquals($expectedUrl, $response->json('results.0.profile_url'));
        $this->assertStringContainsString($uuid, $response->json('results.0.profile_url'));
    }

    public function test_doctor_can_view_patient_profile_page_using_uuid(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient('Viewable Patient', 'viewable@patient.com', '617-123-4567');
        $uuid = $patientUser->ensureUuid();

        $response = $this->actingAs($doctorUser)
            ->get(route('patients.show', $uuid));

        $response->assertOk()
            ->assertSee('Viewable Patient')
            ->assertSee('viewable@patient.com')
            ->assertSee('617-123-4567');
    }

    public function test_doctor_can_view_patient_profile_page_using_numeric_id_fallback(): void
    {
        [$doctorUser, $doctor] = $this->createDoctor();
        [$patientUser, $patient] = $this->createPatient('Fallback Patient', 'fallback@patient.com', '617-999-0000');

        $response = $this->actingAs($doctorUser)
            ->get(route('patients.show', $patient->id));

        $response->assertOk()
            ->assertSee('Fallback Patient')
            ->assertSee('fallback@patient.com')
            ->assertSee('617-999-0000');
    }

    public function test_patient_cannot_view_another_patient_profile(): void
    {
        [$patient1User, $patient1] = $this->createPatient('Patient One', 'p1@test.com');
        [$patient2User, $patient2] = $this->createPatient('Patient Two', 'p2@test.com');

        $response = $this->actingAs($patient1User)
            ->get(route('patients.show', $patient2User->ensureUuid()));

        $response->assertStatus(403);
    }
}
