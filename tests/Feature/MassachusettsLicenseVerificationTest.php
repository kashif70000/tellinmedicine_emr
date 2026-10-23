<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\User;
use App\Services\MassachusettsLicenseSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MassachusettsLicenseVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function application(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Alice Smith',
            'email' => 'alice@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '617-555-0100',
            'specialization' => 'Family Medicine',
            'experience' => 8,
            'fees' => 125,
            'clinic_name' => 'Boston Clinic',
            'address' => 'Boston, MA',
            'license_state' => 'MA',
            'license_type' => 'Full Physician',
            'license_number' => 'MA123456',
            'license_issue_date' => now()->subYears(5)->toDateString(),
            'license_expiration_date' => now()->addYear()->toDateString(),
            'certificate' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf'),
        ], $overrides);
    }

    private function officialRecord(array $overrides = []): array
    {
        return array_merge([
            'license_number' => 'MA123456',
            'full_name' => 'Alice Smith',
            'license_type' => 'Full Physician',
            'status' => 'Active',
            'issue_date' => now()->subYears(5)->toDateString(),
            'expiration_date' => now()->addYear()->toDateString(),
        ], $overrides);
    }

    private function useOfficialRecord(?array $record): void
    {
        $this->app->instance(MassachusettsLicenseSource::class, new class($record) implements MassachusettsLicenseSource {
            public function __construct(private ?array $record) {}

            public function find(string $licenseType, string $licenseNumber): ?array
            {
                return $this->record;
            }

            public function sourceName(): string
            {
                return 'Authorized Massachusetts test source';
            }
        });
    }

    public function test_matching_authoritative_record_allows_registration(): void
    {
        $this->useOfficialRecord($this->officialRecord());

        $response = $this->postJson(route('register.doctor.store'), $this->application());

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('users', ['email' => 'alice@example.com', 'role' => 'doctor', 'is_active' => true]);
        $this->assertDatabaseHas('doctors', [
            'license_state' => 'MA',
            'license_verification_status' => 'verified',
            'license_number' => 'MA123456',
            'is_verified' => true,
            'verification_status' => 'approved',
        ]);
        $this->assertDatabaseHas('license_verification_attempts', ['status' => 'verified']);
    }

    public function test_registration_verifies_against_the_configured_http_source(): void
    {
        config(['services.massachusetts_license.endpoint_url' => 'https://api.medboard.mass.gov/api-public/search']);
        Http::fake([
            'https://api.medboard.mass.gov/api-public/search' => Http::response([
                'results' => ['data' => [[
                    'licenseNumber' => '245843',
                    'licenseMetaId' => 17,
                    'licenseMetaName' => 'Physician Full License',
                    'profileStatus' => 'Active',
                    'expirationDate' => now()->addYear()->endOfDay()->toIso8601String(),
                    'fullName' => 'Alice Smith',
                    'originalDate' => now()->subYears(5)->toDateString(),
                    'startDate' => now()->subYears(5)->toDateString(),
                    'degree' => 'MD',
                    'specialties' => [],
                ]], 'totalDataCount' => 1],
            ]),
        ]);

        $response = $this->postJson(route('register.doctor.store'), $this->application([
            'license_number' => '245843',
            'license_issue_date' => now()->subYears(5)->toDateString(),
        ]));

        $response->assertOk()->assertJsonPath('success', true);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->data()['searchType'] === 'BY_LICENSE_NUMBER');
    }

    public function test_invalid_license_is_failed_and_does_not_register(): void
    {
        $this->useOfficialRecord($this->officialRecord(['license_number' => 'MA999999']));

        $response = $this->postJson(route('register.doctor.store'), $this->application());

        $response->assertStatus(422)->assertJsonPath('status', 'failed');
        $this->assertDatabaseMissing('users', ['email' => 'alice@example.com']);
        $this->assertDatabaseHas('license_verification_attempts', ['status' => 'failed']);
    }

    public function test_name_mismatch_does_not_block_registration_when_license_details_match(): void
    {
        $this->useOfficialRecord($this->officialRecord(['full_name' => 'Someone Else']));

        $response = $this->postJson(route('register.doctor.store'), $this->application());

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('doctors', [
            'license_number' => 'MA123456',
            'license_verification_status' => 'verified',
            'is_verified' => true,
            'verification_status' => 'approved',
        ]);
    }

    public function test_expired_official_license_does_not_register(): void
    {
        $this->useOfficialRecord($this->officialRecord([
            'expiration_date' => now()->subDay()->toDateString(),
        ]));

        $response = $this->postJson(route('register.doctor.store'), $this->application());

        $response->assertStatus(422)->assertJsonPath('status', 'expired');
        $this->assertDatabaseMissing('users', ['email' => 'alice@example.com']);
    }

    public function test_unavailable_source_returns_unable_to_verify_and_blocks_registration(): void
    {
        $this->useOfficialRecord(null);

        $response = $this->postJson(route('register.doctor.store'), $this->application());

        $response->assertStatus(422)->assertJsonPath('status', 'unable_to_verify');
        $this->assertDatabaseMissing('users', ['email' => 'alice@example.com']);
        $this->assertDatabaseHas('license_verification_attempts', ['status' => 'unable_to_verify']);
    }

    public function test_registration_without_verified_license_is_blocked_by_default(): void
    {
        $response = $this->postJson(route('register.doctor.store'), $this->application());

        $response->assertStatus(422)->assertJsonPath('status', 'unable_to_verify');
        $this->assertDatabaseMissing('users', ['email' => 'alice@example.com']);
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
        config(['services.massachusetts_license.endpoint_url' => 'https://api.medboard.mass.gov/api-public/search']);
        Http::fake([
            'https://api.medboard.mass.gov/api-public/search' => Http::response([
                'results' => ['data' => [[
                    'licenseNumber' => '245843',
                    'licenseMetaId' => 17,
                    'licenseMetaName' => 'Physician Full License',
                    'profileStatus' => 'Active',
                    'expirationDate' => now()->addYear()->endOfDay()->toIso8601String(),
                    'fullName' => 'Alice Smith',
                    'originalDate' => now()->subYears(5)->toDateString(),
                    'startDate' => now()->subYears(5)->toDateString(),
                    'degree' => 'MD',
                    'specialties' => [],
                ]], 'totalDataCount' => 1],
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
        config(['services.massachusetts_license.endpoint_url' => 'https://api.medboard.mass.gov/api-public/search']);
        Http::fake([
            'https://api.medboard.mass.gov/api-public/search' => Http::response(['error' => 'unavailable'], 503),
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