<?php

namespace Tests\Unit;

use App\Services\USLicenseCheckVerificationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class USLicenseCheckVerificationServiceTest extends TestCase
{
    private const ENDPOINT = 'https://uslicensecheck.test/api/v1/lookup/verify';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'uslicensecheck.base_url' => 'https://uslicensecheck.test/api/v1',
            'uslicensecheck.api_key' => 'test-key-not-a-secret',
            'uslicensecheck.connect_timeout' => 2,
            'uslicensecheck.timeout' => 5,
            'uslicensecheck.supported_states' => ['OH', 'CT', 'CO', 'DE', 'TX', 'IL', 'NH', 'AK', 'WA'],
        ]);
    }

    public function test_matching_active_physician_license_is_verified_using_the_documented_request(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->record([
            'license_number' => '35.144499',
            'source_state' => 'OH',
            'state' => 'OH',
            'multiple' => null,
            'expiry_date' => null,
        ]))]);

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application([
            'license_number' => '35.144499',
        ]));

        $this->assertSame('verified', $result['status'], $result['message']);
        $this->assertSame('USLicenseCheck', $result['source']);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === self::ENDPOINT
            && $request->hasHeader('X-API-Key', 'test-key-not-a-secret')
            && $request->data() === [
                'license_number' => '35.144499',
                'source_state' => 'OH',
            ]);
    }

    public function test_matching_record_is_verified_even_when_multiple_is_explicitly_null(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->record([
            'license_number' => '35.144499',
            'source_state' => 'OH',
            'state' => 'OH',
            'multiple' => null,
        ]))]);

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application([
            'license_number' => '35.144499',
        ]));

        $this->assertSame('verified', $result['status']);
        $this->assertTrue($result['official_data']['found']);
        $this->assertNull($result['official_data']['multiple']);
    }

    public function test_all_nine_configured_states_are_sent_as_the_selected_source_state(): void
    {
        Http::fake([
            self::ENDPOINT => function (Request $request) {
                return Http::response($this->record([
                    'license_number' => $request->data()['license_number'],
                    'source_state' => $request->data()['source_state'],
                ]));
            },
        ]);

        foreach (['OH', 'CT', 'CO', 'DE', 'TX', 'IL', 'NH', 'AK', 'WA'] as $state) {
            $result = app(USLicenseCheckVerificationService::class)->verify($this->application([
                'license_state' => $state,
                'license_number' => $state . '123456',
            ]));

            $this->assertSame('verified', $result['status'], $result['message'] . ' (' . $state . ')');
            Http::assertSent(fn (Request $request) => $request->url() === self::ENDPOINT
                && $request->data() === [
                    'license_number' => $state . '123456',
                    'source_state' => $state,
                ]);
        }

        Http::assertSentCount(9);
    }

    public function test_license_number_lookup_does_not_require_or_send_applicant_name(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->record())]);

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application(['name' => null]));

        $this->assertSame('verified', $result['status'], $result['message']);
        Http::assertSent(fn (Request $request) => $request->data() === [
            'license_number' => 'OH123456',
            'source_state' => 'OH',
        ]);
    }

    public function test_no_match_is_distinguished_from_an_unavailable_provider(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response(['found' => false]),
        ]);

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application());

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('No matching license', $result['message']);
    }

    public function test_mismatched_numbers_and_states_are_not_verified(): void
    {
        $cases = [
            [['license_number' => 'OTHER123'], 'failed'],
            [['source_state' => 'CT'], 'failed'],
            [['state' => 'CT'], 'failed'],
        ];
        $sequence = Http::sequence();
        foreach ($cases as [$overrides]) {
            $sequence->push($this->record($overrides));
        }
        Http::fake([self::ENDPOINT => $sequence]);

        foreach ($cases as [$overrides, $expectedStatus]) {
            $result = app(USLicenseCheckVerificationService::class)->verify($this->application());

            $this->assertSame($expectedStatus, $result['status']);
        }
    }

    public function test_incomplete_matching_record_requires_manual_review(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response(array_diff_key($this->record(), ['source_state' => true])),
        ]);

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application());

        $this->assertSame('manual_review', $result['status']);
        $this->assertStringContainsString('omitted information', $result['message']);
    }

    public function test_genuine_multiple_record_response_requires_manual_review(): void
    {
        Http::fake([self::ENDPOINT => Http::response([
            'found' => true,
            'multiple' => [$this->record(), $this->record(['license_number' => 'OH654321'])],
        ])]);

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application());

        $this->assertSame('manual_review', $result['status']);
    }

    public function test_malformed_multiple_record_value_requires_manual_review(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->record(['multiple' => 'unknown']))]);

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application());

        $this->assertSame('manual_review', $result['status']);
    }

    public function test_non_md_license_types_do_not_qualify_for_verification(): void
    {
        $nonMdTypes = [
            'Registered Veterinary Technician',
            'Veterinarian',
            'Registered Nurse',
            'Dentist',
            'Pharmacist',
            'Physician Assistant',
            'Doctor of Osteopathic Medicine (DO)',
            'Unknown Medical License',
        ];
        $sequence = Http::sequence();
        foreach ($nonMdTypes as $licenseType) {
            $sequence->push($this->record(['license_type' => $licenseType]));
        }
        Http::fake([self::ENDPOINT => $sequence]);

        foreach ($nonMdTypes as $licenseType) {
            $result = app(USLicenseCheckVerificationService::class)->verify($this->application());

            $this->assertSame('failed', $result['status'], $licenseType);
            $this->assertSame('This license does not qualify for MD physician verification.', $result['message']);
        }
    }

    public function test_inactive_expired_and_unknown_license_statuses_are_not_verified(): void
    {
        $cases = [
            ['status' => 'Inactive', 'expected' => 'failed'],
            ['status' => 'Suspended', 'expected' => 'failed'],
            ['status' => 'Revoked', 'expected' => 'failed'],
            ['status' => 'Expired', 'expected' => 'expired'],
            ['status' => 'Unknown', 'expected' => 'manual_review'],
            ['expiry_date' => now()->subDay()->toDateString(), 'expected' => 'expired'],
            ['expiry_date' => 'not-a-date', 'expected' => 'manual_review'],
        ];
        $sequence = Http::sequence();
        foreach ($cases as $case) {
            $payload = $case;
            unset($payload['expected']);
            $sequence->push($this->record($payload));
        }
        Http::fake([self::ENDPOINT => $sequence]);

        foreach ($cases as $case) {
            $expected = $case['expected'];

            $result = app(USLicenseCheckVerificationService::class)->verify($this->application());

            $this->assertSame($expected, $result['status'], json_encode([$case, $result]));
        }
    }

    public function test_authentication_rate_limit_server_and_malformed_response_errors_are_safe(): void
    {
        $responses = [
            [['error' => 'unauthorized'], 401, 'unavailable'],
            [['error' => 'forbidden'], 403, 'unavailable'],
            [['error' => 'rate limited'], 429, 'unavailable'],
            [['error' => 'server error'], 503, 'unavailable'],
            ['invalid-json', 200, 'unable_to_verify'],
        ];
        $sequence = Http::sequence();
        foreach ($responses as [$body, $status]) {
            $sequence->push($body, $status, $status === 200 ? ['Content-Type' => 'application/json'] : []);
        }
        Http::fake([self::ENDPOINT => $sequence]);

        foreach ($responses as [, , $expectedStatus]) {
            $result = app(USLicenseCheckVerificationService::class)->verify($this->application());

            $this->assertSame($expectedStatus, $result['status'], $result['message']);
            $this->assertStringNotContainsString('test-key-not-a-secret', $result['message']);
        }
    }

    public function test_connection_failure_requires_manual_review_without_exposing_credentials(): void
    {
        Http::fake([
            self::ENDPOINT => static function () {
                throw new ConnectionException('simulated timeout');
            },
        ]);

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application());

        $this->assertSame('unable_to_verify', $result['status']);
        $this->assertStringNotContainsString('test-key-not-a-secret', $result['message']);
    }

    public function test_missing_api_key_does_not_make_a_request(): void
    {
        config(['uslicensecheck.api_key' => '']);
        Http::fake();

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application());

        $this->assertSame('unable_to_verify', $result['status']);
        Http::assertNothingSent();
    }

    private function application(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Alice Smith',
            'license_state' => 'OH',
            'license_number' => 'OH123456',
        ], $overrides);
    }

    private function record(array $overrides = []): array
    {
        return array_merge([
            'found' => true,
            'license_number' => 'OH123456',
            'board' => 'Medical Board',
            'license_type' => 'Doctor of Medicine (MD)',
            'status' => 'Active',
            'expiry_date' => now()->addYear()->toDateString(),
            'source_state' => 'OH',
        ], $overrides);
    }
}
