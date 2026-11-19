<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MassachusettsLicenseVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        config(['doctor_lookup.enabled' => false]);
    }

    public function test_non_admin_cannot_retry_a_doctor_license_verification(): void
    {
        $doctorUser = User::create([
            'name' => 'Alice Smith',
            'email' => 'doctor@example.com',
            'password' => bcrypt('password123'),
            'role' => 'doctor',
            'is_active' => true,
        ]);
        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'license_state' => 'MA',
            'license_type' => 'Full Physician',
            'license_number' => 'MA123456',
            'license_issue_date' => now()->subYears(5)->toDateString(),
            'license_expiration_date' => now()->addYear()->toDateString(),
        ]);
        $patient = User::create([
            'name' => 'Patient',
            'email' => 'patient@example.com',
            'password' => bcrypt('password123'),
            'role' => 'patient',
            'is_active' => true,
        ]);

        $this->actingAs($patient)
            ->postJson(route('admin.doctors.license.verify', $doctor))
            ->assertForbidden();
    }

    public function test_admin_recheck_saves_official_license_details(): void
    {
        config([
            'doctor_lookup.enabled' => true,
            'doctor_lookup.base_url' => 'https://doctorlicenselookup.test',
        ]);
        Http::fake([
            'https://doctorlicenselookup.test/state/massachusetts' => Http::response(
                '<html><meta name="csrf-token" content="fresh-token"></html>'
            ),
            'https://doctorlicenselookup.test/massachusetts/search' => Http::response([
                'success' => true,
                'data' => [
                    'license_no' => '245843',
                    'license_status' => 'Active',
                ],
            ]),
        ]);
        $doctor = $this->createMassachusettsDoctor();
        $admin = $this->createUser('Admin', 'admin@example.com', 'admin');

        $response = $this->actingAs($admin)
            ->postJson(route('admin.doctors.license.verify', $doctor));

        $response->assertOk()->assertJsonPath('status', 'verified');
        $this->assertDatabaseHas('doctors', [
            'id' => $doctor->id,
            'official_license_status' => 'Active',
            'license_verification_status' => 'verified',
            'is_verified' => true,
        ]);
        $this->assertDatabaseHas('license_verification_attempts', [
            'doctor_id' => $doctor->id,
            'status' => 'verified',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_admin_cannot_run_uk_verification_for_massachusetts_doctor(): void
    {
        $doctor = $this->createMassachusettsDoctor();
        $admin = $this->createUser('Admin', 'admin-uk-check@example.com', 'admin');

        $this->actingAs($admin)
            ->postJson(route('admin.doctors.uk.verify', $doctor))
            ->assertNotFound();
    }

    public function test_admin_api_outage_does_not_deactivate_doctor(): void
    {
        config([
            'doctor_lookup.enabled' => true,
            'doctor_lookup.base_url' => 'https://doctorlicenselookup.test',
        ]);
        Http::fake([
            'https://doctorlicenselookup.test/state/massachusetts' => Http::response('unavailable', 503),
        ]);
        $doctor = $this->createMassachusettsDoctor(active: true);
        $admin = $this->createUser('Admin', 'admin-outage@example.com', 'admin');

        $response = $this->actingAs($admin)
            ->postJson(route('admin.doctors.license.verify', $doctor));

        $response->assertStatus(422)->assertJsonPath('status', 'unable_to_verify');
        $this->assertDatabaseHas('users', ['id' => $doctor->user_id, 'is_active' => true]);
        $this->assertDatabaseHas('license_verification_attempts', [
            'doctor_id' => $doctor->id,
            'status' => 'unable_to_verify',
        ]);
    }

    private function createMassachusettsDoctor(bool $active = true): Doctor
    {
        $user = $this->createUser('Alice Smith', 'ma-doctor@example.com', 'doctor', $active);

        return Doctor::create([
            'user_id' => $user->id,
            'name' => 'Alice Smith',
            'specialization' => 'Family Medicine',
            'experience' => 8,
            'fees' => 125,
            'license_state' => 'MA',
            'license_type' => 'Full Physician',
            'license_number' => '245843',
            'license_issue_date' => now()->subYears(5)->toDateString(),
            'license_expiration_date' => now()->addYear()->toDateString(),
            'is_verified' => true,
        ]);
    }

    private function createUser(string $name, string $email, string $role, bool $active = true): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password123'),
            'role' => $role,
            'is_active' => $active,
        ]);
    }
}