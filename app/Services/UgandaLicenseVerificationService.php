<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class UgandaLicenseVerificationService
{
    private const SOURCE = 'Uganda Medical and Dental Practitioners Council (UMDPC)';

    private const DEFAULT_SEARCH_URL = 'https://ehealthlicense.go.ug/index.php/search/';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36';

    public function verify(string $registrationNumber): array
    {
        $registrationNumber = trim($registrationNumber);
        if ($registrationNumber === '') {
            return $this->result('failed', 'A Uganda physician registration or license number is required.');
        }

        if (! (bool) config('uganda_license.enabled', true)) {
            return $this->result('unavailable', 'Uganda physician verification is disabled.');
        }

        $initialUrl = trim((string) config('uganda_license.license_verification_url', self::DEFAULT_SEARCH_URL));
        if (! $this->isInitialSearchPageUrl($initialUrl)) {
            return $this->result('unavailable', 'Uganda license verification is not configured.');
        }

        $cookieJar = new CookieJar();
        $initialStartedAt = microtime(true);

        try {
            $initialResponse = $this->requestWithRetry(
                fn () => $this->pendingRequest($cookieJar)->get($initialUrl)
            );
        } catch (ConnectionException|InvalidArgumentException $exception) {
            return $this->connectionFailure(
                $exception,
                'initial_search_page',
                $initialUrl,
                $registrationNumber,
                (int) round((microtime(true) - $initialStartedAt) * 1000)
            );
        }

        $initialTime = (int) round((microtime(true) - $initialStartedAt) * 1000);
        $initialHtml = (string) $initialResponse->body();
        $diagnosticsEnabled = (bool) config('uganda_license.diagnostics', false);

        if (! $initialResponse->successful()) {
            $this->logResponseFailure('initial_search_page', $initialResponse, $initialUrl, $initialHtml, $registrationNumber, $initialTime, [
                'session_cookie_count' => count($cookieJar),
                'session_cookies_received' => count($cookieJar) > 0,
            ]);

            return $this->result('unable_to_verify', 'The Uganda search page could not be loaded to establish a verification session.');
        }

        $form = $this->findPractitionerSearchForm($initialHtml);
        if ($form === null) {
            $this->logUnexpectedHtml('initial_search_page', $initialResponse, $initialUrl, $initialHtml, $registrationNumber, $initialTime, [
                'session_cookie_count' => count($cookieJar),
                'session_cookies_received' => count($cookieJar) > 0,
                'umdpc_selected' => false,
            ]);

            return $this->result('unable_to_verify', 'The Uganda search page did not contain a recognizable practitioner search form.');
        }

        $searchForm = $this->buildSearchForm($initialHtml, $form, $registrationNumber, $initialUrl);
        if ($searchForm === null) {
            $this->logUnexpectedHtml('initial_search_page', $initialResponse, $initialUrl, $initialHtml, $registrationNumber, $initialTime, [
                'session_cookie_count' => count($cookieJar),
                'session_cookies_received' => count($cookieJar) > 0,
                'umdpc_selected' => false,
                'form_found' => true,
            ]);

            return $this->result('unable_to_verify', 'The Uganda search form did not identify a usable UMDPC option and registration-number field.');
        }

        if ($diagnosticsEnabled) {
            Log::info('Uganda UMDPC search form initialized', [
                'country' => 'UG',
                'verification_source' => self::SOURCE,
                'request_stage' => 'initial_search_page',
                'http_status' => $initialResponse->status(),
                'response_time_ms' => $initialTime,
                'final_response_url' => $this->sanitizeDiagnosticUrl($initialResponse->effectiveUri() ? (string) $initialResponse->effectiveUri() : $initialUrl),
                'session_cookie_count' => count($cookieJar),
                'session_cookies_received' => count($cookieJar) > 0,
                'umdpc_selected' => true,
                'form_method' => $searchForm['method'],
                'search_action' => $this->sanitizeDiagnosticUrl($searchForm['action']),
            ]);
        }

        $submissionStartedAt = microtime(true);
        try {
            $request = $this->pendingRequest($cookieJar, $initialUrl);
            $searchResponse = $this->requestWithRetry(function () use ($request, $searchForm) {
                if ($searchForm['method'] === 'GET') {
                    return $request->get($searchForm['action'], $searchForm['parameters']);
                }

                return $request->asForm()->post($searchForm['action'], $searchForm['parameters']);
            });
        } catch (ConnectionException|InvalidArgumentException $exception) {
            return $this->connectionFailure(
                $exception,
                'search_form_submission',
                $searchForm['action'],
                $registrationNumber,
                (int) round((microtime(true) - $submissionStartedAt) * 1000),
                [
                    'session_cookie_count' => count($cookieJar),
                    'session_cookies_received' => count($cookieJar) > 0,
                    'umdpc_selected' => true,
                    'form_method' => $searchForm['method'],
                ]
            );
        }

        $submissionTime = (int) round((microtime(true) - $submissionStartedAt) * 1000);
        $resultsHtml = (string) $searchResponse->body();
        $commonDiagnostics = [
            'session_cookie_count' => count($cookieJar),
            'session_cookies_received' => count($cookieJar) > 0,
            'umdpc_selected' => true,
            'form_method' => $searchForm['method'],
            'search_parameters' => [
                'council_field' => $searchForm['council_field'],
                'council_value' => $searchForm['council_value'],
                'query_field' => $searchForm['query_field'],
                'query_value' => '[REDACTED]',
                'duplicate_council_values_removed' => true,
            ],
        ];

        if (! $searchResponse->successful()) {
            $this->logResponseFailure('search_form_submission', $searchResponse, $searchForm['action'], $resultsHtml, $registrationNumber, $submissionTime, $commonDiagnostics);

            return $this->result('unable_to_verify', 'The Uganda practitioner search request could not be completed.');
        }

        if ($this->isChallengeOrAccessDenied($resultsHtml)) {
            $this->logUnexpectedHtml('search_result_page', $searchResponse, $searchForm['action'], $resultsHtml, $registrationNumber, $submissionTime, $commonDiagnostics);

            return $this->result('unable_to_verify', 'The Uganda portal returned an access-denied or challenge page instead of search results.');
        }

        $records = $this->parseHtmlRecords($resultsHtml);
        if ($records === null) {
            if ($this->hasNoResultsMessage($resultsHtml)) {
                return $this->result('not_found', 'The UMDPC register returned no practitioners matching the submitted number.');
            }

            $this->logUnexpectedHtml('search_result_page', $searchResponse, $searchForm['action'], $resultsHtml, $registrationNumber, $submissionTime, $commonDiagnostics);

            return $this->result('unable_to_verify', 'The Uganda portal returned an unrecognized or changed search-results page.');
        }

        if ($diagnosticsEnabled) {
            Log::info('Uganda UMDPC search result page parsed', array_merge([
                'country' => 'UG',
                'verification_source' => self::SOURCE,
                'request_stage' => 'search_result_page',
            ], $this->responseDiagnostics($searchResponse, $searchForm['action'], $resultsHtml, $registrationNumber, $submissionTime, $commonDiagnostics), [
                'practitioner_record_count' => count($records),
            ]));
        }

        if ($records === []) {
            return $this->result('unable_to_verify', 'The Uganda portal returned a results page whose no-record status could not be confirmed.');
        }

        $matched = [];
        foreach ($records as $record) {
            $submitted = $this->normalizeNumber($registrationNumber);
            $recordNumber = $this->normalizeNumber((string) ($record['registration_number'] ?? ''));
            $licenseNumber = $this->normalizeNumber((string) ($record['license_number'] ?? ''));
            if ($recordNumber !== '' && $submitted === $recordNumber) {
                $matched[] = $record;
            } elseif ($licenseNumber !== '' && $submitted === $licenseNumber) {
                $matched[] = $record;
            }
        }

        if ($matched === []) {
            return $this->result('not_found', 'The UMDPC results did not contain an exact registration or license number match.');
        }

        if (count($matched) > 1) {
            return $this->result('manual_review', 'Multiple Uganda physician records matched the submitted registration number. Manual review is required.');
        }

        $record = $matched[0];
        if (strtoupper(trim((string) ($record['council'] ?? ''))) !== 'UMDPC') {
            return $this->result('manual_review', 'The matching record did not confirm UMDPC as the licensing council.', $this->officialData($record));
        }

        $profession = $this->normalizeProfession($record['profession'] ?? '');
        if ($profession === null) {
            return $this->result('manual_review', 'The Uganda record was found, but the profession could not be confirmed as a physician category.', $this->officialData($record));
        }

        $registrationStatus = $this->normalizeStatus((string) ($record['registration_status'] ?? ''));
        $licenseStatus = $this->normalizeStatus((string) ($record['license_status'] ?? ''));
        $expiry = $this->parseDate((string) ($record['license_expiry'] ?? ''));

        if (in_array($registrationStatus, ['suspended', 'revoked', 'inactive', 'cancelled', 'not_active'], true)
            || in_array($licenseStatus, ['suspended', 'revoked', 'inactive', 'cancelled', 'not_active'], true)) {
            return $this->result('inactive', 'The Uganda physician record is not active and cannot be verified.', $this->officialData($record));
        }

        if ($licenseStatus === 'expired' || ($expiry !== null && $expiry->toDateString() < CarbonImmutable::today()->toDateString())) {
            return $this->result('expired', 'The Uganda physician license has expired or the expiry date has passed.', $this->officialData($record));
        }

        if ($registrationStatus === '' || $licenseStatus === '') {
            return $this->result('manual_review', 'The Uganda record is incomplete or the status could not be interpreted reliably.', $this->officialData($record));
        }

        if (strtolower($registrationStatus) !== 'active' || strtolower($licenseStatus) !== 'active') {
            return $this->result('inactive', 'The Uganda physician registration or license is not currently active.', $this->officialData($record));
        }

        if ($expiry === null) {
            return $this->result('manual_review', 'The Uganda record matched, but the license expiry date could not be confirmed.', $this->officialData($record));
        }

        return $this->result('verified', 'The Uganda physician registration and active license were verified against the official UMDPC portal.', $this->officialData($record));
    }

    private function caBundlePath(): ?string
    {
        $path = trim((string) config('uganda_license.ca_bundle_path', 'D:\\laragon\\etc\\ssl\\cacert.pem'));

        return $path !== '' && is_file($path) ? $path : null;
    }

    private function pendingRequest(CookieJar $cookieJar, ?string $referer = null)
    {
        $headers = [
            'User-Agent' => self::USER_AGENT,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
        ];
        if ($referer !== null) {
            $headers['Referer'] = $referer;
        }

        $options = [
            'allow_redirects' => ['max' => 5, 'track_redirects' => true],
            'cookies' => $cookieJar,
            'version' => 2.0,
            'verify' => $this->caBundlePath() ?? true,
        ];

        return Http::withHeaders($headers)
            ->withOptions($options)
            ->connectTimeout(max(1, (int) config('uganda_license.connect_timeout', 10)))
            ->timeout(max(1, (int) config('uganda_license.timeout', 20)));
    }

    private function requestWithRetry(callable $request): Response
    {
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                return $request();
            } catch (ConnectionException $exception) {
                if ($attempt === 2) {
                    throw $exception;
                }

                usleep(250_000);
            }
        }

        throw new \LogicException('The Uganda portal request retry loop ended unexpectedly.');
    }

    private function findPractitionerSearchForm(string $html): ?\DOMElement
    {
        $document = $this->loadHtml($html);
        $xpath = new DOMXPath($document);
        $forms = $xpath->query('//form[.//input[@type="radio"] and .//input[@type="text"]]');

        return $forms !== false && $forms->length > 0 && $forms->item(0) instanceof \DOMElement
            ? $forms->item(0)
            : null;
    }

    private function buildSearchForm(string $html, \DOMElement $form, string $registrationNumber, string $initialUrl): ?array
    {
        $document = $form->ownerDocument;
        if (! $document instanceof DOMDocument) {
            return null;
        }

        $xpath = new DOMXPath($document);
        $councilField = null;
        $councilValue = null;
        $ahpcValue = null;
        $radioInputs = $xpath->query('.//input[translate(@type, "RADIO", "radio")="radio"]', $form);
        if ($radioInputs === false) {
            return null;
        }

        foreach ($radioInputs as $radio) {
            if (! $radio instanceof \DOMElement || $radio->getAttribute('name') === '' || $radio->getAttribute('value') === '') {
                continue;
            }

            $label = $this->radioLabel($radio);
            if (preg_match('/\bAHPC\b/i', $label)) {
                $ahpcValue = $radio->getAttribute('value');
            }
            if (preg_match('/\bUMDPC\b/i', $label)) {
                $councilField = $radio->getAttribute('name');
                $councilValue = $radio->getAttribute('value');
            }
        }

        if ($councilField === null || $councilValue === null || $councilValue === $ahpcValue) {
            return null;
        }

        $textInputs = $xpath->query('.//input[not(@type) or translate(@type, "TEXT", "text")="text"]', $form);
        $queryField = null;
        if ($textInputs !== false) {
            foreach ($textInputs as $input) {
                if ($input instanceof \DOMElement && $input->getAttribute('name') !== '') {
                    $queryField = $input->getAttribute('name');
                    break;
                }
            }
        }
        if ($queryField === null) {
            return null;
        }

        $parameters = [];
        $hiddenInputs = $xpath->query('.//input[translate(@type, "HIDDEN", "hidden")="hidden"]', $form);
        if ($hiddenInputs !== false) {
            foreach ($hiddenInputs as $input) {
                if (! $input instanceof \DOMElement) {
                    continue;
                }

                $name = $input->getAttribute('name');
                if ($name !== '' && $name !== $councilField && $name !== $queryField) {
                    $parameters[$name] = $input->getAttribute('value');
                }
            }
        }

        // The form's hidden empty issuer and radio option share a name; submit only UMDPC's radio value.
        $parameters[$councilField] = $councilValue;
        $parameters[$queryField] = $registrationNumber;

        $action = trim($form->getAttribute('action'));
        if ($action === '') {
            $action = $initialUrl;
        } elseif (str_starts_with($action, '/')) {
            $action = 'https://ehealthlicense.go.ug' . $action;
        } elseif (! str_contains($action, '://')) {
            $action = rtrim(dirname($initialUrl), '/') . '/' . ltrim($action, '/');
        }

        if (! $this->isOfficialPortalUrl($action)) {
            return null;
        }

        $method = strtoupper(trim($form->getAttribute('method') ?: 'GET'));
        if (! in_array($method, ['GET', 'POST'], true)) {
            return null;
        }

        return [
            'action' => $action,
            'method' => $method,
            'council_field' => $councilField,
            'council_value' => $councilValue,
            'query_field' => $queryField,
            'parameters' => $parameters,
        ];
    }

    private function radioLabel(\DOMElement $radio): string
    {
        for ($node = $radio->parentNode; $node instanceof \DOMElement; $node = $node->parentNode) {
            if (strtolower($node->tagName) === 'label') {
                return $this->safeText($node->textContent);
            }
        }

        $document = $radio->ownerDocument;
        $id = $radio->getAttribute('id');
        if ($document instanceof DOMDocument && $id !== '') {
            $xpath = new DOMXPath($document);
            $labels = $xpath->query('//label[@for="' . addslashes($id) . '"]');
            if ($labels !== false && $labels->length > 0) {
                return $this->safeText($labels->item(0)?->textContent ?? '');
            }
        }

        return '';
    }

    private function isOfficialPortalUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === 'ehealthlicense.go.ug';
    }

    private function isInitialSearchPageUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        return $this->isOfficialPortalUrl($url)
            && in_array($path, ['/index.php/search', '/index.php/search/'], true);
    }

    private function connectionFailure(
        ConnectionException|InvalidArgumentException $exception,
        string $stage,
        string $url,
        string $registrationNumber,
        int $responseTime,
        array $extra = []
    ): array {
        $message = $exception->getMessage();
        $curlNumber = null;
        $curlMessage = null;
        if (preg_match('/cURL error\s+(\d+):\s*(.*?)(?:\s+\(see https?:\/\/|$)/i', $message, $matches)) {
            $curlNumber = (int) $matches[1];
            $curlMessage = preg_replace('/\s+for https?:\/\/\S+.*/i', '', trim($matches[2])) ?? trim($matches[2]);
        }

        $context = array_merge([
            'country' => 'UG',
            'verification_source' => self::SOURCE,
            'request_stage' => $stage,
            'host' => parse_url($url, PHP_URL_HOST),
            'response_time_ms' => $responseTime,
            'error' => $exception::class,
            'curl_error_number' => $curlNumber,
            'curl_error_message' => $curlMessage ?? $exception::class,
        ], $extra);
        if ((bool) config('uganda_license.diagnostics', false)) {
            $context['request_url'] = $this->sanitizeDiagnosticUrl($url);
            $context['request_value_redacted'] = $registrationNumber !== '';
        }
        Log::warning('Uganda UMDPC license verification connection failed', $context);

        return $this->result(
            'unable_to_verify',
            $stage === 'initial_search_page'
                ? 'The Uganda search page could not be reached to initialize verification.'
                : 'The Uganda practitioner search connection failed; the registration could not be verified.'
        );
    }

    private function logResponseFailure(
        string $stage,
        Response $response,
        string $url,
        string $html,
        string $registrationNumber,
        int $responseTime,
        array $extra = []
    ): void {
        $context = array_merge([
            'country' => 'UG',
            'verification_source' => self::SOURCE,
            'request_stage' => $stage,
            'host' => parse_url($url, PHP_URL_HOST),
            'http_status' => $response->status(),
            'response_time_ms' => $responseTime,
            'reason' => $response->reason(),
        ], $extra);
        if ((bool) config('uganda_license.diagnostics', false)) {
            $context = array_merge($context, $this->responseDiagnostics($response, $url, $html, '', $responseTime, $extra));
        }

        Log::warning('Uganda UMDPC license verification request failed', $context);
    }

    private function logUnexpectedHtml(
        string $stage,
        Response $response,
        string $url,
        string $html,
        string $registrationNumber,
        int $responseTime,
        array $extra = []
    ): void {
        $context = array_merge([
            'country' => 'UG',
            'verification_source' => self::SOURCE,
            'request_stage' => $stage,
            'host' => parse_url($url, PHP_URL_HOST),
            'http_status' => $response->status(),
            'response_time_ms' => $responseTime,
        ], $extra);
        if ((bool) config('uganda_license.diagnostics', false)) {
            $context = array_merge($context, $this->responseDiagnostics($response, $url, $html, $registrationNumber, $responseTime, $extra));
        }

        Log::warning('Uganda UMDPC license verification returned unexpected HTML', $context);
    }

    private function responseDiagnostics(
        Response $response,
        string $requestedUrl,
        string $html,
        string $registrationNumber,
        int $responseTime,
        array $extra = []
    ): array {
        $request = $response->transferStats?->getRequest();
        $headers = $response->headers();
        $redirectUrls = $headers['X-Guzzle-Redirect-History'] ?? [];
        $redirectStatuses = $headers['X-Guzzle-Redirect-Status-History'] ?? [];
        $effectiveUri = $response->effectiveUri();

        if (! is_array($redirectUrls)) {
            $redirectUrls = [$redirectUrls];
        }
        if (! is_array($redirectStatuses)) {
            $redirectStatuses = [$redirectStatuses];
        }

        return [
            'final_response_url' => $this->sanitizeDiagnosticUrl($effectiveUri ? (string) $effectiveUri : null),
            'content_type' => $response->header('Content-Type'),
            'redirect_history' => array_values(array_filter(array_map(
                fn ($url): ?string => $this->sanitizeDiagnosticUrl((string) $url),
                $redirectUrls
            ))),
            'redirect_status_history' => array_values($redirectStatuses),
            'response_body_length_bytes' => strlen($html),
            'response_time_ms' => $responseTime,
            'request_details' => [
                'method' => $request?->getMethod() ?? 'GET',
                'url' => $this->sanitizeDiagnosticUrl($requestedUrl),
                'user_agent' => $request?->getHeaderLine('User-Agent'),
                'accept' => $request?->getHeaderLine('Accept'),
                'accept_language' => $request?->getHeaderLine('Accept-Language'),
                'referer_present' => $request !== null && $request->hasHeader('Referer'),
                'cookie_header_present' => $request !== null && $request->hasHeader('Cookie'),
            ],
            'body_indicators' => [
                'javascript_challenge' => (bool) preg_match('/checking your browser|javascript challenge|challenge-platform|cf-chl|enable javascript/i', $html),
                'captcha' => (bool) preg_match('/captcha|recaptcha|hcaptcha|cf-turnstile|turnstile/i', $html),
                'access_denied' => (bool) preg_match('/access denied|forbidden|request blocked|not authorized/i', $html),
                'search_form' => $this->containsSearchForm($html),
                'practitioner_cards' => $this->containsPractitionerCards($html),
            ],
            'response_snippet' => $this->diagnosticSnippet($html, $registrationNumber),
        ];
    }

    private function containsPractitionerCards(string $html): bool
    {
        return (bool) preg_match('/class\s*=\s*["\'][^"\']*\bpro-card\b[^"\']*["\']/i', $html);
    }

    private function containsSearchForm(string $html): bool
    {
        return (bool) preg_match('/<form\b/i', $html)
            && (bool) preg_match('/name\s*=\s*["\']LicenseSearch\[q\]["\']/i', $html);
    }

    private function sanitizeDiagnosticUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $safeUrl = $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . ($parts['path'] ?? '');

        return isset($parts['query']) ? $safeUrl . '?[query redacted]' : $safeUrl;
    }

    private function diagnosticSnippet(string $html, string $registrationNumber): string
    {
        if ($html === '') {
            return '';
        }

        $document = $this->loadHtml($html);
        $xpath = new DOMXPath($document);
        $nodes = $xpath->query(
            '//*[contains(concat(" ", normalize-space(@class), " "), " pro-card ")'
            . ' or self::script or self::style or self::noscript or self::input or self::textarea]'
        );
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $safeHtml = $document->saveHTML($document->documentElement) ?: '';
        $safeHtml = preg_replace('/((?:href|action)\s*=\s*["\'][^"\']*)\?[^"\']*(["\'])/i', '$1?[query redacted]$2', $safeHtml) ?? $safeHtml;
        $safeHtml = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '[email redacted]', $safeHtml) ?? $safeHtml;
        $safeHtml = preg_replace('/(?<![A-Z0-9])\+?\d[\d\s().-]{6,}\d(?![A-Z0-9])/', '[number redacted]', $safeHtml) ?? $safeHtml;
        if ($registrationNumber !== '') {
            $safeHtml = str_ireplace($registrationNumber, '[submitted number redacted]', $safeHtml);
        }

        $snippets = [];
        if ($this->containsSearchForm($html)) {
            $snippets[] = '<form …>…</form> [search fields omitted]';
        }

        foreach ([
            '/captcha|recaptcha|hcaptcha|cf-turnstile|turnstile/i',
            '/checking your browser|javascript challenge|challenge-platform|cf-chl|enable javascript/i',
            '/access denied|forbidden|request blocked|not authorized/i',
            '/LicenseSearch(?:%5B|\[)|\/search\/cadre/i',
        ] as $pattern) {
            if (preg_match($pattern, $safeHtml, $matches, PREG_OFFSET_CAPTURE)) {
                $offset = $matches[0][1];
                $snippets[] = substr($safeHtml, max(0, $offset - 120), 360);
            }
        }

        return substr(implode(' | ', array_unique($snippets)), 0, 2000);
    }

    private function parseHtmlRecords(string $html): ?array
    {
        $document = $this->loadHtml($html);
        $xpath = new DOMXPath($document);
        $cards = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' pro-card ')]");
        if ($cards === false || $cards->length === 0) {
            return null;
        }

        $records = [];
        foreach ($cards as $card) {
            $record = [
                'name' => $this->extractNodeText($xpath, $card, './/*[contains(concat(" ", normalize-space(@class), " "), " pro-card__name ")]'),
                'profession' => $this->extractNodeText($xpath, $card, './/*[contains(concat(" ", normalize-space(@class), " "), " pro-card__sub ")]'),
                'registration_status' => '',
                'license_status' => '',
                'registration_number' => '',
                'license_number' => '',
                'registration_date' => '',
                'license_expiry' => '',
                'council' => $this->extractNodeText($xpath, $card, './/*[contains(concat(" ", normalize-space(@class), " "), " pro-card__council-tag ")]'),
                'profile_url' => '',
            ];

            $details = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " pro-card__details ")]//dt', $card);
            if ($details !== false) {
                foreach ($details as $term) {
                    $valueNode = $xpath->query('following-sibling::dd[1]', $term)?->item(0);
                    if ($valueNode === null) {
                        continue;
                    }

                    $label = strtolower(trim(preg_replace('/\s+/', ' ', $term->textContent) ?? ''));
                    $value = $this->safeText($valueNode->textContent);
                    if ($label === 'reg. no' || $label === 'registration no' || $label === 'reg no') {
                        $record['registration_number'] = $value;
                    } elseif (in_array($label, ['licence no', 'license no', 'licence number', 'license number'], true)) {
                        $record['license_number'] = $value;
                    } elseif (in_array($label, ['registered', 'registration date'], true)) {
                        $record['registration_date'] = $value;
                    } elseif (in_array($label, ['licence expiry', 'license expiry', 'licence expires', 'license expires'], true)) {
                        $record['license_expiry'] = $value;
                    }
                }
            }

            $pills = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " pill-status ")]', $card);
            if ($pills !== false) {
                foreach ($pills as $pill) {
                    $statusText = $this->safeText($pill->textContent);
                    if (preg_match('/^Registration\s*:\s*(.+)$/i', $statusText, $matches)) {
                        $record['registration_status'] = trim($matches[1]);
                    } elseif (preg_match('/^License\s*:\s*(.+)$/i', $statusText, $matches)) {
                        $record['license_status'] = trim($matches[1]);
                    }
                }
            }

            $profileLinks = $xpath->query('.//a[@href]', $card);
            if ($profileLinks !== false) {
                foreach ($profileLinks as $link) {
                    $href = trim($link->getAttribute('href'));
                    if ($href !== '' && ! str_starts_with(strtolower($href), 'javascript:')) {
                        $record['profile_url'] = str_starts_with($href, 'http')
                            ? $href
                            : 'https://ehealthlicense.go.ug/' . ltrim($href, '/');
                        break;
                    }
                }
            }

            $record['registration_status'] = $this->normalizeStatus($record['registration_status']);
            $record['license_status'] = $this->normalizeStatus($record['license_status']);
            $record['registration_date'] = $this->normalizeDateString($record['registration_date']);
            $record['license_expiry'] = $this->normalizeDateString($record['license_expiry']);
            $records[] = $record;
        }

        return $records;
    }

    private function loadHtml(string $html): DOMDocument
    {
        $previousLibxmlState = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlState);

        return $document;
    }

    private function extractNodeText(DOMXPath $xpath, ?\DOMNode $contextNode, string $query): string
    {
        if ($contextNode === null) {
            return '';
        }

        $nodes = $xpath->query($query, $contextNode);
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }

        $parts = [];
        foreach ($nodes as $node) {
            $parts[] = $this->safeText($node->textContent ?? '');
        }

        return implode(' ', array_filter($parts));
    }

    private function hasNoResultsMessage(string $html): bool
    {
        $text = $this->safeText($this->loadHtml($html)->textContent ?? '');

        return (bool) preg_match('/no practitioners match your search/i', $text);
    }

    private function isChallengeOrAccessDenied(string $html): bool
    {
        return (bool) preg_match(
            '/access denied|request blocked|not authorized|captcha|recaptcha|hcaptcha|cf-turnstile|checking your browser|javascript challenge|challenge-platform|cf-chl/i',
            $html
        );
    }

    private function normalizeProfession(string $profession): ?string
    {
        $profession = trim($profession);
        if ($profession === '') {
            return null;
        }

        $lower = strtolower($profession);
        if (str_contains($lower, 'dentist') || str_contains($lower, 'dental') || str_contains($lower, 'clinical officer') || str_contains($lower, 'nurse') || str_contains($lower, 'pharmacist') || str_contains($lower, 'midwife')) {
            return null;
        }

        $acceptedPatterns = [
            'physician',
            'medical doctor',
            'doctor',
            'general practitioner',
            'gp',
            'specialist',
            'surgeon',
            'consultant',
            'medical officer',
        ];

        foreach ($acceptedPatterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                return $profession;
            }
        }

        return null;
    }

    private function normalizeStatus(string $status): string
    {
        $status = trim($status);
        if ($status === '') {
            return '';
        }

        $lower = strtolower($status);
        if (str_contains($lower, 'not active') || str_contains($lower, 'inactive') || str_contains($lower, 'suspend') || str_contains($lower, 'revoke') || str_contains($lower, 'cancel')) {
            return 'inactive';
        }

        if (str_contains($lower, 'expired')) {
            return 'expired';
        }

        if (str_contains($lower, 'active')) {
            return 'active';
        }

        return $status;
    }

    private function normalizeDateString(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'Y/m/d', 'd.m.Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!' . $format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date->toDateString();
                }
            } catch (InvalidArgumentException) {
                // Ignore invalid dates and continue.
            }
        }

        return $value;
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'Y/m/d', 'd.m.Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!' . $format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date;
                }
            } catch (InvalidArgumentException) {
                // Ignore invalid dates and continue.
            }
        }

        return null;
    }

    private function normalizeNumber(string $value): string
    {
        return strtoupper(trim(preg_replace('/\s+/', '', str_replace(['-', '_'], '', $value)) ?? ''));
    }

    private function safeText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function officialData(array $record): array
    {
        return [
            'practitioner_name' => $record['name'] ?? null,
            'profession' => $record['profession'] ?? null,
            'council' => $record['council'] ?? 'UMDPC',
            'registration_number' => $record['registration_number'] ?? null,
            'license_number' => $record['license_number'] ?? null,
            'registration_status' => $record['registration_status'] ?? null,
            'license_status' => $record['license_status'] ?? null,
            'registration_date' => $record['registration_date'] ?? null,
            'license_expiry' => $record['license_expiry'] ?? null,
            'profile_url' => $record['profile_url'] ?? null,
            'source_url' => config('uganda_license.license_verification_url', 'https://ehealthlicense.go.ug/index.php/search/cadre'),
            'source' => self::SOURCE,
            'verified_at' => now()->toIso8601String(),
        ];
    }

    private function result(string $status, string $message, ?array $officialData = null): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'source' => self::SOURCE,
            'official_data' => $officialData,
        ];
    }
}
