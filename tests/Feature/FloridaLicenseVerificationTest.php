<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\User;
use App\Services\FloridaLicenseVerificationService;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class FloridaLicenseVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'https://mqa-internet.doh.state.fl.us';

    private const SEARCH_URL = self::HOST.'/MQASearchServices/HealthCareProviders';

    private const FINAL_URL = self::HOST.'/MQASearchServices/HealthcareProviders/LicenseVerification?LicInd=180968&Procde=1501';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_florida_search_uses_browser_session_and_parses_the_official_record(): void
    {
        $this->fakeFloridaPortal();

        $result = app(FloridaLicenseVerificationService::class)->verify([
            'license_state' => 'FL',
            'license_number' => 'ME175553',
        ]);

        $this->assertSame('verified', $result['status']);
        $this->assertSame('Florida Department of Health', $result['source']);
        $this->assertSame([
            'full_name' => 'ALANA JOHN',
            'license_number' => 'ME175553',
            'profession' => 'Medical Doctor',
            'status' => 'Clear/Active',
            'expiration_date' => '2027-01-31',
            'original_issue_date' => '2025-07-14',
            'discipline_on_file' => 'No',
            'public_complaint' => 'No',
            'issue_date' => '2025-07-14',
        ], $result['official_data']);
        $this->assertNotContains('FL', config('uslicensecheck.supported_states'));
        Http::assertSentCount(3);

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === self::SEARCH_URL
            && $request->hasHeader('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,image/jxl,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7')
            && $request->hasHeader('Accept-Language', 'en-PK,en-GB;q=0.9,en-US;q=0.8,en;q=0.7')
            && $request->hasHeader('Sec-CH-UA-Mobile', '?0')
            && $request->hasHeader('Sec-CH-UA-Platform', '"Windows"')
            && $request->hasHeader('Sec-Fetch-Site', 'none')
            && $request->hasHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36'));

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === self::SEARCH_URL
            && $request->hasHeader('Origin', self::HOST)
            && $request->hasHeader('Referer', self::SEARCH_URL)
            && $request->hasHeader('Sec-Fetch-Site', 'same-origin')
            && $request->hasHeader('Sec-Fetch-User', '?1')
            && str_contains(implode(';', (array) $request->header('Cookie')), 'ASP.NET_SessionId=session-value')
            && str_starts_with((string) $request->header('Content-Type')[0], 'application/x-www-form-urlencoded')
            && str_contains($request->body(), '__RequestVerificationToken=dynamic-token')
            && str_contains($request->body(), 'SearchDto.Board=15')
            && str_contains($request->body(), 'SearchDto.Profession=1501')
            && str_contains($request->body(), 'SearchDto.LicenseNumber=ME175553')
            && str_contains($request->body(), 'SearchDto.LicenseStatus=ACT')
            && str_contains($request->body(), 'SearchDto.BusinessName=')
            && str_contains($request->body(), 'SearchDto.LastName=')
            && str_contains($request->body(), 'SearchDto.FirstName=')
            && str_contains($request->body(), 'SearchDto.City=')
            && str_contains($request->body(), 'SearchDto.County=')
            && str_contains($request->body(), 'SearchDto.ZipCode='));

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === self::FINAL_URL
            && $request->hasHeader('Referer', self::SEARCH_URL)
            && $request->hasHeader('Sec-Fetch-Site', 'same-origin')
            && str_contains(implode(';', (array) $request->header('Cookie')), 'ASP.NET_SessionId=rotated-session')
            && $request->hasHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36'));
    }

    public function test_missing_token_stops_before_posting(): void
    {
        Http::fake([
            self::SEARCH_URL => Http::response('<html><form></form></html>'),
        ]);

        $result = app(FloridaLicenseVerificationService::class)->verify(['license_number' => 'ME175553']);

        $this->assertSame('unable_to_verify', $result['status']);
        Http::assertSentCount(1);
    }

    public function test_http_403_returns_manual_review_without_retrying(): void
    {
        Http::fake([
            self::SEARCH_URL => Http::response('<html>Request blocked</html>', 403, ['Content-Type' => 'text/html']),
        ]);

        $result = app(FloridaLicenseVerificationService::class)->verify(['license_number' => 'ME175553']);

        $this->assertSame('unable_to_verify', $result['status']);
        Http::assertSentCount(1);
    }

    public function test_http_403_logs_diagnostics_without_token_or_cookie_values(): void
    {
        Log::spy();
        Http::fake([
            self::SEARCH_URL => Http::response(
                '<html>Request blocked __RequestVerificationToken=secret-token ASP.NET_SessionId=session-secret Cookie: MQA=secret-cookie</html>',
                403,
                [
                    'Content-Type' => 'text/html',
                    'Set-Cookie' => 'ASP.NET_SessionId=cookie-secret; Path=/; Secure',
                ]
            ),
        ]);

        app(FloridaLicenseVerificationService::class)->verify(['license_number' => 'ME175553']);

        Log::shouldHaveReceived('log')->withArgs(function ($level, $message, $context): bool {
            $excerpt = $context['response_excerpt'] ?? '';

            return $level === 'warning'
                && $message === 'Florida license verification HTTP response'
                && ($context['stage'] ?? null) === 'initial_get'
                && ($context['http_status'] ?? null) === 403
                && ($context['denial_classification'] ?? null) === 'infrastructure_block'
                && in_array('ASP.NET_SessionId', $context['cookie_names'] ?? [], true)
                && ! str_contains($excerpt, 'secret-token')
                && ! str_contains($excerpt, 'session-secret')
                && ! str_contains($excerpt, 'secret-cookie')
                && ! str_contains($excerpt, 'cookie-secret');
        });
    }

    public function test_http_500_returns_unable_to_verify(): void
    {
        Http::fake([
            self::SEARCH_URL => Http::response('Portal error', 500, ['Content-Type' => 'text/html']),
        ]);

        $result = app(FloridaLicenseVerificationService::class)->verify(['license_number' => 'ME175553']);

        $this->assertSame('unable_to_verify', $result['status']);
        Http::assertSentCount(1);
    }

    public function test_connection_failure_logs_underlying_curl_details_without_sensitive_values(): void
    {
        Log::spy();
        $error = '__RequestVerificationToken=token-secret ASP.NET_SessionId=session-secret ME175553';
        $exception = new ConnectException(
            'cURL error 7: '.$error,
            new PsrRequest('GET', self::SEARCH_URL),
            null,
            ['errno' => 7, 'error' => $error]
        );
        Http::fake([
            self::SEARCH_URL => function () use ($exception) {
                throw $exception;
            },
        ]);

        $result = app(FloridaLicenseVerificationService::class)->verify(['license_number' => 'ME175553']);

        $this->assertSame('unable_to_verify', $result['status']);
        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context): bool {
            return $message === 'Florida license verification request failed'
                && ($context['stage'] ?? null) === 'initial_get'
                && ($context['curl_errno'] ?? null) === 7
                && str_contains($context['curl_error'] ?? '', '[sensitive value redacted]')
                && str_contains($context['curl_error'] ?? '', '[license redacted]')
                && ! str_contains($context['curl_error'] ?? '', 'token-secret')
                && ! str_contains($context['curl_error'] ?? '', 'session-secret')
                && ! str_contains($context['connection_error'] ?? '', 'token-secret')
                && ! str_contains($context['connection_error'] ?? '', 'session-secret');
        });
    }

    public function test_unexpected_external_redirect_is_not_followed(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response($this->searchPage());
            }

            return Http::response('', 302, ['Location' => 'https://example.com/MQASearchServices/HealthcareProviders/LicenseVerification?LicInd=1&Procde=1501']);
        });

        $result = app(FloridaLicenseVerificationService::class)->verify(['license_number' => 'ME175553']);

        $this->assertSame('unable_to_verify', $result['status']);
        Http::assertSentCount(2);
    }

    public function test_non_active_and_mismatched_records_are_not_verified(): void
    {
        $this->fakeFloridaPortal($this->verificationPage('Inactive'));
        $inactive = app(FloridaLicenseVerificationService::class)->verify(['license_number' => 'ME175553']);
        $this->assertSame('failed', $inactive['status']);

        $this->fakeFloridaPortal($this->verificationPage('Clear/Active', 'ME999999'));
        $mismatch = app(FloridaLicenseVerificationService::class)->verify(['license_number' => 'ME175553']);
        $this->assertSame('failed', $mismatch['status']);
        Http::assertSentCount(3);
    }

    public function test_active_without_clearance_is_not_verified(): void
    {
        $this->fakeFloridaPortal($this->verificationPage('Active'));

        $result = app(FloridaLicenseVerificationService::class)->verify(['license_number' => 'ME175553']);

        $this->assertSame('failed', $result['status']);
    }

    public function test_expired_record_is_not_verified(): void
    {
        $this->fakeFloridaPortal($this->verificationPage('Expired'));
        $expired = app(FloridaLicenseVerificationService::class)->verify(['license_number' => 'ME175553']);
        $this->assertSame('expired', $expired['status']);
    }

    public function test_clear_active_record_with_expired_expiration_date_is_not_verified(): void
    {
        $this->fakeFloridaPortal(str_replace('01/31/2027', '01/31/2020', $this->verificationPage()));

        $expired = app(FloridaLicenseVerificationService::class)->verify(['license_number' => 'ME175553']);

        $this->assertSame('expired', $expired['status']);
    }

    public function test_unexpected_html_uses_manual_review(): void
    {
        $this->fakeFloridaPortal('<html><body>Unexpected proxy response</body></html>');
        $unexpected = app(FloridaLicenseVerificationService::class)->verify(['license_number' => 'ME175553']);
        $this->assertSame('unable_to_verify', $unexpected['status']);
    }

    public function test_session_expiration_on_final_page_uses_manual_review(): void
    {
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($request->method() === 'GET' && $path === '/MQASearchServices/HealthCareProviders') {
                return Http::response($this->searchPage());
            }
            if ($request->method() === 'POST') {
                return Http::response('', 302, [
                    'Location' => '/MQASearchServices/HealthcareProviders/LicenseVerification?LicInd=180968&Procde=1501',
                ]);
            }

            return Http::response('<html>Session expired</html>', 403, ['Content-Type' => 'text/html']);
        });

        $result = app(FloridaLicenseVerificationService::class)->verify(['license_number' => 'ME175553']);

        $this->assertSame('unable_to_verify', $result['status']);
        Http::assertSentCount(3);
    }

    public function test_admin_lookup_accepts_florida_without_adding_it_to_api_supported_states(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->fakeFloridaPortal();

        $this->actingAs($admin)
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'US',
                'state' => 'FL',
                'license_number' => 'ME175553',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'verified')
            ->assertJsonPath('official_data.profession', 'Medical Doctor');

        $this->assertNotContains('FL', config('uslicensecheck.supported_states'));
    }

    public function test_registration_license_lookup_accepts_only_the_florida_license_number(): void
    {
        $this->fakeFloridaPortal();

        $this->postJson(route('register.doctor.verify-license'), [
            'license_state' => 'FL',
            'license_number' => 'ME175553',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'verified');
    }

    public function test_registration_persists_florida_verification_and_attempt(): void
    {
        $this->fakeFloridaPortal();

        $response = $this->postJson(route('register.doctor.store'), [
            'name' => 'Alana John',
            'email' => 'alana@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '555-555-5555',
            'specialization' => 'Family Medicine',
            'experience' => 8,
            'fees' => 125,
            'clinic_name' => 'Florida Clinic',
            'address' => 'Florida',
            'license_country' => 'US',
            'license_state' => 'FL',
            'license_number' => 'ME175553',
            'certificate' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf'),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('verification_pending', false);
        $this->assertDatabaseHas('doctors', [
            'license_state' => 'FL',
            'license_number' => 'ME175553',
            'license_verification_status' => 'verified',
            'license_verification_source' => 'Florida Department of Health',
            'is_verified' => true,
            'verification_status' => 'approved',
        ]);
        $this->assertDatabaseHas('license_verification_attempts', [
            'license_state' => 'FL',
            'license_number' => 'ME175553',
            'source' => 'Florida Department of Health',
            'status' => 'verified',
        ]);
    }

    public function test_blocked_registration_is_saved_for_manual_review(): void
    {
        Http::fake([
            self::SEARCH_URL => Http::response('<html>Request blocked</html>', 403, ['Content-Type' => 'text/html']),
        ]);

        $response = $this->postJson(route('register.doctor.store'), [
            'name' => 'Alana John',
            'email' => 'review@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '555-555-5555',
            'specialization' => 'Family Medicine',
            'experience' => 8,
            'fees' => 125,
            'clinic_name' => 'Florida Clinic',
            'address' => 'Florida',
            'license_country' => 'US',
            'license_state' => 'FL',
            'license_number' => 'ME175553',
            'certificate' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf'),
        ]);

        $response->assertOk()
            ->assertJsonPath('verification_pending', true);
        $this->assertDatabaseHas('doctors', [
            'license_state' => 'FL',
            'license_number' => 'ME175553',
            'license_verification_status' => 'unable_to_verify',
            'is_verified' => false,
            'verification_status' => 'pending',
        ]);
        $this->assertDatabaseHas('license_verification_attempts', [
            'license_state' => 'FL',
            'license_number' => 'ME175553',
            'status' => 'unable_to_verify',
        ]);
    }

    public function test_admin_can_retry_florida_verification_and_records_the_attempt(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $doctorUser = User::create([
            'name' => 'Alana John',
            'email' => 'doctor@example.com',
            'password' => bcrypt('password123'),
            'role' => 'doctor',
            'is_active' => false,
        ]);
        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'license_state' => 'FL',
            'license_type' => 'Physician License',
            'license_number' => 'ME175553',
            'license_verification_status' => 'unable_to_verify',
        ]);
        $this->fakeFloridaPortal();

        $this->actingAs($admin)
            ->postJson(route('admin.doctors.license.verify', $doctor))
            ->assertOk()
            ->assertJsonPath('status', 'verified');

        $this->assertDatabaseHas('doctors', [
            'id' => $doctor->id,
            'license_verification_status' => 'verified',
            'license_verification_source' => 'Florida Department of Health',
            'is_verified' => true,
        ]);
        $this->assertDatabaseHas('license_verification_attempts', [
            'doctor_id' => $doctor->id,
            'actor_user_id' => $admin->id,
            'license_state' => 'FL',
            'status' => 'verified',
        ]);
    }

    private function fakeFloridaPortal(?string $verificationHtml = null): void
    {
        Http::fake(function (Request $request) use ($verificationHtml) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($request->method() === 'GET' && $path === '/MQASearchServices/HealthCareProviders') {
                return Http::response(
                    $this->searchPage(),
                    200,
                    ['Set-Cookie' => [
                        'ASP.NET_SessionId=session-value; Path=/; HttpOnly; Secure',
                        '__RequestVerificationToken_L01RQVNlYXJjaFNlcnZpY2Vz0=cookie-token; Path=/; Secure',
                    ]]
                );
            }
            if ($request->method() === 'POST' && $path === '/MQASearchServices/HealthCareProviders') {
                return Http::response('', 302, [
                    'Location' => '/MQASearchServices/HealthcareProviders/LicenseVerification?LicInd=180968&Procde=1501',
                    'Set-Cookie' => 'ASP.NET_SessionId=rotated-session; Path=/; HttpOnly; Secure',
                ]);
            }
            if ($request->method() === 'GET' && $path === '/MQASearchServices/HealthcareProviders/LicenseVerification') {
                return Http::response($verificationHtml ?? $this->verificationPage());
            }

            return Http::response('', 404);
        });
    }

    private function searchPage(): string
    {
        return '<!doctype html><html><body><form method="post"><input type="hidden" name="__RequestVerificationToken" value="dynamic-token"></form></body></html>';
    }

    private function verificationPage(string $status = 'Clear/Active', string $licenseNumber = 'ME175553'): string
    {
        return '<!doctype html><html><body><table>'
            .'<tr><th>Physician Name</th><td>ALANA JOHN</td></tr>'
            .'<tr><th>License Number</th><td>'.$licenseNumber.'</td></tr>'
            .'<tr><th>Profession</th><td>Medical Doctor</td></tr>'
            .'<tr><th>License Status</th><td>'.$status.'</td></tr>'
            .'<tr><th>Expiration Date</th><td>01/31/2027</td></tr>'
            .'<tr><th>Original Issue Date</th><td>07/14/2025</td></tr>'
            .'<tr><th>Discipline on File</th><td>No</td></tr>'
            .'<tr><th>Public Complaint</th><td>No</td></tr>'
            .'</table></body></html>';
    }
}
