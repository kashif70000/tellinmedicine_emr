<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\User;
use App\Services\GhanaGmrLicenseVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GhanaGmrLicenseVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://ghana-gmr.test/rest/v1/rpc/gmr_public_verify';

    private const KEY = 'sb_publishable_test_key';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        config([
            'ghana_gmr.base_url' => 'https://ghana-gmr.test',
            'ghana_gmr.publishable_key' => self::KEY,
            'ghana_gmr.timeout' => 20,
            'ghana_gmr.connect_timeout' => 10,
        ]);
    }

    public function test_matching_active_mdc_doctor_record_is_manual_review_not_automatic_approval(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->record())]);

        $response = $this->postJson(route('register.doctor.store'), $this->application());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('verification_pending', true);
        $this->assertDatabaseHas('doctors', [
            'license_state' => 'GH',
            'license_number' => 'MDC-2019-0042',
            'license_type' => 'Ghana MDC Registration',
            'license_verification_status' => 'manual_review',
            'official_license_status' => 'active',
            'official_license_expiration_date' => now()->addYear()->toDateString(),
            'license_verification_source' => 'Ghana Medical Registry public credential lookup',
            'is_verified' => false,
            'verification_status' => 'pending',
        ]);
        $this->assertDatabaseHas('license_verification_attempts', [
            'license_state' => 'GH',
            'license_number' => 'MDC-2019-0042',
            'status' => 'manual_review',
        ]);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === self::ENDPOINT
            && $request->data() === ['p_query' => 'MDC-2019-0042']
            && $request->hasHeader('apikey', self::KEY)
            && $request->hasHeader('Authorization', 'Bearer '.self::KEY));
    }

    public function test_registration_verification_endpoint_returns_the_same_manual_review_result(): void
    {
        $this->get(route('register.doctor'))
            ->assertOk()
            ->assertSee('<option value="GH">Ghana</option>', false);
        Http::fake([self::ENDPOINT => Http::response($this->record())]);

        $this->postJson(route('register.doctor.verify-license'), [
            'license_country' => 'GH',
            'license_state' => 'GH',
            'license_number' => 'MDC-2019-0042',
        ])->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 'manual_review')
            ->assertJsonPath('official_data.council_code', 'MDC')
            ->assertJsonPath('official_data.licence_type', 'MDC-DOCTOR');
    }

    public function test_registration_number_matching_is_case_and_separator_normalized(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->record([
            'currentLicenceNumber' => 'MDC-2019-0042',
            'licenceNumber' => 'MDC-2019-0042',
        ]))]);

        $result = app(GhanaGmrLicenseVerificationService::class)->verify(' mdc 2019 0042 ');

        $this->assertSame('manual_review', $result['status']);
        $this->assertSame('MDC-2019-0042', $result['official_data']['registration_number']);
    }

    public function test_unknown_registry_record_is_inconclusive_and_does_not_complete_registration(): void
    {
        Http::fake([self::ENDPOINT => Http::response([
            'kind' => 'NONE',
            'found' => false,
        ])]);

        $this->postJson(route('register.doctor.store'), $this->application())
            ->assertUnprocessable()
            ->assertJsonPath('status', 'unable_to_verify');

        $this->assertDatabaseMissing('users', ['email' => 'ghana-doctor@example.com']);
        $this->assertDatabaseHas('license_verification_attempts', [
            'license_state' => 'GH',
            'status' => 'unable_to_verify',
        ]);
    }

    public function test_ghana_registration_cannot_skip_the_required_certificate(): void
    {
        $application = $this->application(['skip' => '1']);
        unset($application['certificate']);
        Http::fake();

        $this->postJson(route('register.doctor.store'), $application)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('certificate');

        Http::assertNothingSent();
        $this->assertDatabaseMissing('users', ['email' => 'ghana-doctor@example.com']);
    }

    public function test_changing_a_ghana_license_resets_verification_and_deactivates_the_account(): void
    {
        $user = $this->user('doctor');
        $doctor = Doctor::create([
            'user_id' => $user->id,
            'license_state' => 'GH',
            'license_number' => 'MDC-2019-0042',
            'license_verification_status' => 'manual_review',
            'official_license_status' => 'active',
            'official_license_issue_date' => '2025-01-01',
            'official_license_expiration_date' => '2027-01-01',
            'license_verification_data' => ['registration_number' => 'MDC-2019-0042'],
            'license_verification_source' => 'Ghana Medical Registry public credential lookup',
            'license_verification_message' => 'Previously checked.',
            'license_verified_at' => now(),
            'is_verified' => true,
            'verification_status' => 'approved',
        ]);

        $this->actingAs($user)
            ->post(route('doctor.updateVerification'), [
                'license_number' => 'MDC-2020-1234',
                'certificate' => UploadedFile::fake()->create('updated-certificate.pdf', 20, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $doctor->refresh();
        $this->assertSame('MDC-2020-1234', $doctor->license_number);
        $this->assertSame('pending', $doctor->verification_status);
        $this->assertFalse($doctor->is_verified);
        $this->assertSame('pending', $doctor->license_verification_status);
        $this->assertNull($doctor->official_license_status);
        $this->assertNull($doctor->official_license_issue_date);
        $this->assertNull($doctor->official_license_expiration_date);
        $this->assertNull($doctor->license_verification_data);
        $this->assertNull($doctor->license_verification_source);
        $this->assertSame('License details changed and require a fresh verification.', $doctor->license_verification_message);
        $this->assertNull($doctor->license_verified_at);
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_registry_verification_is_available_to_admin_and_is_logged(): void
    {
        $admin = $this->user('admin');
        Http::fake([self::ENDPOINT => Http::response($this->record())]);

        $this->actingAs($admin)
            ->get(route('admin.license-verification.index'))
            ->assertOk()
            ->assertSee('<option value="GH">Ghana</option>', false);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'GH',
                'license_number' => 'MDC-2019-0042',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 'manual_review')
            ->assertJsonPath('source', 'Ghana Medical Registry public credential lookup')
            ->assertJsonPath('official_data.expiry_date', now()->addYear()->toDateString())
            ->assertJsonStructure(['checked_at']);
        $this->assertDatabaseHas('license_verification_attempts', [
            'actor_user_id' => $admin->id,
            'license_state' => 'GH',
            'license_number' => 'MDC-2019-0042',
            'status' => 'manual_review',
        ]);
    }

    public function test_admin_recheck_of_a_ghana_doctor_uses_the_same_service_without_approving(): void
    {
        $admin = $this->user('admin');
        $doctorUser = $this->user('doctor');
        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'license_state' => 'GH',
            'license_number' => 'MDC-2019-0042',
            'is_verified' => true,
            'verification_status' => 'approved',
        ]);
        Http::fake([self::ENDPOINT => Http::response($this->record())]);

        $this->actingAs($admin)
            ->postJson(route('admin.doctors.license.verify', $doctor))
            ->assertUnprocessable()
            ->assertJsonPath('status', 'manual_review');

        $this->assertDatabaseHas('doctors', [
            'id' => $doctor->id,
            'license_verification_status' => 'manual_review',
            'is_verified' => false,
            'verification_status' => 'pending',
        ]);
    }

    public function test_non_doctor_professions_are_rejected_even_when_their_records_are_active(): void
    {
        $responses = [
            $this->record(['councilCode' => 'MDC', 'licenceType' => 'MDC-MEDICAL-STUDENT']),
            $this->record(['councilCode' => 'MDC', 'licenceType' => 'MDC-DENTIST']),
            $this->record(['councilCode' => 'TMPC', 'licenceType' => 'TMPC-HERBAL']),
        ];
        Http::fake([self::ENDPOINT => function () use (&$responses) {
            return Http::response(array_shift($responses));
        }]);
        $service = new GhanaGmrLicenseVerificationService;

        for ($index = 0; $index < 3; $index++) {
            $this->assertSame('failed', $service->verify('MDC-2019-0042')['status']);
        }
    }

    public function test_expired_and_inactive_records_are_rejected(): void
    {
        $service = new GhanaGmrLicenseVerificationService;

        $responses = [
            $this->record(['expiryDate' => now()->subDay()->toDateString()]),
            $this->record(['status' => 'inactive', 'isActive' => false]),
        ];
        Http::fake([self::ENDPOINT => function () use (&$responses) {
            return Http::response(array_shift($responses));
        }]);

        $this->assertSame('failed', $service->verify('MDC-2019-0042')['status']);
        $this->assertSame('failed', $service->verify('MDC-2019-0042')['status']);
    }

    public function test_number_mismatch_missing_number_and_ambiguous_records_are_not_verified(): void
    {
        $service = new GhanaGmrLicenseVerificationService;

        $responses = [
            $this->record(['currentLicenceNumber' => 'MDC-2019-9999', 'licenceNumber' => 'MDC-2019-9999']),
            $this->record(['currentLicenceNumber' => null, 'licenceNumber' => null]),
            [$this->record(), $this->record()],
        ];
        Http::fake([self::ENDPOINT => function () use (&$responses) {
            return Http::response(array_shift($responses));
        }]);

        $this->assertSame('failed', $service->verify('MDC-2019-0042')['status']);
        $this->assertSame('unable_to_verify', $service->verify('MDC-2019-0042')['status']);
        $this->assertSame('manual_review', $service->verify('MDC-2019-0042')['status']);
    }

    public function test_http_errors_timeouts_invalid_json_and_incomplete_responses_are_inconclusive(): void
    {
        $service = new GhanaGmrLicenseVerificationService;
        $responseNumber = 0;
        Http::fake([self::ENDPOINT => function () use (&$responseNumber) {
            $responseNumber++;
            if ($responseNumber === 1) {
                return Http::response(['error' => 'unavailable'], 503);
            }
            if ($responseNumber === 2) {
                throw new ConnectionException('Simulated connection timeout.');
            }
            if ($responseNumber === 3) {
                return Http::response('not-json', 200, ['Content-Type' => 'application/json']);
            }
            if ($responseNumber === 4) {
                return Http::response(['kind' => 'LICENCE']);
            }

            return Http::response('not found', 404);
        }]);

        for ($index = 0; $index < 5; $index++) {
            $this->assertSame('unable_to_verify', $service->verify('MDC-2019-0042')['status']);
        }
    }

    public function test_missing_registration_number_and_unconfigured_endpoint_do_not_send_requests(): void
    {
        $service = new GhanaGmrLicenseVerificationService;
        $this->assertSame('failed', $service->verify('')['status']);

        config(['ghana_gmr.publishable_key' => '']);
        Http::fake();
        $this->assertSame('unable_to_verify', $service->verify('MDC-2019-0042')['status']);
        Http::assertNothingSent();
    }

    private function application(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ghana Doctor',
            'email' => 'ghana-doctor@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '+233200000000',
            'specialization' => 'General Medicine',
            'experience' => 8,
            'fees' => 125,
            'clinic_name' => 'Accra Clinic',
            'address' => 'Accra',
            'license_country' => 'GH',
            'license_state' => 'GH',
            'license_number' => 'MDC-2019-0042',
            'certificate' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf'),
        ], $overrides);
    }

    private function record(array $overrides = []): array
    {
        return array_merge([
            'kind' => 'LICENCE',
            'found' => true,
            'practitionerName' => 'Test Ghana Doctor',
            'currentLicenceNumber' => 'MDC-2019-0042',
            'licenceNumber' => 'MDC-2019-0042',
            'council' => 'Medical and Dental Council',
            'councilCode' => 'MDC',
            'licenceType' => 'MDC-DOCTOR',
            'status' => 'active',
            'isActive' => true,
            'issueDate' => '2026-01-01',
            'expiryDate' => now()->addYear()->toDateString(),
            'regulatorName' => 'Medical and Dental Council',
            'authorityLabel' => 'COUNCIL_REGISTER',
            'verifiedAt' => now()->toIso8601String(),
        ], $overrides);
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $role.'-'.uniqid().'@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
