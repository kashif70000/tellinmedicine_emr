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
}
