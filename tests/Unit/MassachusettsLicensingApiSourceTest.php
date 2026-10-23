<?php

namespace Tests\Unit;

use App\Services\MassachusettsLicenseVerificationService;
use App\Services\MassachusettsLicensingApiSource;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MassachusettsLicensingApiSourceTest extends TestCase
{
    private const ENDPOINT = 'https://api.medboard.mass.gov/api-public/search';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.massachusetts_license.endpoint_url' => self::ENDPOINT]);
    }

    private function record(array $overrides = []): array
    {
        return array_merge([
            'licenseNumber' => '245843',
            'licenseMetaId' => 17,
            'licenseMetaName' => 'Physician Full License',
            'profileStatus' => 'Active',
            'expirationDate' => now()->addYear()->endOfDay()->toIso8601String(),
            'fullName' => 'Jasper I Ngomba',
            'originalDate' => '2012-05-10T04:00:00.000Z',
            'startDate' => '2012-05-10T04:00:00.000Z',
            'degree' => 'MD',
            'specialties' => [],
        ], $overrides);
    }

    private function apiResponse(array $records): array
    {
        return ['results' => ['data' => $records, 'totalDataCount' => count($records)]];
    }

    private function application(array $overrides = []): array
    {
        return array_merge([
            'license_state' => 'MA',
            'license_type' => 'Full Physician',
            'license_number' => '245843',
            'license_issue_date' => '2012-05-10',
            'license_expiration_date' => now()->addYear()->toDateString(),
            'name' => 'Jasper I Ngomba',
        ], $overrides);
    }

    private function verify(array $records): array
    {
        Http::fake([self::ENDPOINT => Http::response($this->apiResponse($records))]);

        return (new MassachusettsLicenseVerificationService(new MassachusettsLicensingApiSource()))
            ->verify($this->application());
    }

    public function test_it_posts_the_exact_search_contract_and_maps_the_official_record(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->apiResponse([$this->record()]))]);
        $record = (new MassachusettsLicensingApiSource())->find('Full Physician', '245843');

        $this->assertSame('245843', $record['license_number']);
        $this->assertSame('Physician Full License', $record['license_type']);
        $this->assertSame('Active', $record['status']);
        $this->assertSame('2012-05-10T04:00:00.000Z', $record['issue_date']);
        $this->assertSame('2012-05-10T04:00:00.000Z', $record['original_issue_date']);
        Http::assertSent(fn (ClientRequest $request) =>
            $request->method() === 'POST'
            && $request->url() === self::ENDPOINT
            && $request->hasHeader('Accept', 'application/json')
            && $request->hasHeader('Content-Type', 'application/json')
            && $request->data() === [
                'licenseMetaId' => null,
                'licenseNumber' => '245843',
                'searchType' => 'BY_LICENSE_NUMBER',
                'showOnlyAuthorizedForHerbalTherapy' => false,
            ]
        );
    }

    public function test_active_unexpired_physician_license_verifies(): void
    {
        $this->assertSame('verified', $this->verify([$this->record()])['status']);
    }

    public function test_expired_license_is_rejected(): void
    {
        $result = $this->verify([$this->record(['expirationDate' => now()->subDay()->toIso8601String()])]);

        $this->assertSame('expired', $result['status']);
    }

    public function test_inactive_license_is_rejected(): void
    {
        $result = $this->verify([$this->record(['profileStatus' => 'Inactive'])]);

        $this->assertSame('failed', $result['status']);
    }

    public function test_no_matching_license_is_a_definitive_failure(): void
    {
        $result = $this->verify([]);

        $this->assertSame('failed', $result['status']);
    }

    public function test_mismatched_returned_license_number_is_not_accepted(): void
    {
        $result = $this->verify([$this->record(['licenseNumber' => '999999'])]);

        $this->assertSame('failed', $result['status']);
    }

    public function test_unsupported_license_type_is_rejected(): void
    {
        $result = $this->verify([$this->record(['licenseMetaName' => 'Acupuncture Full License'])]);

        $this->assertSame('failed', $result['status']);
    }

    public function test_missing_or_invalid_expiration_date_cannot_verify(): void
    {
        $missing = $this->verify([$this->record(['expirationDate' => null])]);
        $invalid = $this->verify([$this->record(['expirationDate' => 'not-a-date'])]);

        $this->assertSame('unable_to_verify', $missing['status']);
        $this->assertSame('unable_to_verify', $invalid['status']);
    }

    public function test_returned_latest_issue_date_is_checked_when_available(): void
    {
        $mismatched = $this->verify([$this->record(['startDate' => '2013-05-10T04:00:00.000Z'])]);

        $this->assertSame('failed', $mismatched['status']);
    }

    public function test_original_issue_date_does_not_override_latest_issue_date(): void
    {
        $result = $this->verify([$this->record(['originalDate' => '2010-09-28T04:00:00.000Z'])]);

        $this->assertSame('verified', $result['status']);
    }

    public function test_malformed_returned_latest_issue_date_is_unavailable(): void
    {
        $malformed = $this->verify([$this->record(['startDate' => 'not-a-date'])]);

        $this->assertSame('unable_to_verify', $malformed['status']);
    }

    public function test_api_connection_failure_is_unavailable_not_invalid(): void
    {
        Http::fake([self::ENDPOINT => Http::failedConnection()]);
        $result = (new MassachusettsLicenseVerificationService(new MassachusettsLicensingApiSource()))
            ->verify($this->application());

        $this->assertSame('unable_to_verify', $result['status']);
    }

    public function test_api_http_error_is_unavailable_not_invalid(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['message' => 'Service unavailable'], 503)]);
        $result = (new MassachusettsLicenseVerificationService(new MassachusettsLicensingApiSource()))
            ->verify($this->application());

        $this->assertSame('unable_to_verify', $result['status']);
    }

    public function test_malformed_or_unexpected_response_is_unavailable(): void
    {
        Http::fake([self::ENDPOINT => Http::response('not-json', 200)]);
        $malformed = (new MassachusettsLicenseVerificationService(new MassachusettsLicensingApiSource()))
            ->verify($this->application());

        Http::fake([self::ENDPOINT => Http::response(['unexpected' => ['shape' => []]], 200)]);
        $unexpected = (new MassachusettsLicenseVerificationService(new MassachusettsLicensingApiSource()))
            ->verify($this->application());

        $this->assertSame('unable_to_verify', $malformed['status']);
        $this->assertSame('unable_to_verify', $unexpected['status']);
    }

    public function test_multiple_records_are_searched_for_an_exact_number(): void
    {
        $result = $this->verify([
            $this->record(['licenseNumber' => '111111']),
            $this->record(),
        ]);

        $this->assertSame('verified', $result['status']);
    }

    public function test_name_discrepancy_is_routed_to_manual_review(): void
    {
        $result = $this->verify([$this->record(['fullName' => 'Jasper N Ngomba'])]);

        $this->assertSame('manual_review', $result['status']);
    }
}