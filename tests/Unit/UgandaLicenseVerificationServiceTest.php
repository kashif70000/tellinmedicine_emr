<?php

namespace Tests\Unit;

use App\Services\UgandaLicenseVerificationService;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class UgandaLicenseVerificationServiceTest extends TestCase
{
    private const INITIAL_URL = 'https://ehealthlicense.go.ug/index.php/search/';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'uganda_license.license_verification_url' => self::INITIAL_URL,
            'uganda_license.connect_timeout' => 10,
            'uganda_license.timeout' => 20,
            'uganda_license.diagnostics' => false,
        ]);
    }

    public function test_active_umdpc_physician_is_verified_using_the_live_search_form_workflow(): void
    {
        $this->fakeSearch($this->resultHtml([
            'profession' => 'Medical Doctor',
            'registration_number' => 'K6918',
        ]));

        $result = app(UgandaLicenseVerificationService::class)->verify('K6918');

        $this->assertSame('verified', $result['status']);
        $this->assertSame('MATOVU MUHAMMADI', $result['official_data']['practitioner_name']);
        $this->assertSame('Medical Doctor', $result['official_data']['profession']);
        $this->assertSame('K6918', $result['official_data']['registration_number']);
        $this->assertSame('2026706627', $result['official_data']['license_number']);
        $this->assertSame('UMDPC', $result['official_data']['council']);
    }

    public function test_form_submission_selects_umdpc_without_duplicate_hidden_issuer(): void
    {
        $this->fakeSearch($this->resultHtml([
            'profession' => 'Physician',
            'registration_number' => 'K6918',
        ]));

        app(UgandaLicenseVerificationService::class)->verify('K6918');

        $requests = Http::recorded()->map(fn (array $record): Request => $record[0]);
        $this->assertCount(2, $requests);
        $this->assertSame('GET', $requests[0]->method());
        $this->assertSame(self::INITIAL_URL, $requests[0]->url());

        $searchRequest = $requests[1];
        $this->assertSame('GET', $searchRequest->method());
        $this->assertSame('https://ehealthlicense.go.ug/index.php/search/cadre', strtok($searchRequest->url(), '?'));
        $this->assertSame(self::INITIAL_URL, $searchRequest->header('Referer')[0] ?? null);
        $this->assertSame('2', $this->queryParameters($searchRequest->url())['LicenseSearch']['issuerId']);
        $this->assertSame('K6918', $this->queryParameters($searchRequest->url())['LicenseSearch']['q']);
        $this->assertSame(
            1,
            substr_count($searchRequest->url(), 'LicenseSearch%5BissuerId%5D=')
        );
        $this->assertStringNotContainsString('LicenseSearch%5BissuerId%5D=&', $searchRequest->url());
    }

    public function test_session_cookies_from_initial_page_are_sent_with_search_request(): void
    {
        $this->fakeSearch($this->resultHtml([
            'profession' => 'Physician',
            'registration_number' => 'K6918',
        ]));

        app(UgandaLicenseVerificationService::class)->verify('K6918');

        $requests = Http::recorded()->map(fn (array $record): Request => $record[0]);
        $this->assertCount(2, $requests);
        $this->assertFalse($requests[0]->hasHeader('Cookie'));
        $this->assertTrue($requests[1]->hasHeader('Cookie'));
        $this->assertStringContainsString('portal-session=', $requests[1]->header('Cookie')[0]);
    }

    public function test_ahpc_option_is_never_used_for_physician_verification(): void
    {
        $this->fakeSearch($this->resultHtml([
            'profession' => 'Physician',
            'registration_number' => 'K6918',
        ]));

        app(UgandaLicenseVerificationService::class)->verify('K6918');

        $requests = Http::recorded()->map(fn (array $record): Request => $record[0]);
        $query = $this->queryParameters($requests[1]->url());
        $this->assertSame('2', $query['LicenseSearch']['issuerId']);
        $this->assertNotSame('4', $query['LicenseSearch']['issuerId']);
    }

    public function test_exact_license_number_match_is_verified(): void
    {
        $this->fakeSearch($this->resultHtml([
            'profession' => 'General Practitioner',
            'registration_number' => 'K6918',
            'license_number' => '2026706627',
        ]));

        $result = app(UgandaLicenseVerificationService::class)->verify('2026706627');

        $this->assertSame('verified', $result['status']);
    }

    public function test_valid_no_match_result_is_not_found(): void
    {
        $this->fakeSearch($this->noResultsHtml());

        $result = app(UgandaLicenseVerificationService::class)->verify('NO-SUCH-REGISTRATION');

        $this->assertSame('not_found', $result['status']);
    }

    public function test_dentist_cannot_be_verified_as_a_physician(): void
    {
        $this->fakeSearch($this->resultHtml([
            'profession' => 'Dentist',
            'registration_number' => 'K6918',
        ]));

        $result = app(UgandaLicenseVerificationService::class)->verify('K6918');

        $this->assertSame('manual_review', $result['status']);
    }

    public function test_non_physician_record_cannot_be_verified(): void
    {
        $this->fakeSearch($this->resultHtml([
            'profession' => 'Nurse',
            'registration_number' => 'N-6918',
        ]));

        $result = app(UgandaLicenseVerificationService::class)->verify('N-6918');

        $this->assertSame('manual_review', $result['status']);
    }

    public function test_ambiguous_profession_requires_manual_review(): void
    {
        $this->fakeSearch($this->resultHtml([
            'profession' => 'Practitioner',
            'registration_number' => 'R-6918',
        ]));

        $result = app(UgandaLicenseVerificationService::class)->verify('R-6918');

        $this->assertSame('manual_review', $result['status']);
    }

    public function test_partial_registration_number_does_not_match(): void
    {
        $this->fakeSearch($this->resultHtml([
            'profession' => 'Medical Doctor',
            'registration_number' => 'K6918',
        ]));

        $result = app(UgandaLicenseVerificationService::class)->verify('K691');

        $this->assertSame('not_found', $result['status']);
    }

    public function test_multiple_exact_records_require_manual_review(): void
    {
        $html = $this->resultHtml([
            'profession' => 'Physician',
            'registration_number' => 'K6918',
        ]) . $this->resultHtml([
            'name' => 'ANOTHER PRACTITIONER',
            'profession' => 'Medical Doctor',
            'registration_number' => 'K6918',
        ]);
        $this->fakeSearch($html);

        $result = app(UgandaLicenseVerificationService::class)->verify('K6918');

        $this->assertSame('manual_review', $result['status']);
    }

    public function test_expired_license_is_not_verified(): void
    {
        $this->fakeSearch($this->resultHtml([
            'profession' => 'Physician',
            'registration_number' => 'UG-9999',
            'license_expiry' => '2020-01-01',
        ]));

        $result = app(UgandaLicenseVerificationService::class)->verify('UG-9999');

        $this->assertSame('expired', $result['status']);
    }

    public function test_license_expiring_today_is_not_treated_as_expired(): void
    {
        Carbon::setTestNow('2026-11-12 23:45:00');
        $this->beforeApplicationDestroyed(fn () => Carbon::setTestNow());
        $this->fakeSearch($this->resultHtml([
            'profession' => 'Physician',
            'registration_number' => 'TODAY-VALID',
            'license_expiry' => '2026-11-12',
        ]));

        $result = app(UgandaLicenseVerificationService::class)->verify('TODAY-VALID');

        $this->assertSame('verified', $result['status']);
    }

    public function test_suspended_license_is_inactive(): void
    {
        $this->fakeSearch($this->resultHtml([
            'profession' => 'Physician',
            'registration_number' => 'SUSP-1',
            'license_status' => 'Suspended',
        ]));

        $result = app(UgandaLicenseVerificationService::class)->verify('SUSP-1');

        $this->assertSame('inactive', $result['status']);
    }

    public function test_http_403_is_unable_to_verify_and_is_not_not_found(): void
    {
        $this->fakeSearch('<html><body>Forbidden</body></html>', 403);

        $result = app(UgandaLicenseVerificationService::class)->verify('K6918');

        $this->assertSame('unable_to_verify', $result['status']);
        Http::assertSentCount(2);
    }

    public function test_connection_reset_retries_once_and_does_not_report_not_found(): void
    {
        $searchAttempts = 0;
        Http::fake(function (Request $request) use (&$searchAttempts) {
            if (parse_url($request->url(), PHP_URL_PATH) === '/index.php/search/') {
                return Http::response($this->searchFormHtml(), 200, [
                    'Set-Cookie' => 'portal-session=test-session; Path=/; HttpOnly; Secure',
                ]);
            }

            $searchAttempts++;
            throw new ConnectionException('cURL error 35: Recv failure: Connection was reset');
        });

        $result = app(UgandaLicenseVerificationService::class)->verify('K6918');

        $this->assertSame('unable_to_verify', $result['status']);
        $this->assertSame(2, $searchAttempts);
        Http::assertSentCount(1);
    }

    public function test_unexpected_html_is_unable_to_verify(): void
    {
        $this->fakeSearch('<html><body>Service page changed</body></html>');

        $result = app(UgandaLicenseVerificationService::class)->verify('K6918');

        $this->assertSame('unable_to_verify', $result['status']);
    }

    public function test_missing_umdpc_option_does_not_submit_a_search(): void
    {
        Http::fake([
            '*' => Http::response('<form><input type="text" name="LicenseSearch[q]"></form>', 200),
        ]);

        $result = app(UgandaLicenseVerificationService::class)->verify('K6918');

        $this->assertSame('unable_to_verify', $result['status']);
        Http::assertSentCount(1);
    }

    public function test_opt_in_diagnostics_redact_query_and_practitioner_data(): void
    {
        config(['uganda_license.diagnostics' => true]);
        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'Uganda UMDPC search form initialized');
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $this->assertSame('Uganda UMDPC license verification request failed', $message);
                $this->assertSame('search_form_submission', $context['request_stage']);
                $this->assertSame(403, $context['http_status']);
                $this->assertTrue($context['session_cookies_received']);
                $this->assertTrue($context['umdpc_selected']);
                $this->assertSame('2', $context['search_parameters']['council_value']);
                $this->assertLessThanOrEqual(2000, strlen($context['response_snippet']));
                $this->assertStringNotContainsString('K6918', $context['response_snippet']);

                return true;
            });
        $this->fakeSearch('<html><body>Access Denied</body></html>', 403);

        $result = app(UgandaLicenseVerificationService::class)->verify('K6918');

        $this->assertSame('unable_to_verify', $result['status']);
    }

    private function fakeSearch(string $results, int $status = 200): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push($this->searchFormHtml(), 200, [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'Set-Cookie' => 'portal-session=test-session; Path=/; HttpOnly; Secure',
                ])
                ->push($results, $status, [
                    'Content-Type' => 'text/html; charset=UTF-8',
                ]),
        ]);
    }

    private function queryParameters(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $parameters);

        return $parameters;
    }

    private function searchFormHtml(): string
    {
        return <<<'HTML'
<html><body>
<form id="w1" action="/index.php/search/cadre" method="get">
  <input type="hidden" name="LicenseSearch[issuerId]" value="">
  <div id="licensesearch-issuerid" role="radiogroup">
    <label><input type="radio" name="LicenseSearch[issuerId]" value="4"> AHPC</label>
    <label><input type="radio" name="LicenseSearch[issuerId]" value="2"> UMDPC</label>
  </div>
  <input type="text" id="licensesearch-q" name="LicenseSearch[q]" placeholder="Enter a name or registration number">
</form>
</body></html>
HTML;
    }

    private function noResultsHtml(): string
    {
        return <<<'HTML'
<html><head><title>Practitioner Results</title></head><body>
  <h1>Practitioner Results</h1>
  <p>No practitioners match your search. Try a different name or registration number.</p>
</body></html>
HTML;
    }

    private function resultHtml(array $overrides = []): string
    {
        $name = htmlspecialchars($overrides['name'] ?? 'MATOVU MUHAMMADI', ENT_QUOTES, 'UTF-8');
        $profession = htmlspecialchars($overrides['profession'] ?? 'Medical Doctor', ENT_QUOTES, 'UTF-8');
        $registrationStatus = htmlspecialchars($overrides['registration_status'] ?? 'Active', ENT_QUOTES, 'UTF-8');
        $licenseStatus = htmlspecialchars($overrides['license_status'] ?? 'Active', ENT_QUOTES, 'UTF-8');
        $registrationNumber = htmlspecialchars($overrides['registration_number'] ?? 'K6918', ENT_QUOTES, 'UTF-8');
        $licenseNumber = htmlspecialchars($overrides['license_number'] ?? '2026706627', ENT_QUOTES, 'UTF-8');
        $registrationDate = htmlspecialchars($overrides['registration_date'] ?? '2019-05-27', ENT_QUOTES, 'UTF-8');
        $licenseExpiry = htmlspecialchars($overrides['license_expiry'] ?? '2026-12-31', ENT_QUOTES, 'UTF-8');
        $council = htmlspecialchars($overrides['council'] ?? 'UMDPC', ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div class="pro-card">
  <div class="pro-card__photo"><span class="pro-card__council-tag">{$council}</span></div>
  <div class="pro-card__body">
    <div class="pro-card__name">{$name}</div>
    <div class="pro-card__sub">{$profession}</div>
    <div class="pro-card__pills">
      <span class="pill pill-status">Registration: {$registrationStatus}</span>
      <span class="pill pill-status pill-open">License: {$licenseStatus}</span>
    </div>
    <dl class="pro-card__details">
      <div><dt>Reg. No</dt><dd>{$registrationNumber}</dd></div>
      <div><dt>Registered</dt><dd>{$registrationDate}</dd></div>
      <div><dt>Licence No</dt><dd>{$licenseNumber}</dd></div>
      <div><dt>Licence expiry</dt><dd>{$licenseExpiry}</dd></div>
    </dl>
    <div class="pro-card__foot"><a href="/index.php/site/professional-profile?id=NDM0ODU5">View profile</a></div>
  </div>
</div>
HTML;
    }
}
