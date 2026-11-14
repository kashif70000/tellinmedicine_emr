<?php

namespace Tests\Feature;

use App\Services\TanzaniaLicenseVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TanzaniaLicenseVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://hprs.moh.go.tz/api/user_management/search-user/';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        config([
            'tanzania.enabled' => true,
            'tanzania.base_url' => self::ENDPOINT,
            'tanzania.council_uuid' => '6904e90f-5df9-4e7e-b681-a4f2044ef042',
            'tanzania.search_type' => 'mct_number',
            'tanzania.timeout' => 20,
            'tanzania.connect_timeout' => 10,
        ]);
    }

    public function test_matching_active_mct_record_is_verified_for_registration_flow(): void
    {
        Http::fake([self::ENDPOINT => Http::response([$this->record()])]);

        $response = $this->postJson(route('register.doctor.store'), $this->application());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('verification_pending', true);

        $this->assertDatabaseHas('doctors', [
            'license_state' => 'TZ',
            'license_number' => 'MCT12241',
            'license_type' => 'Tanzania MCT Registration',
            'license_verification_status' => 'verified',
            'verification_status' => 'pending',
            'is_verified' => false,
        ]);
        $this->assertDatabaseHas('license_verification_attempts', [
            'license_state' => 'TZ',
            'license_number' => 'MCT12241',
            'status' => 'verified',
        ]);
    }

    public function test_expired_registration_is_not_accepted(): void
    {
        Http::fake([self::ENDPOINT => Http::response([$this->record([
            'registration' => [[
                'registration_number' => 'MCT12241',
                'application_status' => 'Approved',
                'expire_date' => now()->subDay()->toDateString(),
                'expired' => true,
            ]],
        ])])]);

        $result = app(TanzaniaLicenseVerificationService::class)->verify('MCT12241');

        $this->assertSame('expired', $result['status']);
        $this->assertSame('MCT12241', $result['official_data']['registration_number']);
    }

    public function test_pending_and_rejected_applications_return_non_verified_statuses(): void
    {
        $service = app(TanzaniaLicenseVerificationService::class);

        Http::fake([self::ENDPOINT => Http::response([$this->record([
            'registration' => [[
                'registration_number' => 'MCT12241',
                'application_status' => 'Pending',
                'expire_date' => now()->addYear()->toDateString(),
                'expired' => false,
            ]],
        ])])]);
        $this->assertSame('pending', $service->verify('MCT12241')['status']);

        Http::fake([self::ENDPOINT => Http::response([$this->record([
            'registration' => [[
                'registration_number' => 'MCT12241',
                'application_status' => 'Rejected',
                'expire_date' => now()->addYear()->toDateString(),
                'expired' => false,
            ]],
        ])])]);
        $this->assertSame('rejected', $service->verify('MCT12241')['status']);
    }

    public function test_missing_registration_record_is_manual_review(): void
    {
        Http::fake([self::ENDPOINT => Http::response([$this->record(['registration' => []])])]);

        $this->assertSame('manual_review', app(TanzaniaLicenseVerificationService::class)->verify('MCT12241')['status']);
    }

    public function test_non_physician_record_is_rejected(): void
    {
        Http::fake([self::ENDPOINT => Http::response([$this->record([
            'user_profession' => [[
                'profession' => [
                    'profession_name' => 'Nurse',
                    'profession_code' => 'RN',
                    'profession_short_name' => 'RN',
                ],
            ]],
        ])])]);

        $this->assertSame('failed', app(TanzaniaLicenseVerificationService::class)->verify('MCT12241')['status']);
    }

    public function test_not_found_timeout_invalid_json_and_unexpected_structures_are_handled(): void
    {
        $service = app(TanzaniaLicenseVerificationService::class);

        Http::fake([self::ENDPOINT => Http::response([])]);
        $this->assertSame('not_found', $service->verify('MCT12241')['status']);

        Http::fake([self::ENDPOINT => function () {
            throw new ConnectionException('Simulated timeout');
        }]);
        $this->assertSame('unable_to_verify', $service->verify('MCT12241')['status']);

        Http::fake([self::ENDPOINT => Http::response('not-json', 200, ['Content-Type' => 'application/json'])]);
        $this->assertSame('unable_to_verify', $service->verify('MCT12241')['status']);

        Http::fake([self::ENDPOINT => Http::response(['error' => 'bad'], 500)]);
        $this->assertSame('unavailable', $service->verify('MCT12241')['status']);
    }

    private function application(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Tanzania Doctor',
            'email' => 'tanzania-doctor@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '+255700000000',
            'specialization' => 'General Medicine',
            'experience' => 6,
            'fees' => 150,
            'clinic_name' => 'Dar Clinic',
            'address' => 'Dar es Salaam',
            'license_country' => 'TZ',
            'license_state' => 'TZ',
            'license_number' => 'MCT12241',
            'certificate' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf'),
        ], $overrides);
    }

    private function record(array $overrides = []): array
    {
        return array_merge([
            'id' => '5260f20d-4229-4269-a4cd-ad77f25dcb6b',
            'first_name' => 'Aisha',
            'middle_name' => 'Aminu',
            'last_name' => 'Muhammad',
            'get_full_name' => 'Aisha Aminu Muhammad',
            'sex' => 'F',
            'nationality' => 'NG',
            'profile_picture' => 'https://hprs.moh.go.tz/media/images/profile_pictures/55382.jpg',
            'user_profession' => [[
                'user' => '5260f20d-4229-4269-a4cd-ad77f25dcb6b',
                'profession' => [
                    'profession_name' => 'Medical Doctor(MD/MBBS/MBChB)',
                    'profession_short_name' => 'MD',
                    'profession_code' => 'MD',
                ],
                'user_profession_type' => 'main',
            ]],
            'registration' => [[
                'registration_number' => 'MCT12241',
                'application_status' => 'Approved',
                'expire_date' => now()->addYear()->toDateString(),
                'expired' => false,
                'created_at' => now()->toIso8601String(),
                'approved_at' => now()->subMonths(2)->toIso8601String(),
                'service_name' => 'Provisional Registration(Foreigners)',
                'start_date' => null,
                'end_date' => null,
            ]],
            'licences' => [],
            'professional_qualifications' => [[
                'course_name' => [
                    'program_name' => 'Doctor of Medicine',
                    'program_name_short_name' => 'MD',
                    'category' => 'Basic',
                    'education_level' => 'Bachelor',
                ],
                'qualification_level' => [
                    'qualification_level_name' => 'Bachelor',
                ],
                'qualification_type' => [
                    'qualification_type_name' => 'Basic Qualification',
                ],
                'study_country' => [
                    'name' => 'Ukraine',
                    'country_code' => 'UA',
                ],
                'institution_or_university_name_other' => 'DNIPRO MEDICAL INSTITUTE',
                'start_year' => 2015,
                'end_year' => 2021,
                'issue_year' => 2022,
                'is_verified' => false,
            ]],
            'caveat' => [],
        ], $overrides);
    }
}
