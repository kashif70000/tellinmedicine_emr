<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class USLicenseCheckRegistrationTest extends TestCase
{
    use RefreshDatabase;

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
            'uslicensecheck.supported_states' => ['OH', 'CT', 'CO', 'DE', 'TX', 'IL', 'NH', 'AK', 'WA', 'KS'],
            'uslicensecheck.montana_license_verification_url' => self::MONTANA_SEARCH_PAGE,
        ]);
    }

    public function test_registration_page_lists_kansas_and_new_hampshire_as_license_states(): void
    {
        $this->get(route('register.doctor'))
            ->assertOk()
            ->assertSee('<option value="KS">Kansas (KS)</option>', false)
            ->assertSee('<option value="NH">New Hampshire (NH)</option>', false)
            ->assertSee('<option value="MT">Montana (MT)</option>', false);
        $this->assertNotContains('MT', config('uslicensecheck.supported_states'));
    }

    public function test_matching_us_license_registers_as_verified_and_records_the_attempt(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->record(['multiple' => null]))]);

        $response = $this->postJson(route('register.doctor.store'), $this->application());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('verification_pending', false);
        $this->assertDatabaseHas('doctors', [
            'license_state' => 'OH',
            'license_number' => 'OH123456',
            'license_verification_status' => 'verified',
            'license_verification_source' => 'USLicenseCheck',
            'is_verified' => true,
            'verification_status' => 'approved',
        ]);
        $this->assertDatabaseHas('license_verification_attempts', [
            'license_state' => 'OH',
            'license_type' => 'Physician License',
            'source' => 'USLicenseCheck',
            'status' => 'verified',
        ]);
        Http::assertSent(fn (Request $request) => $request->hasHeader('X-API-Key', 'test-key-not-a-secret')
            && $request->data() === [
                'license_number' => 'OH123456',
                'source_state' => 'OH',
            ]);
    }

    public function test_matching_new_hampshire_license_registers_using_the_official_portal(): void
    {
        $this->fakeNewHampshirePortal();

        $response = $this->postJson(route('register.doctor.store'), $this->application([
            'license_state' => 'NH',
            'license_number' => '15080',
        ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('verification_pending', false);
        $this->assertDatabaseHas('doctors', [
            'license_state' => 'NH',
            'license_number' => '15080',
            'license_verification_status' => 'verified',
            'license_verification_source' => 'New Hampshire',
            'is_verified' => true,
            'verification_status' => 'approved',
        ]);
        $this->assertDatabaseHas('license_verification_attempts', [
            'license_state' => 'NH',
            'license_number' => '15080',
            'source' => 'New Hampshire',
            'status' => 'verified',
        ]);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://forms.nh.gov/licenseverification/'
            && $request->data()['t_web_lookup__license_no'] === '15080');
    }

    public function test_matching_montana_license_registers_and_persists_official_verification_data(): void
    {
        $this->withoutMiddleware();
        $this->fakeMontanaPortal($this->montanaResults());

        $response = $this->postJson(route('register.doctor.store'), $this->application([
            'license_state' => 'MT',
            'license_number' => '100591',
        ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('verification_pending', false);
        $this->assertDatabaseHas('doctors', [
            'license_state' => 'MT',
            'license_number' => '100591',
            'license_verification_status' => 'verified',
            'license_verification_source' => 'Montana',
            'official_license_status' => 'Active',
            'official_license_expiration_date' => '2027-03-31',
            'is_verified' => true,
            'verification_status' => 'approved',
        ]);
        $this->assertDatabaseHas('license_verification_attempts', [
            'license_state' => 'MT',
            'license_number' => '100591',
            'source' => 'Montana',
            'status' => 'verified',
        ]);
        Http::assertSent(fn (Request $request) => $request->url() === self::MONTANA_SEARCH
            && $request->data()['licnumber'] === 'MED-PHYS-LIC-100591'
            && $request->data()['firstname'] === ''
            && $request->data()['lastname'] === '');
    }

    public function test_montana_registration_verification_does_not_require_a_name(): void
    {
        $this->withoutMiddleware();
        $this->fakeMontanaPortal($this->montanaResults());

        $this->postJson(route('register.doctor.verify-license'), [
            'license_state' => 'MT',
            'license_number' => '100591',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'verified');
    }

    public function test_matching_record_returns_verified_from_the_registration_verification_endpoint(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->record(['multiple' => null]))]);

        $this->postJson(route('register.doctor.verify-license'), [
            'name' => 'Alice Smith',
            'license_state' => 'OH',
            'license_number' => 'OH123456',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'verified')
            ->assertJsonPath('official_data.found', true)
            ->assertJsonPath('official_data.multiple', null);
    }

    public function test_inconclusive_provider_result_registers_only_for_manual_review(): void
    {
        Http::fake([self::ENDPOINT => Http::response([
            'found' => true,
            'multiple' => [$this->record(), $this->record(['license_number' => 'OH654321'])],
        ])]);

        $response = $this->postJson(route('register.doctor.store'), $this->application());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('verification_pending', true);
        $this->assertDatabaseHas('doctors', [
            'license_state' => 'OH',
            'license_verification_status' => 'manual_review',
            'is_verified' => false,
            'verification_status' => 'pending',
        ]);
        $this->assertDatabaseHas('license_verification_attempts', ['status' => 'manual_review']);
    }

    public function test_provider_outage_never_approves_the_registration(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['error' => 'unavailable'], 503)]);

        $response = $this->postJson(route('register.doctor.store'), $this->application());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('verification_pending', true);
        $this->assertDatabaseHas('doctors', [
            'license_state' => 'OH',
            'license_verification_status' => 'unavailable',
            'is_verified' => false,
            'verification_status' => 'pending',
        ]);
    }

    public function test_no_match_blocks_registration_and_records_the_failed_attempt(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['found' => false])]);

        $response = $this->postJson(route('register.doctor.store'), $this->application());

        $response->assertStatus(422)->assertJsonPath('status', 'failed');
        $this->assertDatabaseMissing('users', ['email' => 'alice@example.com']);
        $this->assertDatabaseHas('license_verification_attempts', [
            'license_state' => 'OH',
            'status' => 'failed',
            'source' => 'USLicenseCheck',
        ]);
    }

    public function test_registration_rejects_missing_and_unsupported_license_states(): void
    {
        Http::fake();

        $this->postJson(route('register.doctor.store'), $this->application(['license_state' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('license_state');

        $this->postJson(route('register.doctor.store'), $this->application(['license_state' => 'CA']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('license_state');

        Http::assertNothingSent();
    }

    public function test_registration_requires_country_and_matching_us_license_state(): void
    {
        Http::fake();

        $this->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->postJson(route('register.doctor.store'), $this->application(['license_country' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('license_country');

        $this->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->postJson(route('register.doctor.store'), $this->application(['license_country' => 'UK']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('license_state');

        Http::assertNothingSent();
    }

    public function test_admin_can_recheck_a_supported_us_license_using_the_doctor_state(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->record())]);
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $doctorUser = User::create([
            'name' => 'Alice Smith',
            'email' => 'doctor@example.com',
            'password' => bcrypt('password123'),
            'role' => 'doctor',
            'is_active' => true,
        ]);
        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'license_state' => 'OH',
            'license_number' => 'OH123456',
            'license_type' => 'Physician License',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.doctors.license.verify', $doctor))
            ->assertOk()
            ->assertJsonPath('status', 'verified');

        $this->assertDatabaseHas('doctors', [
            'id' => $doctor->id,
            'license_verification_status' => 'verified',
            'license_verification_source' => 'USLicenseCheck',
            'is_verified' => true,
            'verification_status' => 'approved',
        ]);
        Http::assertSent(fn (Request $request) => $request->data() === [
            'license_number' => 'OH123456',
            'source_state' => 'OH',
        ]);
    }

    private function application(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Alice Smith',
            'email' => 'alice@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '614-555-0100',
            'specialization' => 'Family Medicine',
            'experience' => 8,
            'fees' => 125,
            'clinic_name' => 'Columbus Clinic',
            'address' => 'Columbus, OH',
            'license_country' => 'US',
            'license_state' => 'OH',
            'license_number' => 'OH123456',
            'certificate' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf'),
        ], $overrides);
    }

    private function record(array $overrides = []): array
    {
        return array_merge([
            'found' => true,
            'full_name' => 'Smith, Alice',
            'license_number' => 'OH123456',
            'board' => 'Medical Board',
            'license_type' => 'Doctor of Medicine (MD)',
            'status' => 'Active',
            'expiry_date' => now()->addYear()->toDateString(),
            'source_state' => 'OH',
        ], $overrides);
    }

    private function fakeMontanaPortal(string $resultsHtml): void
    {
        Http::fake([
            self::MONTANA_SEARCH_PAGE => Http::response(
                '<html><body><form><input name="licnumber"></form></body></html>',
                200,
                ['Set-Cookie' => 'MTSession=fresh-session; Path=/; Secure']
            ),
            self::MONTANA_VERIFICATION => Http::response('OK'),
            self::MONTANA_SEARCH => Http::response($resultsHtml),
        ]);
    }

    private function montanaResults(): string
    {
        return '<html><script>var data = [{"licenseNumber":"<a href=\'licensedetail?altid=MED-PHYS-LIC-100591\'>MED-PHYS-LIC-100591</a>","recordAlias":"Physician License","licenseStatus":"Active","firstName":"Natalia","lastName":"Solenkova","orgName":"","expDate":"03/31/2027","city":"AVENTURA","state":"FL","zip":"33180"}];</script></html>';
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
}
