<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class USLicenseCheckRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://uslicensecheck.test/api/v1/lookup/verify';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'uslicensecheck.base_url' => 'https://uslicensecheck.test/api/v1',
            'uslicensecheck.api_key' => 'test-key-not-a-secret',
            'uslicensecheck.supported_states' => ['OH', 'CT', 'CO', 'DE', 'TX', 'IL', 'NH', 'AK', 'WA'],
        ]);
    }

    public function test_matching_us_license_registers_as_verified_and_records_the_attempt(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->record(['multiple' => null]))]);

        $response = $this->postJson(route('register.doctor.store'), $this->application());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('verification_pending', false);
        $this->assertDatabaseHas('doctors', [
            'license_state' => 'OH',
            'license_number' => 'OH123456',
            'license_verification_status' => 'verified',
            'license_verification_source' => 'USLicenseCheck',
            'is_verified' => true,
            'verification_status' => 'approved',
        ]);
        $this->assertDatabaseHas('license_verification_attempts', [
            'license_state' => 'OH',
            'license_type' => 'Physician License',
            'source' => 'USLicenseCheck',
            'status' => 'verified',
        ]);
        Http::assertSent(fn (Request $request) => $request->hasHeader('X-API-Key', 'test-key-not-a-secret')
            && $request->data() === [
                'license_number' => 'OH123456',
                'source_state' => 'OH',
            ]);
    }

    public function test_matching_record_returns_verified_from_the_registration_verification_endpoint(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->record(['multiple' => null]))]);

        $this->postJson(route('register.doctor.verify-license'), [
            'name' => 'Alice Smith',
            'license_state' => 'OH',
            'license_number' => 'OH123456',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'verified')
            ->assertJsonPath('official_data.found', true)
            ->assertJsonPath('official_data.multiple', null);
    }

    public function test_inconclusive_provider_result_registers_only_for_manual_review(): void
    {
        Http::fake([self::ENDPOINT => Http::response([
            'found' => true,
            'multiple' => [$this->record(), $this->record(['license_number' => 'OH654321'])],
        ])]);

        $response = $this->postJson(route('register.doctor.store'), $this->application());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('verification_pending', true);
        $this->assertDatabaseHas('doctors', [
            'license_state' => 'OH',
            'license_verification_status' => 'manual_review',
            'is_verified' => false,
            'verification_status' => 'pending',
        ]);
        $this->assertDatabaseHas('license_verification_attempts', ['status' => 'manual_review']);
    }

    public function test_provider_outage_never_approves_the_registration(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['error' => 'unavailable'], 503)]);

        $response = $this->postJson(route('register.doctor.store'), $this->application());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('verification_pending', true);
        $this->assertDatabaseHas('doctors', [
            'license_state' => 'OH',
            'license_verification_status' => 'unavailable',
            'is_verified' => false,
            'verification_status' => 'pending',
        ]);
    }

    public function test_no_match_blocks_registration_and_records_the_failed_attempt(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['found' => false])]);

        $response = $this->postJson(route('register.doctor.store'), $this->application());

        $response->assertStatus(422)->assertJsonPath('status', 'failed');
        $this->assertDatabaseMissing('users', ['email' => 'alice@example.com']);
        $this->assertDatabaseHas('license_verification_attempts', [
            'license_state' => 'OH',
            'status' => 'failed',
            'source' => 'USLicenseCheck',
        ]);
    }

    public function test_registration_rejects_missing_and_unsupported_license_states(): void
    {
        Http::fake();

        $this->postJson(route('register.doctor.store'), $this->application(['license_state' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('license_state');

        $this->postJson(route('register.doctor.store'), $this->application(['license_state' => 'CA']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('license_state');

        Http::assertNothingSent();
    }

    public function test_admin_can_recheck_a_supported_us_license_using_the_doctor_state(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->record())]);
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $doctorUser = User::create([
            'name' => 'Alice Smith',
            'email' => 'doctor@example.com',
            'password' => bcrypt('password123'),
            'role' => 'doctor',
            'is_active' => true,
        ]);
        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'license_state' => 'OH',
            'license_number' => 'OH123456',
            'license_type' => 'Physician License',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.doctors.license.verify', $doctor))
            ->assertOk()
            ->assertJsonPath('status', 'verified');

        $this->assertDatabaseHas('doctors', [
            'id' => $doctor->id,
            'license_verification_status' => 'verified',
            'license_verification_source' => 'USLicenseCheck',
            'is_verified' => true,
            'verification_status' => 'approved',
        ]);
        Http::assertSent(fn (Request $request) => $request->data() === [
            'license_number' => 'OH123456',
            'source_state' => 'OH',
        ]);
    }

    private function application(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Alice Smith',
            'email' => 'alice@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '614-555-0100',
            'specialization' => 'Family Medicine',
            'experience' => 8,
            'fees' => 125,
            'clinic_name' => 'Columbus Clinic',
            'address' => 'Columbus, OH',
            'license_state' => 'OH',
            'license_number' => 'OH123456',
            'certificate' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf'),
        ], $overrides);
    }

    private function record(array $overrides = []): array
    {
        return array_merge([
            'found' => true,
            'full_name' => 'Smith, Alice',
            'license_number' => 'OH123456',
            'board' => 'Medical Board',
            'license_type' => 'Doctor of Medicine (MD)',
            'status' => 'Active',
            'expiry_date' => now()->addYear()->toDateString(),
            'source_state' => 'OH',
        ], $overrides);
    }
}
