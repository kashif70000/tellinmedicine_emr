<?php

namespace Tests\Feature;

use App\Models\User;
use App\Http\Controllers\Admin\LicenseVerificationController;
use App\Services\GmcUkVerificationService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminLicenseVerificationTest extends TestCase
{
    private const ENDPOINT = 'https://uslicensecheck.test/api/v1/lookup/verify';

    private const MONTANA_SEARCH_PAGE = 'https://ebizws.mt.gov/PUBLICPORTAL/searchform?mylist=licenses';

    private const MONTANA_VERIFICATION = 'https://ebizws.mt.gov/PUBLICPORTAL/verif';

    private const MONTANA_SEARCH = 'https://ebizws.mt.gov/PUBLICPORTAL/searchform';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        config([
            'doctor_lookup.enabled' => false,
            'uslicensecheck.base_url' => 'https://uslicensecheck.test/api/v1',
            'uslicensecheck.api_key' => 'test-key-not-a-secret',
            'uslicensecheck.supported_states' => ['OH', 'CT', 'CO', 'DE', 'TX', 'IL', 'NH', 'AK', 'WA'],
            'uslicensecheck.montana_license_verification_url' => self::MONTANA_SEARCH_PAGE,
        ]);
    }

    public function test_admin_page_lists_all_doctor_license_lookup_states(): void
    {
        $request = HttpRequest::create('/admin/license-verification');
        $request->setUserResolver(fn () => $this->user('admin'));
        $view = app(LicenseVerificationController::class)->index($request);

        $this->assertSame('admin.license-verification', $view->name());
        $this->assertSame(
            array_map(static fn (array $state): string => $state['name'], config('doctor_lookup.states')),
            $view->getData()['states']
        );

        $this->actingAs($this->user('admin'))
            ->get(route('admin.license-verification.index'))
            ->assertOk()
            ->assertSee('<option value="MT">Montana (MT)</option>', false)
            ->assertSee('<option value="UK">United Kingdom</option>', false)
            ->assertDontSee('Doctor\'s Full Name')
            ->assertSee('id="license-number-label">GMC Registration Number', false);
    }

    public function test_admin_uk_verification_reuses_gmc_service_and_returns_official_physician_information(): void
    {
        $officialData = [
            'full_name' => 'Alice Smith',
            'gmc_number' => '1234567',
            'profile_url' => 'https://www.gmc-uk.org/registrants/1234567',
            'registration_status' => 'Registered',
            'licence_status' => 'Licensed to practise',
        ];
        $this->mock(GmcUkVerificationService::class)
            ->shouldReceive('verify')
            ->once()
            ->with([
                'gmc_registration_number' => '1234567',
            ])
            ->andReturn([
                'status' => 'verified',
                'message' => 'The GMC registration record was verified.',
                'source' => 'Test GMC Register',
                'official_data' => $officialData,
            ]);

        $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'UK',
                'license_number' => '1234567',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'verified')
            ->assertJsonPath('source', 'Test GMC Register')
            ->assertJsonPath('official_data.full_name', 'Alice Smith')
            ->assertJsonPath('official_data.gmc_number', '1234567');
    }

    public function test_admin_uk_verification_returns_not_found_without_claiming_success(): void
    {
        $this->mock(GmcUkVerificationService::class)
            ->shouldReceive('verify')
            ->once()
            ->andReturn([
                'status' => 'failed',
                'message' => 'No matching GMC registration record was found.',
                'source' => 'Test GMC Register',
                'official_data' => null,
            ]);

        $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'UK',
                'license_number' => '1234567',
            ])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('official_data', null);
    }

    public function test_admin_uk_verification_returns_unavailable_for_manual_review_without_claiming_success(): void
    {
        $this->mock(GmcUkVerificationService::class)
            ->shouldReceive('verify')
            ->once()
            ->andReturn([
                'status' => 'unavailable',
                'message' => 'GMC registration verification is currently unavailable.',
                'source' => 'Test GMC Register',
                'official_data' => null,
            ]);

        $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'UK',
                'license_number' => '1234567',
            ])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 'unavailable')
            ->assertJsonPath('official_data', null);
    }

    public function test_admin_uk_manual_review_result_is_not_reported_as_verified(): void
    {
        $this->mock(GmcUkVerificationService::class)
            ->shouldReceive('verify')
            ->once()
            ->andReturn([
                'status' => 'manual_review',
                'message' => 'The GMC record requires manual review.',
                'source' => 'Test GMC Register',
                'official_data' => ['full_name' => 'Alice Smith', 'gmc_number' => '1234567'],
            ]);

        $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'UK',
                'license_number' => '1234567',
            ])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 'manual_review')
            ->assertJsonPath('official_data.full_name', 'Alice Smith');
    }

    public function test_admin_uk_verification_requires_a_seven_digit_registration_number(): void
    {
        $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'UK',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['license_number']);

        $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'UK',
                'license_number' => '12A4567',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['license_number']);
    }

    public function test_admin_uk_verification_omits_raw_text_from_result(): void
    {
        $this->mock(GmcUkVerificationService::class)
            ->shouldReceive('verify')
            ->once()
            ->andReturn([
                'status' => 'verified',
                'message' => 'Verified.',
                'source' => 'Test GMC Register',
                'official_data' => [
                    'gmc_number' => '1234567',
                    'raw_text' => 'Internal page text that must not be shown.',
                ],
            ]);

        $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'UK',
                'license_number' => '1234567',
            ])
            ->assertOk()
            ->assertJsonMissingPath('official_data.raw_text');
    }

    public function test_non_admin_cannot_access_page_or_verification_endpoint(): void
    {
        $user = $this->user('doctor');

        $this->actingAs($user)
            ->get(route('admin.license-verification.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'US',
                'state' => 'OH',
                'license_number' => 'OH123456',
            ])
            ->assertForbidden();
    }

    public function test_supported_us_state_lookup_returns_official_result_without_writing_doctor_records(): void
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
                    'license_no' => 'OH123456',
                    'full_name' => 'Smith, Alice',
                ],
            ]),
            self::ENDPOINT => function (Request $request) {
                return Http::response([
                    'found' => true,
                    'license_number' => $request->data()['license_number'],
                    'full_name' => 'Smith, Alice',
                    'board' => 'Medical Board',
                    'license_type' => 'Physician License',
                    'status' => 'Active',
                    'source_state' => $request->data()['source_state'],
                    'expiry_date' => now()->addYear()->toDateString(),
                    'multiple' => null,
                ]);
            },
        ]);

        $response = $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'US',
                'state' => 'OH',
                'license_number' => 'OH123456',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'verified')
            ->assertJsonPath('official_data.full_name', 'Smith, Alice')
            ->assertJsonPath('official_data.license_no', 'OH123456')
            ->assertJsonPath('source', 'DoctorLicenseLookup');
        Http::assertSentCount(2);
        $this->assertSame(
            [
                'https://doctorlicenselookup.test/state/ohio',
                'https://doctorlicenselookup.test/ohio/search',
            ],
            Http::recorded()->map(static fn (array $recorded) => $recorded[0]->url())->all()
        );
    }

    public function test_admin_falls_back_to_state_provider_after_primary_technical_failure(): void
    {
        config([
            'doctor_lookup.enabled' => true,
            'doctor_lookup.base_url' => 'https://doctorlicenselookup.test',
        ]);
        Http::fake([
            'https://doctorlicenselookup.test/state/ohio' => Http::response('temporarily unavailable', 503),
            self::ENDPOINT => Http::response([
                'found' => true,
                'license_number' => 'OH123456',
                'board' => 'Medical Board',
                'license_type' => 'Physician License',
                'status' => 'Active',
                'source_state' => 'OH',
                'expiry_date' => now()->addYear()->toDateString(),
                'multiple' => null,
            ]),
        ]);

        $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'US',
                'state' => 'OH',
                'license_number' => 'OH123456',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'verified')
            ->assertJsonPath('source', 'USLicenseCheck');
        Http::assertSentCount(2);
        $this->assertSame(
            ['https://doctorlicenselookup.test/state/ohio', self::ENDPOINT],
            Http::recorded()->map(static fn (array $recorded) => $recorded[0]->url())->all()
        );
    }

    public function test_admin_does_not_fall_back_after_primary_not_found_result(): void
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
                'success' => false,
                'no_results' => true,
            ]),
            self::ENDPOINT => Http::response(['found' => true]),
        ]);

        $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'US',
                'state' => 'OH',
                'license_number' => 'missing',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'not_found');
        Http::assertSentCount(2);
    }

    public function test_admin_returns_primary_manual_review_when_state_has_no_fallback(): void
    {
        config([
            'doctor_lookup.enabled' => true,
            'doctor_lookup.base_url' => 'https://doctorlicenselookup.test',
        ]);
        Http::fake([
            'https://doctorlicenselookup.test/state/california' => Http::response('temporarily unavailable', 503),
            self::ENDPOINT => Http::response(['found' => true]),
        ]);

        $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'US',
                'state' => 'CA',
                'license_number' => 'CA123456',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'unable_to_verify')
            ->assertJsonPath('source', 'DoctorLicenseLookup');
        Http::assertSentCount(1);
    }

    public function test_admin_new_hampshire_lookup_uses_the_official_portal(): void
    {
        $this->withoutMiddleware();
        $this->fakeNewHampshirePortal();

        $response = $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'US',
                'state' => 'NH',
                'license_number' => '15080',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'verified')
            ->assertJsonPath('source', 'New Hampshire')
            ->assertJsonPath('official_data.license_number', '15080')
            ->assertJsonPath('official_data.profession', 'Medicine');
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://forms.nh.gov/licenseverification/'
            && $request->data()['t_web_lookup__license_no'] === '15080');
    }

    public function test_admin_can_verify_montana_license_through_the_existing_lookup_page(): void
    {
        $this->withoutMiddleware();
        $this->fakeMontanaPortal();

        $response = $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'US',
                'state' => 'MT',
                'license_number' => '100591',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'verified')
            ->assertJsonPath('source', 'Montana')
            ->assertJsonPath('official_data.licenseNumber', 'MED-PHYS-LIC-100591')
            ->assertJsonPath('official_data.licenseStatus', 'Active')
            ->assertJsonPath('official_data.expDate', '03/31/2027')
            ->assertJsonPath('official_data.expiration_date', '2027-03-31');
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === self::MONTANA_SEARCH
            && $request->data()['licnumber'] === 'MED-PHYS-LIC-100591');
    }

    public function test_massachusetts_uses_doctor_license_lookup_without_legacy_inputs_or_fallback(): void
    {
        config([
            'doctor_lookup.enabled' => true,
            'doctor_lookup.base_url' => 'https://doctorlicenselookup.test',
        ]);
        Http::fake([
            'https://doctorlicenselookup.test/state/massachusetts' => Http::response(
                '<html><meta name="csrf-token" content="fresh-token"></html>'
            ),
            'https://doctorlicenselookup.test/massachusetts/search' => Http::response([
                'success' => true,
                'data' => [
                    'license_no' => 'MA123456',
                    'full_name' => 'Alice Smith',
                ],
            ]),
            self::ENDPOINT => Http::response([
                'found' => true,
                'license_number' => 'MA123456',
                'board' => 'Medical Board',
                'license_type' => 'Physician License',
                'status' => 'Active',
                'source_state' => 'MA',
                'multiple' => null,
            ]),
        ]);

        $response = $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'US',
                'state' => 'MA',
                'license_number' => 'MA123456',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'verified')
            ->assertJsonPath('official_data.full_name', 'Alice Smith')
            ->assertJsonPath('official_data.license_no', 'MA123456')
            ->assertJsonPath('source', 'DoctorLicenseLookup');
        Http::assertSentCount(2);
        $this->assertSame(
            [
                'https://doctorlicenselookup.test/state/massachusetts',
                'https://doctorlicenselookup.test/massachusetts/search',
            ],
            Http::recorded()->map(static fn (array $recorded) => $recorded[0]->url())->all()
        );
    }

    public function test_kansas_is_accepted_and_unknown_states_are_rejected(): void
    {
        $this->withoutMiddleware();
        Http::fake();
        $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'US',
                'state' => 'KS',
                'license_number' => 'KS123456',
            ])
            ->assertOk()
            ->assertJsonPath('source', 'Kansas');

        $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'US',
                'state' => 'XX',
                'license_number' => 'CA123456',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('state');
    }

    public function test_massachusetts_does_not_require_legacy_license_type_or_date_inputs(): void
    {
        $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'US',
                'state' => 'MA',
                'license_number' => 'MA123456',
            ])
            ->assertOk()
            ->assertJsonPath('source', 'DoctorLicenseLookup');
    }

    public function test_massachusetts_does_not_fall_back_to_individual_verifiers(): void
    {
        config([
            'doctor_lookup.enabled' => true,
            'doctor_lookup.base_url' => 'https://doctorlicenselookup.test',
        ]);
        Http::fake([
            'https://doctorlicenselookup.test/state/massachusetts' => Http::response('temporarily unavailable', 503),
            self::ENDPOINT => Http::response([
                'found' => true,
                'license_number' => 'MA123456',
                'board' => 'Medical Board',
                'license_type' => 'Physician License',
                'status' => 'Active',
                'source_state' => 'MA',
                'multiple' => null,
            ]),
        ]);

        $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'US',
                'state' => 'MA',
                'license_number' => 'MA123456',
            ])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 'unable_to_verify')
            ->assertJsonPath('source', 'DoctorLicenseLookup');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://doctorlicenselookup.test/state/massachusetts');
    }

    public function test_multiple_consecutive_lookups_return_the_latest_submitted_license(): void
    {
        Http::fake([
            self::ENDPOINT => function (Request $request) {
                return Http::response([
                    'found' => true,
                    'license_number' => $request->data()['license_number'],
                    'board' => 'Medical Board',
                    'license_type' => 'Physician License',
                    'status' => 'Active',
                    'source_state' => $request->data()['source_state'],
                    'multiple' => null,
                ]);
            },
        ]);
        $admin = $this->user('admin');

        foreach (['OH111111', 'OH222222'] as $licenseNumber) {
            $this->actingAs($admin)
                ->postJson(route('admin.license-verification.verify'), [
                    'country' => 'US',
                    'state' => 'OH',
                    'license_number' => $licenseNumber,
                ])
                ->assertOk()
                ->assertJsonPath('official_data.license_number', $licenseNumber);
        }

        Http::assertSentCount(2);
    }

    private function user(string $role): User
    {
        $user = new User([
            'name' => ucfirst($role),
            'email' => $role.'@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'is_active' => true,
        ]);
        $user->forceFill(['id' => 1, 'uuid' => '00000001']);

        return $user;
    }

    private function fakeNewHampshirePortal(): void
    {
        $searchPage = '<html><form method="post" action="/licenseverification/">
            <input type="hidden" name="__EVENTTARGET" value="">
            <input type="hidden" name="__EVENTARGUMENT" value="">
            <input type="hidden" name="__LASTFOCUS" value="">
            <input type="hidden" name="__VIEWSTATE" value="view-state">
            <input type="hidden" name="__VIEWSTATEGENERATOR" value="view-state-generator">
            <input type="hidden" name="__EVENTVALIDATION" value="event-validation">
            <input name="t_web_lookup__profession_name">
            <input name="t_web_lookup__license_type_name">
            <input name="t_web_lookup__first_name">
            <input name="t_web_lookup__last_name">
            <input name="t_web_lookup__license_no">
            <input name="sch_button">
            </form></html>';
        $results = '<html><table id="datagrid_results">
            <tr><th>Full Name</th><th>Profession</th><th>License Type</th><th>License Number</th><th>License Status</th></tr>
            <tr><td>CURTIS, JOHN A, Jr</td><td>Medicine</td><td>Physician</td><td>15080</td><td>Active</td></tr>
            </table></html>';

        Http::fake([
            'https://forms.nh.gov/licenseverification/' => static function (Request $request) use ($searchPage) {
                return $request->method() === 'GET'
                    ? Http::response($searchPage)
                    : Http::response('', 302, ['Location' => '/licenseverification/SearchResults.aspx']);
            },
            'https://forms.nh.gov/licenseverification/SearchResults.aspx' => Http::response($results),
        ]);
    }

    private function fakeMontanaPortal(): void
    {
        $results = '<html><script>var data = [{"licenseNumber":"<a href=\'licensedetail?altid=MED-PHYS-LIC-100591\'>MED-PHYS-LIC-100591</a>","recordAlias":"Physician License","licenseStatus":"Active","firstName":"Natalia","lastName":"Solenkova","orgName":"","expDate":"03/31/2027","city":"AVENTURA","state":"FL","zip":"33180"}];</script></html>';

        Http::fake([
            self::MONTANA_SEARCH_PAGE => Http::response(
                '<html><body><form><input name="licnumber"></form></body></html>',
                200,
                ['Set-Cookie' => 'MTSession=fresh-session; Path=/; Secure']
            ),
            self::MONTANA_VERIFICATION => Http::response('OK'),
            self::MONTANA_SEARCH => Http::response($results),
        ]);
    }
}
