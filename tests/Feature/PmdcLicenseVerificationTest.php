<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PmdcLicenseVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://pmdc.test/api/DRC/GetData';

    protected function setUp(): void
    {
        parent::setUp();

        config(['pmdc.license_verification_url' => self::ENDPOINT]);
    }

    public function test_pakistan_registration_verifies_and_saves_official_license_details(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->response())]);

        $response = $this->postWithCsrf(route('register.doctor.store'), $this->application());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('verification_pending', false);
        $this->assertDatabaseHas('doctors', [
            'license_state' => 'PK',
            'license_number' => '75960-P',
            'license_type' => 'Permanent',
            'license_verification_status' => 'verified',
            'official_license_status' => 'ACTIVE',
            'official_license_issue_date' => '2015-03-18',
            'official_license_expiration_date' => '2029-12-30',
            'license_verification_source' => 'Pakistan Medical & Dental Council (PMDC)',
            'is_verified' => true,
            'verification_status' => 'approved',
        ]);
        $this->assertDatabaseHas('license_verification_attempts', [
            'license_state' => 'PK',
            'license_number' => '75960-P',
            'status' => 'verified',
            'source' => 'Pakistan Medical & Dental Council (PMDC)',
        ]);
        Http::assertSent(fn (Request $request) => $request->data() === ['RegistrationNo' => '75960-P']);
    }

    public function test_inactive_registration_blocks_doctor_registration(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->response([
            'data' => [$this->record(['Status' => 'INACTIVE'])],
        ]))]);

        $this->postWithCsrf(route('register.doctor.store'), $this->application())
            ->assertUnprocessable()
            ->assertJsonPath('status', 'inactive');

        $this->assertDatabaseMissing('users', ['email' => 'pmdc@example.com']);
    }

    public function test_registration_verification_endpoint_needs_only_a_license_number(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->response())]);

        $this->postWithCsrf(route('register.doctor.verify-pmdc'), ['license_number' => '75960-P'])
            ->assertOk()
            ->assertJsonPath('status', 'verified')
            ->assertJsonPath('official_data.name', 'BILAL ZAHOOR');

        Http::assertSent(fn (Request $request) => $request->data() === ['RegistrationNo' => '75960-P']);
    }

    public function test_admin_page_and_verification_endpoint_support_pakistan(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->actingAs($admin)
            ->get(route('admin.license-verification.index'))
            ->assertOk()
            ->assertSee('value="PK">Pakistan', false);

        Http::fake([self::ENDPOINT => Http::response($this->response())]);
        $this->postWithCsrf(route('admin.license-verification.verify-pmdc'), [
            'license_number' => '75960-P',
        ], $admin)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('official_data.registration_number', '75960-P')
            ->assertJsonPath('official_data.registration_type', 'Permanent')
            ->assertJsonPath('official_data.registration_date', '18/03/2015')
            ->assertJsonPath('official_data.valid_upto', '30/12/2029')
            ->assertJsonPath('official_data.status', 'ACTIVE');
    }

    private function postWithCsrf(string $url, array $data, ?User $user = null)
    {
        if ($user) {
            $this->actingAs($user);
        }

        return $this->withSession(['_token' => 'test-token'])
            ->post($url, $data, [
                'Accept' => 'application/json',
                'X-CSRF-TOKEN' => 'test-token',
            ]);
    }

    private function application(): array
    {
        return [
            'name' => 'Doctor Applicant',
            'email' => 'pmdc@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '03001234567',
            'specialization' => 'General Medicine',
            'experience' => 8,
            'fees' => 125,
            'clinic_name' => 'Lahore Clinic',
            'address' => 'Lahore',
            'license_country' => 'PK',
            'license_state' => 'PK',
            'license_number' => '75960-P',
            'certificate' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf'),
        ];
    }

    private function response(array $overrides = []): array
    {
        return array_merge([
            'status' => true,
            'data' => [$this->record()],
            'message' => '1 Records Found!',
        ], $overrides);
    }

    private function record(array $overrides = []): array
    {
        return array_merge([
            'RegistrationNo' => '75960-P',
            'Name' => 'BILAL ZAHOOR',
            'FatherName' => 'ZAHOOR HUSSAIN TOOR',
            'RegistrationType' => 'Permanent',
            'RegistrationDate' => '18/03/2015',
            'ValidUpto' => '30/12/2029',
            'Status' => 'ACTIVE',
        ], $overrides);
    }
}
