<?php

namespace Tests\Unit;

use App\Services\DoctorLicenseLookupService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DoctorLicenseLookupServiceTest extends TestCase
{
    public function test_lookup_fetches_state_page_then_posts_search_with_fresh_csrf_token(): void
    {
        config([
            'doctor_lookup.enabled' => true,
            'doctor_lookup.base_url' => 'https://doctorlicenselookup.test',
            'doctor_lookup.verify_ssl' => true,
        ]);

        Http::fake([
            'https://doctorlicenselookup.test/state/florida' => Http::response(
                '<html><meta name="csrf-token" content="fresh-token"></html>'
            ),
            'https://doctorlicenselookup.test/florida/search' => Http::response([
                'success' => true,
                'data' => [
                    'license_no' => '12345',
                    'name' => 'Alex Smith',
                ],
            ]),
        ]);

        $result = app(DoctorLicenseLookupService::class)->verify('US', 'FL', '12345');

        $this->assertSame('verified', $result['status']);
        $this->assertSame('Alex Smith', $result['official_data']['name']);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://doctorlicenselookup.test/state/florida');
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://doctorlicenselookup.test/florida/search'
            && $request->data() === [
                '_token' => 'fresh-token',
                'license_no' => '12345',
                'state_select' => 'florida',
                'page' => 1,
                'per_page' => 10,
            ]
            && $request->hasHeader('X-CSRF-TOKEN', 'fresh-token'));
    }

    public function test_empty_search_results_are_reported_as_no_result_not_manual_review(): void
    {
        config([
            'doctor_lookup.enabled' => true,
            'doctor_lookup.base_url' => 'https://doctorlicenselookup.test',
        ]);

        Http::fake([
            'https://doctorlicenselookup.test/state/florida' => Http::response(
                '<html><meta name="csrf-token" content="fresh-token"></html>'
            ),
            'https://doctorlicenselookup.test/florida/search' => Http::response([
                'success' => false,
                'no_results' => true,
                'error_message' => 'No results found',
                'search_activity_id' => 'activity-123',
            ]),
        ]);

        $result = app(DoctorLicenseLookupService::class)->verify('US', 'FL', 'missing-license');

        $this->assertSame('not_found', $result['status']);
        $this->assertSame(
            'License record not found. Please check the license number and selected state.',
            $result['message']
        );
        $this->assertSame([], $result['official_data']);
    }

    public function test_masked_license_number_requires_manual_review(): void
    {
        config([
            'doctor_lookup.enabled' => true,
            'doctor_lookup.base_url' => 'https://doctorlicenselookup.test',
        ]);

        Http::fake([
            'https://doctorlicenselookup.test/state/florida' => Http::response(
                '<html><meta name="csrf-token" content="fresh-token"></html>'
            ),
            'https://doctorlicenselookup.test/florida/search' => Http::response([
                'success' => true,
                'data' => ['license_no' => '*****2345'],
            ]),
        ]);

        $result = app(DoctorLicenseLookupService::class)->verify('US', 'FL', '12345');

        $this->assertSame('manual_review', $result['status']);
        $this->assertFalse($result['success']);
    }

    public function test_single_active_record_with_masked_number_is_verified_using_submitted_number(): void
    {
        config([
            'doctor_lookup.enabled' => true,
            'doctor_lookup.base_url' => 'https://doctorlicenselookup.test',
        ]);

        Http::fake([
            'https://doctorlicenselookup.test/state/washington' => Http::response(
                '<html><meta name="csrf-token" content="fresh-token"></html>'
            ),
            'https://doctorlicenselookup.test/washington/search' => Http::response([
                'success' => true,
                'data' => [[
                    'Full Name' => 'John Sweeney',
                    'Lic No' => 'MD.**********',
                    'Lic Status' => 'Active',
                ]],
            ]),
        ]);

        $result = app(DoctorLicenseLookupService::class)->verify('US', 'WA', 'MD.MD.61641953');

        $this->assertSame('verified', $result['status']);
        $this->assertTrue($result['success']);
        $this->assertSame('MD.MD.61641953', $result['official_data']['verified_license_number']);
        $this->assertSame('John Sweeney', $result['official_data']['Full Name']);
        $this->assertArrayNotHasKey('Lic No', $result['official_data']);
        $this->assertArrayNotHasKey(0, $result['official_data']);
    }

    public function test_active_status_slug_with_masked_number_is_verified(): void
    {
        config([
            'doctor_lookup.enabled' => true,
            'doctor_lookup.base_url' => 'https://doctorlicenselookup.test',
        ]);

        Http::fake([
            'https://doctorlicenselookup.test/state/new-hampshire' => Http::response(
                '<html><meta name="csrf-token" content="fresh-token"></html>'
            ),
            'https://doctorlicenselookup.test/new-hampshire/search' => Http::response([
                'success' => true,
                'data' => [[
                    'Full Name' => 'John A Ditri',
                    'Lic No' => '114**',
                    'Lic Status Slug' => 'status-active',
                ]],
            ]),
        ]);

        $result = app(DoctorLicenseLookupService::class)->verify('US', 'NH', '11444');

        $this->assertSame('verified', $result['status']);
        $this->assertSame('11444', $result['official_data']['verified_license_number']);
        $this->assertArrayNotHasKey('Lic No', $result['official_data']);
    }

    public function test_nested_active_record_with_prefixed_masked_number_is_verified_and_flattened(): void
    {
        config([
            'doctor_lookup.enabled' => true,
            'doctor_lookup.base_url' => 'https://doctorlicenselookup.test',
        ]);

        Http::fake([
            'https://doctorlicenselookup.test/state/new-hampshire' => Http::response(
                '<html><meta name="csrf-token" content="fresh-token"></html>'
            ),
            'https://doctorlicenselookup.test/new-hampshire/search' => Http::response([
                'success' => true,
                'data' => [
                    'records' => [[
                        '0 / Full Name' => 'Jordan Doctor',
                        '0 / Lic No' => '11***',
                        '0 / Lic Status' => 'Active',
                    ]],
                ],
            ]),
        ]);

        $result = app(DoctorLicenseLookupService::class)->verify('US', 'NH', '11444');

        $this->assertSame('verified', $result['status']);
        $this->assertSame('Jordan Doctor', $result['official_data']['Full Name']);
        $this->assertSame('11444', $result['official_data']['verified_license_number']);
        $this->assertArrayNotHasKey('0 / Lic No', $result['official_data']);
        $this->assertArrayNotHasKey('Lic No', $result['official_data']);
        $this->assertArrayNotHasKey('records', $result['official_data']);
    }

    public function test_masked_license_with_an_active_status_is_verified_even_with_other_state_results(): void
    {
        config([
            'doctor_lookup.enabled' => true,
            'doctor_lookup.base_url' => 'https://doctorlicenselookup.test',
        ]);

        Http::fake([
            'https://doctorlicenselookup.test/state/ohio' => Http::response(
                '<html><meta name="csrf-token" content="fresh-token"></html>'
            ),
            'https://doctorlicenselookup.test/ohio/search' => Http::response([
                'success' => true,
                'data' => [
                    'records' => [
                        ['License Number' => 'OH*****', 'License Status' => 'Active'],
                        ['License Number' => 'OH*****', 'License Status' => 'Expired'],
                    ],
                ],
            ]),
        ]);

        $result = app(DoctorLicenseLookupService::class)->verify('US', 'OH', 'OH12345');

        $this->assertSame('verified', $result['status']);
        $this->assertSame('OH12345', $result['official_data']['verified_license_number']);
    }

    public function test_masked_number_without_an_active_record_still_requires_manual_review(): void
    {
        config([
            'doctor_lookup.enabled' => true,
            'doctor_lookup.base_url' => 'https://doctorlicenselookup.test',
        ]);

        Http::fake([
            'https://doctorlicenselookup.test/state/washington' => Http::response(
                '<html><meta name="csrf-token" content="fresh-token"></html>'
            ),
            'https://doctorlicenselookup.test/washington/search' => Http::response([
                'success' => true,
                'data' => [[
                    'Lic No' => 'MD.**********',
                    'Lic Status' => 'Inactive',
                ]],
            ]),
        ]);

        $result = app(DoctorLicenseLookupService::class)->verify('US', 'WA', 'MD.MD.61641953');

        $this->assertSame('manual_review', $result['status']);
        $this->assertFalse($result['success']);
    }

    public function test_success_response_without_license_number_requires_manual_review(): void
    {
        config([
            'doctor_lookup.enabled' => true,
            'doctor_lookup.base_url' => 'https://doctorlicenselookup.test',
        ]);

        Http::fake([
            'https://doctorlicenselookup.test/state/florida' => Http::response(
                '<html><meta name="csrf-token" content="fresh-token"></html>'
            ),
            'https://doctorlicenselookup.test/florida/search' => Http::response([
                'success' => true,
                'data' => ['name' => 'Alex Smith'],
            ]),
        ]);

        $result = app(DoctorLicenseLookupService::class)->verify('US', 'FL', '12345');

        $this->assertSame('manual_review', $result['status']);
        $this->assertFalse($result['success']);
    }
}
