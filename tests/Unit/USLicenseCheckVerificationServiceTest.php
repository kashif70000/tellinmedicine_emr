<?php

namespace Tests\Unit;

use App\Services\USLicenseCheckVerificationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class USLicenseCheckVerificationServiceTest extends TestCase
{
    private const ENDPOINT = 'https://uslicensecheck.test/api/v1/lookup/verify';

    private const NEW_HAMPSHIRE_ENDPOINT = 'https://forms.nh.gov/licenseverification/';

    private const NEW_HAMPSHIRE_RESULTS = 'https://forms.nh.gov/licenseverification/SearchResults.aspx';

    private const MONTANA_SEARCH_PAGE = 'https://ebizws.mt.gov/PUBLICPORTAL/searchform?mylist=licenses';

    private const MONTANA_VERIFICATION = 'https://ebizws.mt.gov/PUBLICPORTAL/verif';

    private const MONTANA_SEARCH = 'https://ebizws.mt.gov/PUBLICPORTAL/searchform';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'uslicensecheck.base_url' => 'https://uslicensecheck.test/api/v1',
            'uslicensecheck.api_key' => 'test-key-not-a-secret',
            'uslicensecheck.connect_timeout' => 2,
            'uslicensecheck.timeout' => 5,
            'uslicensecheck.supported_states' => ['OH', 'CT', 'CO', 'DE', 'TX', 'IL', 'NH', 'AK', 'WA'],
            'uslicensecheck.new_hampshire_request_cookie' => '',
            'uslicensecheck.montana_license_verification_url' => self::MONTANA_SEARCH_PAGE,
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

    public function test_uslicensecheck_states_are_sent_as_the_selected_source_state(): void
    {
        Http::fake([
            self::ENDPOINT => function (Request $request) {
                return Http::response($this->record([
                    'license_number' => $request->data()['license_number'],
                    'source_state' => $request->data()['source_state'],
                ]));
            },
        ]);

        foreach (['OH', 'CT', 'CO', 'DE', 'TX', 'IL', 'AK', 'WA'] as $state) {
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

        Http::assertSentCount(8);
    }

    public function test_new_hampshire_active_physician_license_uses_official_portal_and_dynamic_form_fields(): void
    {
        $this->fakeNewHampshirePortal($this->newHampshireResults());

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application([
            'license_state' => 'NH',
            'license_number' => '15080',
        ]));

        $this->assertSame('verified', $result['status']);
        $this->assertSame('New Hampshire', $result['source']);
        $this->assertSame([
            'full_name' => 'CURTIS, JOHN A, Jr',
            'profession' => 'Medicine',
            'license_type' => 'Physician',
            'license_number' => '15080',
            'status' => 'Active',
            'source_state' => 'NH',
        ], $result['official_data']);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === self::NEW_HAMPSHIRE_ENDPOINT
            && $request->data()['__EVENTTARGET'] === ''
            && $request->data()['__EVENTARGUMENT'] === ''
            && $request->data()['__LASTFOCUS'] === ''
            && $request->data()['__VIEWSTATE'] === 'fresh-viewstate'
            && $request->data()['__VIEWSTATEGENERATOR'] === 'fresh-generator'
            && $request->data()['__EVENTVALIDATION'] === 'fresh-eventvalidation'
            && $request->data()['t_web_lookup__profession_name'] === 'Medicine'
            && $request->data()['t_web_lookup__license_type_name'] === 'Physician'
            && $request->data()['t_web_lookup__first_name'] === ''
            && $request->data()['t_web_lookup__last_name'] === ''
            && $request->data()['t_web_lookup__license_no'] === '15080'
            && $request->data()['sch_button'] === 'Search');
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->hasHeader('Cookie', 'ASP.NET_SessionId=fresh-session'));
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === self::NEW_HAMPSHIRE_RESULTS);
        Http::assertSentCount(3);
    }

    public function test_montana_active_matching_physician_license_is_verified_using_the_portal_workflow(): void
    {
        $this->fakeMontanaPortal($this->montanaResults());

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application([
            'license_state' => 'MT',
            'license_number' => 'MED-PHYS-LIC-100591',
        ]));

        $this->assertSame('verified', $result['status']);
        $this->assertSame('Montana', $result['source']);
        $this->assertSame('MED-PHYS-LIC-100591', $result['official_data']['licenseNumber']);
        $this->assertSame('Physician License', $result['official_data']['recordAlias']);
        $this->assertSame('Active', $result['official_data']['licenseStatus']);
        $this->assertSame('', $result['official_data']['orgName']);
        $this->assertSame('2027-03-31', $result['official_data']['expiration_date']);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === self::MONTANA_SEARCH_PAGE);
        foreach ([self::MONTANA_VERIFICATION, self::MONTANA_SEARCH] as $url) {
            Http::assertSent(fn (Request $request) => $request->method() === 'POST'
                && $request->url() === $url
                && $request->data() === [
                    'licboard' => 'Medical Examiners',
                    'lictype' => 'Medical Examiners Medical Doctor License',
                    'licnumber' => 'MED-PHYS-LIC-100591',
                    'firstname' => '',
                    'lastname' => '',
                    'businessname' => '',
                    'city' => '',
                    'state' => '',
                    'zip' => '',
                    'country' => '',
                    'mylist' => 'license',
                ]
                && in_array('MTSession=fresh-session', $request->header('Cookie'), true));
        }
        Http::assertSentCount(3);
    }

    public function test_montana_license_prefix_is_not_duplicated_when_submitting_search(): void
    {
        $this->fakeMontanaPortal($this->montanaResults());

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application([
            'license_state' => 'MT',
            'license_number' => 'MED-PHYS-LIC-MED-PHYS-LIC-100591',
        ]));

        $this->assertSame('verified', $result['status']);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === self::MONTANA_VERIFICATION
            && $request->data()['licnumber'] === 'MED-PHYS-LIC-100591');
    }

    public function test_montana_empty_data_array_requires_manual_review(): void
    {
        $this->fakeMontanaPortal('<html><script>var data = [];</script></html>');

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application([
            'license_state' => 'MT',
            'license_number' => '100591',
        ]));

        $this->assertSame('manual_review', $result['status']);
    }

    public function test_montana_mismatched_license_number_is_not_verified(): void
    {
        $this->fakeMontanaPortal($this->montanaResults(['licenseNumber' => '<a href="licensedetail?altid=MED-PHYS-LIC-100592">MED-PHYS-LIC-100592</a>']));

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application([
            'license_state' => 'MT',
            'license_number' => '100591',
        ]));

        $this->assertSame('failed', $result['status']);
    }

    public function test_montana_non_physician_license_is_not_verified(): void
    {
        $this->fakeMontanaPortal($this->montanaResults(['recordAlias' => 'Nursing License']));

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application([
            'license_state' => 'MT',
            'license_number' => '100591',
        ]));

        $this->assertSame('failed', $result['status']);
    }

    public function test_montana_inactive_license_is_not_verified(): void
    {
        $this->fakeMontanaPortal($this->montanaResults(['licenseStatus' => 'Inactive']));

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application([
            'license_state' => 'MT',
            'license_number' => '100591',
        ]));

        $this->assertSame('failed', $result['status']);
    }

    public function test_montana_active_license_with_expired_expiration_date_is_not_verified(): void
    {
        $this->fakeMontanaPortal($this->montanaResults(['expDate' => '01/01/2020']));

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application([
            'license_state' => 'MT',
            'license_number' => '100591',
        ]));

        $this->assertSame('expired', $result['status'], $result['message']);
    }

    public function test_montana_unknown_status_requires_manual_review(): void
    {
        $this->fakeMontanaPortal($this->montanaResults(['licenseStatus' => 'Pending']));

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application([
            'license_state' => 'MT',
            'license_number' => '100591',
        ]));

        $this->assertSame('manual_review', $result['status']);
    }

    public function test_montana_malformed_or_missing_data_is_unable_to_verify(): void
    {
        foreach ([
            '<html><script>var data = [{"licenseNumber":</script></html>',
            '<html><body>No result data</body></html>',
        ] as $html) {
            $this->fakeMontanaPortal($html);
            $result = app(USLicenseCheckVerificationService::class)->verify($this->application([
                'license_state' => 'MT',
                'license_number' => '100591',
            ]));

            $this->assertSame('unable_to_verify', $result['status']);
        }
    }

    public function test_montana_security_page_requires_manual_review(): void
    {
        Http::fake([
            self::MONTANA_SEARCH_PAGE => Http::response(
                '<html><title>Request Rejected</title>Support ID: 123</html>',
                200,
                [
                    'Content-Type' => 'text/html',
                    'Set-Cookie' => 'SessionId=sensitive-session-value; Path=/',
                ]
            ),
        ]);
        $diagnostics = null;
        Log::shouldReceive('warning')
            ->twice()
            ->withArgs(function (string $message, array $context) use (&$diagnostics): bool {
                if ($message === 'Montana license response diagnostics') {
                    $diagnostics = $context;

                    return true;
                }

                return $message === 'Montana license verification response could not be used';
            });

        $blocked = app(USLicenseCheckVerificationService::class)->verify($this->application([
            'license_state' => 'MT',
            'license_number' => '100591',
        ]));
        $this->assertSame('manual_review', $blocked['status']);
        Http::assertSentCount(1);
        $this->assertSame([
            'stage',
            'response_type',
            'http_status',
            'final_response_url',
            'content_type',
            'response_body_length',
            'page_title',
            'var_data_present',
            'search_form_present',
            'verification_question_present',
            'portal_security_page',
        ], array_keys($diagnostics));
        $this->assertSame('portal_security_page', $diagnostics['response_type']);
        $this->assertSame(200, $diagnostics['http_status']);
        $this->assertSame('https://ebizws.mt.gov/PUBLICPORTAL/searchform', $diagnostics['final_response_url']);
        $this->assertSame('text/html', $diagnostics['content_type']);
        $this->assertSame('Request Rejected', $diagnostics['page_title']);
        $this->assertFalse($diagnostics['var_data_present']);
        $this->assertFalse($diagnostics['search_form_present']);
        $this->assertFalse($diagnostics['verification_question_present']);
        $this->assertTrue($diagnostics['portal_security_page']);
    }

    public function test_montana_verification_security_page_requires_manual_review(): void
    {
        Http::fake([
            self::MONTANA_SEARCH_PAGE => Http::response('<html><body><form><input name="licnumber"></form></body></html>'),
            self::MONTANA_VERIFICATION => Http::response('<html>Access denied. Support ID: 456</html>'),
            self::MONTANA_SEARCH => Http::response($this->montanaResults()),
        ]);
        $blockedVerification = app(USLicenseCheckVerificationService::class)->verify($this->application([
            'license_state' => 'MT',
            'license_number' => '100591',
        ]));
        $this->assertSame('manual_review', $blockedVerification['status']);
        Http::assertSentCount(2);
    }

    public function test_montana_http_error_requires_manual_verification(): void
    {
        Http::fake([
            self::MONTANA_SEARCH_PAGE => Http::response('Unavailable', 503),
        ]);
        $httpError = app(USLicenseCheckVerificationService::class)->verify($this->application([
            'license_state' => 'MT',
            'license_number' => '100591',
        ]));
        $this->assertSame('unable_to_verify', $httpError['status']);
        Http::assertSentCount(1);
    }

    public function test_montana_connection_error_requires_manual_verification(): void
    {
        Http::fake([
            self::MONTANA_SEARCH_PAGE => static function () {
                throw new ConnectionException('simulated timeout');
            },
        ]);
        $timeout = app(USLicenseCheckVerificationService::class)->verify($this->application([
            'license_state' => 'MT',
            'license_number' => '100591',
        ]));
        $this->assertSame('unable_to_verify', $timeout['status']);
    }

    public function test_montana_verification_question_requires_manual_review_without_submission(): void
    {
        Http::fake([
            self::MONTANA_SEARCH_PAGE => Http::response(
                '<html><title>Verification Question</title><body><form>Verification Question: Enter answer</form></body></html>'
            ),
        ]);

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application([
            'license_state' => 'MT',
            'license_number' => '100591',
        ]));

        $this->assertSame('manual_review', $result['status']);
        Http::assertSentCount(1);
    }

    public function test_montana_parser_handles_escaped_json_and_html_entities(): void
    {
        $record = [
            'licenseNumber' => '<a href="licensedetail?altid=MED-PHYS-LIC-100591">MED-PHYS-LIC-100591</a>',
            'recordAlias' => 'Physician License',
            'licenseStatus' => 'Active',
            'firstName' => 'Natalia "N." &amp; Co.',
            'lastName' => 'Solenkova',
            'orgName' => '',
            'expDate' => '03/31/2027',
            'city' => 'AVENTURA',
            'state' => 'FL',
            'zip' => '33180',
        ];
        $this->fakeMontanaPortal('<html><script>var data = '.json_encode([$record], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).';</script></html>');

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application([
            'license_state' => 'MT',
            'license_number' => 'MED-PHYS-LIC-100591',
        ]));

        $this->assertSame('verified', $result['status']);
        $this->assertSame('Natalia "N." & Co.', $result['official_data']['firstName']);
    }

    public function test_new_hampshire_request_cookie_is_sent_on_get_post_and_results_requests(): void
    {
        Log::spy();
        config(['uslicensecheck.new_hampshire_request_cookie' => 'NHAccess=approved; NHPreference=full']);
        $this->fakeNewHampshirePortal($this->newHampshireResults());

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application([
            'license_state' => 'NH',
            'license_number' => '15080',
        ]));

        $this->assertSame('verified', $result['status']);
        Http::assertSent(function (Request $request) {
            $cookieHeader = implode('; ', $request->header('Cookie'));

            return $request->url() === self::NEW_HAMPSHIRE_ENDPOINT
                && str_contains($cookieHeader, 'NHAccess=approved')
                && str_contains($cookieHeader, 'NHPreference=full');
        });
        Http::assertSent(function (Request $request) {
            $cookieHeader = implode('; ', $request->header('Cookie'));

            return $request->method() === 'POST'
                && str_contains($cookieHeader, 'NHAccess=approved')
                && str_contains($cookieHeader, 'NHPreference=full')
                && str_contains($cookieHeader, 'ASP.NET_SessionId=fresh-session');
        });
        Http::assertSent(function (Request $request) {
            $cookieHeader = implode('; ', $request->header('Cookie'));

            return $request->url() === self::NEW_HAMPSHIRE_RESULTS
                && str_contains($cookieHeader, 'NHAccess=approved')
                && str_contains($cookieHeader, 'NHPreference=full')
                && str_contains($cookieHeader, 'ASP.NET_SessionId=fresh-session');
        });
        Http::assertSentCount(3);
        Log::shouldHaveReceived('info')->times(3);
    }

    public function test_new_hampshire_session_cookie_replaces_a_configured_cookie_with_the_same_name(): void
    {
        config(['uslicensecheck.new_hampshire_request_cookie' => 'NHAccess=stale']);
        $searchPage = $this->newHampshireSearchPage();
        $results = $this->newHampshireResults();
        Http::fake([
            self::NEW_HAMPSHIRE_ENDPOINT => static function (Request $request) use ($searchPage) {
                if ($request->method() === 'GET') {
                    return Http::response(
                        $searchPage,
                        200,
                        ['Set-Cookie' => 'NHAccess=fresh-session; Path=/']
                    );
                }

                return Http::response('', 302, ['Location' => '/licenseverification/SearchResults.aspx']);
            },
            self::NEW_HAMPSHIRE_RESULTS => Http::response($results),
        ]);

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application([
            'license_state' => 'NH',
            'license_number' => '15080',
        ]));

        $this->assertSame('verified', $result['status']);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_contains(implode('; ', $request->header('Cookie')), 'NHAccess=fresh-session')
            && ! str_contains(implode('; ', $request->header('Cookie')), 'NHAccess=stale'));
    }

    public function test_new_hampshire_no_matching_license_returns_failed(): void
    {
        $this->fakeNewHampshirePortal($this->newHampshireResults([], true));

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application([
            'license_state' => 'NH',
            'license_number' => '99999',
        ]));

        $this->assertSame('failed', $result['status']);
        $this->assertNull($result['official_data']);
    }

    public function test_new_hampshire_rejects_another_profession_and_mismatched_license_number(): void
    {
        $cases = [
            [$this->newHampshireResults(['profession' => 'Pharmacy']), 'failed'],
            [$this->newHampshireResults(['license_number' => '15081']), 'failed'],
        ];

        foreach ($cases as [$html, $expectedStatus]) {
            $this->fakeNewHampshirePortal($html);

            $result = app(USLicenseCheckVerificationService::class)->verify($this->application([
                'license_state' => 'NH',
                'license_number' => '15080',
            ]));

            $this->assertSame($expectedStatus, $result['status']);
        }
    }

    public function test_new_hampshire_expired_inactive_suspended_and_revoked_licenses_are_not_verified(): void
    {
        $cases = [
            ['Expired', 'expired'],
            ['Inactive', 'failed'],
            ['Suspended', 'failed'],
            ['Revoked', 'failed'],
            ['Unknown', 'unable_to_verify'],
        ];
        $portal = Http::sequence();
        $results = Http::sequence();
        foreach ($cases as [$licenseStatus]) {
            $portal->push($this->newHampshireSearchPage())
                ->push('', 302, ['Location' => '/licenseverification/SearchResults.aspx']);
            $results->push($this->newHampshireResults(['status' => $licenseStatus]));
        }
        Http::fake([
            self::NEW_HAMPSHIRE_ENDPOINT => $portal,
            self::NEW_HAMPSHIRE_RESULTS => $results,
        ]);

        foreach ($cases as [$licenseStatus, $expectedStatus]) {
            $result = app(USLicenseCheckVerificationService::class)->verify($this->application([
                'license_state' => 'NH',
                'license_number' => '15080',
            ]));

            $this->assertSame($expectedStatus, $result['status'], $licenseStatus);
        }
    }

    public function test_new_hampshire_unavailable_portal_requires_manual_review(): void
    {
        Http::fake([self::NEW_HAMPSHIRE_ENDPOINT => static function () {
            throw new ConnectionException('simulated timeout');
        }]);
        $result = app(USLicenseCheckVerificationService::class)->verify($this->application(['license_state' => 'NH']));
        $this->assertSame('unable_to_verify', $result['status']);
    }

    public function test_new_hampshire_missing_hidden_fields_requires_manual_review(): void
    {
        $pageWithoutEventValidation = str_replace(
            '<input type="hidden" name="__EVENTVALIDATION" value="fresh-eventvalidation">',
            '',
            $this->newHampshireSearchPage()
        );
        $this->fakeNewHampshirePortal('', 200, '/licenseverification/SearchResults.aspx', true, $pageWithoutEventValidation);
        $result = app(USLicenseCheckVerificationService::class)->verify($this->application(['license_state' => 'NH']));
        $this->assertSame('unable_to_verify', $result['status']);
        Http::assertSentCount(1);
    }

    public function test_new_hampshire_unexpected_results_html_requires_manual_review(): void
    {
        $this->fakeNewHampshirePortal('<html><body>Unexpected page</body></html>');
        $result = app(USLicenseCheckVerificationService::class)->verify($this->application(['license_state' => 'NH']));
        $this->assertSame('unable_to_verify', $result['status']);
    }

    public function test_new_hampshire_rejects_non_redirect_search_response(): void
    {
        $this->fakeNewHampshirePortal($this->newHampshireResults(), 200);
        $result = app(USLicenseCheckVerificationService::class)->verify($this->application(['license_state' => 'NH']));
        $this->assertSame('unable_to_verify', $result['status']);
    }

    public function test_new_hampshire_rejects_unexpected_redirect_locations(): void
    {
        $this->fakeNewHampshirePortal($this->newHampshireResults(), 302, 'https://example.com/results');

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application(['license_state' => 'NH']));

        $this->assertSame('unable_to_verify', $result['status']);
        Http::assertSentCount(2);
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
        Log::spy();
        Http::fake([
            self::ENDPOINT => static function () {
                throw new ConnectionException('simulated timeout');
            },
        ]);

        $result = app(USLicenseCheckVerificationService::class)->verify($this->application());

        $this->assertSame('unable_to_verify', $result['status']);
        $this->assertStringNotContainsString('test-key-not-a-secret', $result['message']);
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('USLicenseCheck connection failed', [
                'exception' => ConnectionException::class,
                'reason' => 'simulated timeout',
            ]);
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

    private function fakeNewHampshirePortal(
        string $resultsHtml,
        int $postStatus = 302,
        string $location = '/licenseverification/SearchResults.aspx',
        bool $includeForm = true,
        ?string $searchPageHtml = null
    ): void
    {
        $pageHtml = $searchPageHtml ?? ($includeForm ? $this->newHampshireSearchPage() : '<html><body>Unexpected page</body></html>');
        Http::fake([
            self::NEW_HAMPSHIRE_ENDPOINT => static function (Request $request) use ($pageHtml, $postStatus, $location) {
                if ($request->method() === 'GET') {
                    return Http::response($pageHtml, 200, ['Set-Cookie' => 'ASP.NET_SessionId=fresh-session; Path=/']);
                }

                return Http::response('', $postStatus, ['Location' => $location]);
            },
            self::NEW_HAMPSHIRE_RESULTS => Http::response($resultsHtml),
        ]);
    }

    private function newHampshireSearchPage(): string
    {
        return '<html><body><form method="post" action="/licenseverification/">
            <input type="hidden" name="__EVENTTARGET" value="">
            <input type="hidden" name="__EVENTARGUMENT" value="">
            <input type="hidden" name="__LASTFOCUS" value="">
            <input type="hidden" name="__VIEWSTATE" value="fresh-viewstate">
            <input type="hidden" name="__VIEWSTATEGENERATOR" value="fresh-generator">
            <input type="hidden" name="__EVENTVALIDATION" value="fresh-eventvalidation">
            <input name="t_web_lookup__profession_name">
            <input name="t_web_lookup__license_type_name">
            <input name="t_web_lookup__first_name">
            <input name="t_web_lookup__last_name">
            <input name="t_web_lookup__license_no">
            <input name="sch_button">
            </form></body></html>';
    }

    private function newHampshireResults(array $overrides = [], bool $noResults = false): string
    {
        if ($noResults) {
            return '<html><body><table id="datagrid_results">
                <tr><th>Full Name</th><th>Profession</th><th>License Type</th><th>License Number</th><th>License Status</th></tr>
                </table></body></html>';
        }

        $record = array_merge([
            'full_name' => 'CURTIS, JOHN A, Jr',
            'profession' => 'Medicine',
            'license_type' => 'Physician',
            'license_number' => '15080',
            'status' => 'Active',
        ], $overrides);

        return '<html><body><table id="datagrid_results">
            <tr><th>Full Name</th><th>Profession</th><th>License Type</th><th>License Number</th><th>License Status</th></tr>
            <tr><td>'.$record['full_name'].'</td><td>'.$record['profession'].'</td><td>'.$record['license_type'].'</td><td>'.$record['license_number'].'</td><td>'.$record['status'].'</td></tr>
            </table></body></html>';
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

    private function montanaResults(array $overrides = []): string
    {
        $record = array_merge([
            'licenseNumber' => "<a href='licensedetail?altid=MED-PHYS-LIC-100591'>MED-PHYS-LIC-100591</a>",
            'recordAlias' => 'Physician License',
            'licenseStatus' => 'Active',
            'firstName' => 'Natalia',
            'lastName' => 'Solenkova',
            'orgName' => '',
            'expDate' => '03/31/2027',
            'city' => 'AVENTURA',
            'state' => 'FL',
            'zip' => '33180',
        ], $overrides);

        return '<html><script>var data = '.json_encode([$record], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).';</script></html>';
    }
}
