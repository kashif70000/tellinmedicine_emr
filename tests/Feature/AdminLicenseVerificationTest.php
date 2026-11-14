<?php

namespace Tests\Feature;

use App\Models\User;
use App\Http\Controllers\Admin\LicenseVerificationController;
use App\Services\MassachusettsLicenseSource;
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
            'uslicensecheck.base_url' => 'https://uslicensecheck.test/api/v1',
            'uslicensecheck.api_key' => 'test-key-not-a-secret',
            'uslicensecheck.supported_states' => ['OH', 'CT', 'CO', 'DE', 'TX', 'IL', 'NH', 'AK', 'WA'],
            'uslicensecheck.montana_license_verification_url' => self::MONTANA_SEARCH_PAGE,
        ]);
    }

    public function test_admin_page_lists_massachusetts_and_configured_states_including_kansas(): void
    {
        $request = HttpRequest::create('/admin/license-verification');
        $request->setUserResolver(fn () => $this->user('admin'));
        $view = app(LicenseVerificationController::class)->index($request);

        $this->assertSame('admin.license-verification', $view->name());
        $this->assertSame([
            'MA' => 'Massachusetts',
            'OH' => 'Ohio',
            'CT' => 'Connecticut',
            'CO' => 'Colorado',
            'DE' => 'Delaware',
            'TX' => 'Texas',
            'IL' => 'Illinois',
            'NH' => 'New Hampshire',
            'AK' => 'Alaska',
            'WA' => 'Washington',
            'KS' => 'Kansas',
            'MT' => 'Montana',
            'FL' => 'Florida',
        ], $view->getData()['states']);

        $this->actingAs($this->user('admin'))
            ->get(route('admin.license-verification.index'))
            ->assertOk()
            ->assertSee('<option value="MT">Montana (MT)</option>', false);
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
        Http::fake([
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
            ->assertJsonPath('official_data.license_number', 'OH123456');
        Http::assertSent(fn (Request $request) => $request->data() === [
            'license_number' => 'OH123456',
            'source_state' => 'OH',
        ]);
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

    public function test_massachusetts_lookup_uses_existing_verifier_inputs_without_writing_records(): void
    {
        $issueDate = now()->subYears(5)->toDateString();
        $expirationDate = now()->addYear()->toDateString();
        $this->app->instance(MassachusettsLicenseSource::class, new class($issueDate, $expirationDate) implements MassachusettsLicenseSource {
            public function __construct(private string $issueDate, private string $expirationDate) {}

            public function find(string $licenseType, string $licenseNumber): ?array
            {
                return [
                    'license_number' => $licenseNumber,
                    'full_name' => 'Alice Smith',
                    'license_type' => $licenseType,
                    'status' => 'Active',
                    'issue_date' => $this->issueDate,
                    'expiration_date' => $this->expirationDate,
                ];
            }

            public function sourceName(): string
            {
                return 'Test Massachusetts source';
            }
        });

        $response = $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'US',
                'state' => 'MA',
                'license_number' => 'MA123456',
                'license_type' => 'Full Physician',
                'license_issue_date' => $issueDate,
                'license_expiration_date' => $expirationDate,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'verified')
            ->assertJsonPath('official_data.full_name', 'Alice Smith')
            ->assertJsonPath('official_data.license_number', 'MA123456');
    }

    public function test_kansas_is_accepted_and_unconfigured_states_are_rejected(): void
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
                'state' => 'CA',
                'license_number' => 'CA123456',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('state');
    }

    public function test_massachusetts_requires_its_existing_license_type_and_date_inputs(): void
    {
        $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'US',
                'state' => 'MA',
                'license_number' => 'MA123456',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'license_type',
                'license_issue_date',
                'license_expiration_date',
            ]);
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
