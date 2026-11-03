<?php

namespace Tests\Unit;

use App\Services\PmdcLicenseVerificationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PmdcLicenseVerificationServiceTest extends TestCase
{
    private const ENDPOINT = 'https://pmdc.test/api/DRC/GetData';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'pmdc.license_verification_url' => self::ENDPOINT,
            'pmdc.timeout' => 10,
        ]);
    }

    public function test_active_registration_is_verified_using_official_form_request_shape(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->response([
            'data' => [$this->record(['RegistrationNo' => '102496-P'])],
        ]))]);

        $result = app(PmdcLicenseVerificationService::class)->verify(' 102496-P ');

        $this->assertSame('verified', $result['status']);
        $this->assertSame('BILAL ZAHOOR', $result['official_data']['name']);
        $this->assertSame('Permanent', $result['official_data']['registration_type']);
        $this->assertSame('2015-03-18', $result['official_data']['issue_date']);
        $this->assertSame('2029-12-30', $result['official_data']['expiration_date']);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === self::ENDPOINT
            && $request->data() === [
                'RegistrationNo' => '102496-P',
                'Name' => '',
                'FatherName' => '',
            ]
            && $request->body() === 'RegistrationNo=102496-P&Name=&FatherName='
            && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8')
            && $request->hasHeader('Origin', 'https://pmdc.pk')
            && $request->hasHeader('Referer', 'https://pmdc.pk/'));
    }

    public function test_inactive_registration_is_not_verified(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->response([
            'data' => [$this->record(['Status' => 'INACTIVE'])],
        ]))]);

        $result = app(PmdcLicenseVerificationService::class)->verify('75960-P');

        $this->assertSame('inactive', $result['status']);
        $this->assertSame('INACTIVE', $result['official_data']['status']);
    }

    public function test_invalid_official_dates_do_not_block_an_active_registration(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->response([
            'data' => [$this->record([
                'RegistrationDate' => 'invalid',
                'ValidUpto' => '31/02/2029',
            ])],
        ]))]);

        $result = app(PmdcLicenseVerificationService::class)->verify('75960-P');

        $this->assertSame('verified', $result['status']);
        $this->assertNull($result['official_data']['issue_date']);
        $this->assertNull($result['official_data']['expiration_date']);
    }

    public function test_missing_record_is_reported_as_not_found(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->response([
            'status' => false,
            'data' => [],
            'message' => '0 Records Found!',
        ]))]);

        $result = app(PmdcLicenseVerificationService::class)->verify('missing-P');

        $this->assertSame('not_found', $result['status']);
    }

    public function test_pmdc_internal_errors_are_logged_and_not_reported_as_invalid_licenses(): void
    {
        Http::fake([self::ENDPOINT => Http::response([
            'status' => false,
            'message' => 'Something went wrong, contact administrator!',
            'error' => [
                'ClassName' => 'System.NullReferenceException',
                'StackTraceString' => 'Remote implementation details',
            ],
        ])]);
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'PMDC license verification API request failed'
                && $context === [
                    'http_status' => 200,
                    'sanitized_response_body' => [
                        'status' => false,
                        'message' => 'Something went wrong, contact administrator!',
                        'error_class' => 'System.NullReferenceException',
                    ],
                ]);

        $result = app(PmdcLicenseVerificationService::class)->verify('TEST-NOT-REAL');

        $this->assertSame('unavailable', $result['status']);
        $this->assertStringContainsString('does not confirm the registration is invalid', $result['message']);
        $this->assertNull($result['official_data']);
        $this->assertStringNotContainsString('Remote implementation details', json_encode($result));
    }

    public function test_invalid_parameters_are_reported_as_unavailable_not_invalid(): void
    {
        Http::fake([self::ENDPOINT => Http::response([
            'status' => false,
            'message' => 'Invalid Parameters',
        ])]);
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'PMDC license verification API request failed'
                && $context['http_status'] === 200
                && $context['sanitized_response_body']['message'] === 'Invalid Parameters');

        $result = app(PmdcLicenseVerificationService::class)->verify('75960-P');

        $this->assertSame('unavailable', $result['status']);
        $this->assertStringContainsString('registration-number-only', $result['message']);
        $this->assertNull($result['official_data']);
    }

    public function test_empty_registration_number_does_not_make_a_request(): void
    {
        Http::fake();

        $result = app(PmdcLicenseVerificationService::class)->verify('  ');

        $this->assertSame('failed', $result['status']);
        Http::assertNothingSent();
    }

    public function test_connection_failure_requires_manual_resolution(): void
    {
        Http::fake([
            self::ENDPOINT => static function () {
                throw new ConnectionException('simulated timeout');
            },
        ]);

        $result = app(PmdcLicenseVerificationService::class)->verify('75960-P');

        $this->assertSame('unable_to_verify', $result['status']);
    }

    public function test_http_error_and_invalid_payload_are_logged_without_exposing_raw_body(): void
    {
        Http::fake([
            self::ENDPOINT => Http::sequence()
                ->push([
                    'message' => 'Temporarily unavailable',
                    'token' => 'must-not-be-logged',
                ], 503)
                ->push(['status' => true, 'data' => 'unexpected']),
        ]);
        $loggedFailures = [];
        Log::shouldReceive('warning')
            ->twice()
            ->withArgs(function (string $message, array $context) use (&$loggedFailures): bool {
                $this->assertSame('PMDC license verification API request failed', $message);
                $loggedFailures[] = $context;

                return true;
            });

        $service = app(PmdcLicenseVerificationService::class);
        $this->assertSame('unavailable', $service->verify('75960-P')['status']);
        $this->assertSame('unable_to_verify', $service->verify('75960-P')['status']);
        $this->assertSame([
            [
                'http_status' => 503,
                'sanitized_response_body' => ['message' => 'Temporarily unavailable'],
            ],
            [
                'http_status' => 200,
                'sanitized_response_body' => ['status' => true],
            ],
        ], $loggedFailures);
    }

    public function test_successful_http_response_with_a_different_registration_is_not_verified(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->response([
            'data' => [$this->record(['RegistrationNo' => 'different-P'])],
        ]))]);

        $result = app(PmdcLicenseVerificationService::class)->verify('75960-P');

        $this->assertSame('not_found', $result['status']);
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
