<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class USLicenseCheckVerificationService
{
    public function verify(array $application): array
    {
        $state = strtoupper(trim((string) ($application['license_state'] ?? '')));

        if ($state === 'KS') {
            return $this->verifyKansas($application);
        }

        $source = 'USLicenseCheck';

        if (! in_array($state, config('uslicensecheck.supported_states', []), true)) {
            return $this->result('failed', 'The selected license state is not supported by USLicenseCheck.', $source);
        }

        $baseUrl = rtrim((string) config('uslicensecheck.base_url'), '/');
        $apiKey = (string) config('uslicensecheck.api_key');
        $licenseNumber = trim((string) ($application['license_number'] ?? ''));

        if ($baseUrl === '' || $apiKey === '') {
            return $this->result('unable_to_verify', 'USLicenseCheck is not configured. This application requires manual license review.', $source);
        }

        if ($licenseNumber === '') {
            return $this->result('unable_to_verify', 'A license number is required for verification.', $source);
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders(['X-API-Key' => $apiKey])
                ->connectTimeout(max(1, (int) config('uslicensecheck.connect_timeout', 15)))
                ->timeout(max(1, (int) config('uslicensecheck.timeout', 30)))
                ->post($baseUrl.'/lookup/verify', [
                    'license_number' => $licenseNumber,
                    'source_state' => $state,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('USLicenseCheck connection failed', [
                'exception' => $exception::class,
                'reason' => $exception->getMessage(),
            ]);

            return $this->result('unable_to_verify', 'USLicenseCheck could not be reached. This application requires manual license review.', $source);
        } catch (Throwable) {
            return $this->result('unable_to_verify', 'USLicenseCheck returned an unexpected error. This application requires manual license review.', $source);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            return $this->result('unavailable', 'USLicenseCheck authentication failed. This application requires manual license review.', $source);
        }

        if ($response->status() === 429) {
            return $this->result('unavailable', 'USLicenseCheck rate limit reached. Retry later or use manual license review.', $source);
        }

        if (! $response->successful()) {
            return $this->result('unavailable', 'USLicenseCheck is temporarily unavailable. This application requires manual license review.', $source);
        }

        $payload = $response->json();
        if (! is_array($payload) || ! is_bool($payload['found'] ?? null)) {
            return $this->result('unable_to_verify', 'USLicenseCheck returned an unexpected response. This application requires manual license review.', $source);
        }

        if ($payload['found'] === false) {
            return $this->result('failed', 'No matching license record was found for the submitted state and license number.', $source);
        }

        if (is_array($payload['multiple'] ?? null) && $payload['multiple'] !== []) {
            return $this->result('manual_review', 'USLicenseCheck returned multiple possible records. Manual license review is required.', $source, $payload);
        }

        if (array_key_exists('multiple', $payload)
            && $payload['multiple'] !== null
            && ! is_array($payload['multiple'])) {
            return $this->result('manual_review', 'USLicenseCheck returned an inconclusive matching result. Manual license review is required.', $source, $payload);
        }

        foreach (['license_number', 'board', 'license_type', 'status', 'source_state'] as $requiredField) {
            if (! is_string($payload[$requiredField] ?? null) || trim($payload[$requiredField]) === '') {
                return $this->result('manual_review', 'USLicenseCheck omitted information required to confirm a physician license. Manual review is required.', $source, $payload);
            }
        }

        if ($this->normalizeLicenseNumber($payload['license_number']) !== $this->normalizeLicenseNumber($licenseNumber)) {
            return $this->result('failed', 'The returned license number does not match the submitted number.', $source, $payload);
        }

        if (strtoupper(trim($payload['source_state'])) !== $state) {
            return $this->result('failed', 'The returned license state does not match the selected state.', $source, $payload);
        }

        if (isset($payload['state'])) {
            if (! is_string($payload['state']) || trim($payload['state']) === '') {
                return $this->result('manual_review', 'USLicenseCheck returned an inconclusive license state. Manual review is required.', $source, $payload);
            }

            if (strtoupper(trim($payload['state'])) !== $state) {
                return $this->result('failed', 'The returned license state does not match the selected state.', $source, $payload);
            }
        }

        if (! $this->isPhysicianLicense($payload['board'], $payload['license_type'])) {
            return $this->result('failed', 'This license does not qualify for physician license verification.', $source, $payload);
        }

        $status = strtolower(trim($payload['status']));
        if ($status !== 'active') {
            if (str_contains($status, 'expired')) {
                return $this->result('expired', 'The returned physician license is expired.', $source, $payload);
            }

            if (in_array($status, ['inactive', 'suspended', 'revoked'], true)) {
                return $this->result('failed', 'The returned physician license is not active.', $source, $payload);
            }

            return $this->result('manual_review', 'The returned license status is not recognized as active. Manual review is required.', $source, $payload);
        }

        if (isset($payload['expiry_date']) && $payload['expiry_date'] !== '') {
            $expiration = $this->date($payload['expiry_date']);
            if ($expiration === null) {
                return $this->result('manual_review', 'USLicenseCheck returned an unusable expiration date. Manual review is required.', $source, $payload);
            }

            if ($expiration->isBefore(CarbonImmutable::today())) {
                return $this->result('expired', 'The returned physician license is expired.', $source, $payload);
            }
        }

        return $this->result(
            'verified',
            'The license matched USLicenseCheck data. Confirm current licensure with the issuing state board for credentialing decisions.',
            $source,
            $payload
        );
    }

    private function verifyKansas(array $application): array
    {
        $source = 'Kansas';
        $endpoint = trim((string) config('uslicensecheck.kansas_license_verification_url', 'https://www.kansas.gov/ssrv-ksbhada/search.html'));
        $licenseNumber = trim((string) ($application['license_number'] ?? ''));

        if ($endpoint === '') {
            return $this->result('unable_to_verify', 'Kansas license verification is not configured. This application requires manual license review.', $source);
        }

        if ($licenseNumber === '') {
            return $this->result('unable_to_verify', 'A license number is required for verification.', $source);
        }

        $endpointHost = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        if (strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)) !== 'https' || $endpointHost === '') {
            return $this->result('unable_to_verify', 'Kansas license verification has an invalid endpoint configuration. This application requires manual license review.', $source);
        }

        $normalizedSubmittedLicenseNumber = $this->normalizeLicenseNumber($licenseNumber);
        $startedAt = microtime(true);
        $redirects = [];
        $cookieJar = $this->kansasCookieJar(
            (string) config('uslicensecheck.kansas_request_cookie', ''),
            $endpointHost
        );
        $requestStage = 'search_page';

        try {
            $request = Http::asForm()
                ->withHeaders([
                    'Accept' => 'text/html,application/xhtml+xml',
                    'User-Agent' => 'Mozilla/5.0',
                ])
                ->withOptions([
                    'cookies' => $cookieJar,
                    'allow_redirects' => [
                        'max' => 5,
                        'strict' => false,
                        'track_redirects' => true,
                        'protocols' => ['https'],
                        'on_redirect' => function ($request, $response, $uri) use (&$redirects, $endpointHost): void {
                            $redirectHost = strtolower((string) $uri->getHost());
                            $redirects[] = [
                                'status' => $response->getStatusCode(),
                                'host' => $redirectHost,
                                'path' => $uri->getPath(),
                            ];

                            if ($redirectHost !== $endpointHost) {
                                throw new \RuntimeException('Kansas redirected to an unexpected host.');
                            }
                        },
                    ],
                ])
                ->connectTimeout(max(1, (int) config('uslicensecheck.connect_timeout', 15)))
                ->timeout(max(1, (int) config('uslicensecheck.timeout', 30)));

            $searchResponse = $request->get($endpoint);
            $this->logKansasResponse('search_page', $endpointHost, $searchResponse->status(), $startedAt, $redirects);

            if (! $searchResponse->successful()) {
                $this->logKansasHttpError('search_page', $endpointHost, $searchResponse->status(), $startedAt, $redirects);

                return $this->result('unable_to_verify', 'Kansas could not provide its license search page. This application requires manual license review.', $source);
            }

            $searchHtml = $searchResponse->body();
            if (preg_match('/\b(CAPTCHA|VERIFY YOU ARE HUMAN|ACCESS DENIED|REQUEST BLOCKED|AUTOMATED ACCESS)\b/i', $searchHtml)) {
                $this->logKansasHtmlFailure('automated_access_blocked', $endpointHost, $searchResponse->status(), $startedAt, $redirects);

                return $this->result('unable_to_verify', 'Kansas requires manual review to continue. This application requires manual license review.', $source);
            }

            if (! $this->isKansasSearchForm($searchHtml)) {
                $this->logKansasHtmlFailure('search_form_not_found', $endpointHost, $searchResponse->status(), $startedAt, $redirects);

                return $this->result('unable_to_verify', 'Kansas returned an unexpected search page. This application requires manual license review.', $source);
            }

            $requestStage = 'form_submission';
            $response = $request->post($endpoint, [
                'lastName' => '',
                'firstName' => '',
                'middleInitial' => '',
                'licenseNumber' => $licenseNumber,
                'city' => '',
                'profession' => '04',
                'specialty' => '',
                'submit' => '',
            ]);
        } catch (ConnectionException $exception) {
            $this->logKansasException($exception, $endpointHost, $startedAt, $redirects, $requestStage, $licenseNumber);

            return $this->result('unable_to_verify', 'Kansas could not be reached. This application requires manual license review.', $source);
        } catch (Throwable $exception) {
            $this->logKansasException($exception, $endpointHost, $startedAt, $redirects, $requestStage, $licenseNumber);

            return $this->result('unable_to_verify', 'Kansas returned an unexpected error. This application requires manual license review.', $source);
        }

        $this->logKansasResponse('form_submission', $endpointHost, $response->status(), $startedAt, $redirects);

        if (! $response->successful()) {
            $this->logKansasHttpError('form_submission', $endpointHost, $response->status(), $startedAt, $redirects);

            return $this->result('unable_to_verify', 'Kansas did not return a usable license results response. This application requires manual license review.', $source);
        }

        $html = trim((string) $response->body());
        if ($html === '') {
            $this->logKansasHtmlFailure('empty_response', $endpointHost, $response->status(), $startedAt, $redirects);

            return $this->result('unable_to_verify', 'Kansas returned an empty response. This application requires manual license review.', $source);
        }

        $finalPath = $redirects === []
            ? (string) parse_url($endpoint, PHP_URL_PATH)
            : (string) end($redirects)['path'];

        try {
            $parsedResults = $this->parseKansasResults($html, $finalPath);
        } catch (Throwable $exception) {
            $this->logKansasException($exception, $endpointHost, $startedAt, $redirects, 'results_html', $licenseNumber);

            return $this->result('unable_to_verify', 'Kansas returned an unreadable license results page. This application requires manual license review.', $source);
        }

        if ($parsedResults['parse_failed']) {
            $this->logKansasHtmlFailure('html_parse_failed', $endpointHost, $response->status(), $startedAt, $redirects);

            return $this->result('unable_to_verify', 'Kansas returned an unreadable license results page. This application requires manual license review.', $source);
        }

        if (! $parsedResults['recognized']) {
            $this->logKansasHtmlFailure('results_page_not_recognized', $endpointHost, $response->status(), $startedAt, $redirects);

            return $this->result('unable_to_verify', 'Kansas returned an unexpected license response. This application requires manual license review.', $source);
        }

        $records = $parsedResults['records'];
        if ($records === []) {
            return $this->result('failed', 'No matching Kansas physician license record was found for the submitted license number.', $source);
        }

        $exactMatches = array_values(array_filter(
            $records,
            static fn (array $record): bool => ($record['normalized_license_number'] ?? '') === $normalizedSubmittedLicenseNumber
        ));

        if ($exactMatches === []) {
            return $this->result('failed', 'No exact Kansas license number match was found for the submitted physician license number.', $source, ['records' => $records]);
        }

        if (count($exactMatches) > 1) {
            return $this->result('manual_review', 'Kansas returned multiple exact matches for the submitted license number. Manual review is required.', $source, ['matches' => $exactMatches]);
        }

        $record = $exactMatches[0];
        $profession = strtoupper(trim((string) ($record['profession'] ?? '')));
        $status = strtoupper(trim((string) ($record['status'] ?? '')));

        if ($profession === '') {
            return $this->result('manual_review', 'Kansas omitted the profession required to confirm physician licensure. Manual review is required.', $source, $record);
        }

        if (! preg_match('/^(?:MEDICAL DOCTOR(?:\s*\(MD\))?|MD)$/', $profession)) {
            return $this->result('failed', 'This Kansas license does not qualify for MD physician verification.', $source, $record);
        }

        if (! isset($record['status']) || trim((string) $record['status']) === '') {
            return $this->result('manual_review', 'Kansas omitted the license status required to confirm physician licensure. Manual review is required.', $source, $record);
        }

        if ($status === 'CURRENT') {
            return $this->result(
                'verified',
                'The license matched the Kansas physician registry. Confirm current licensure with the issuing board for credentialing decisions.',
                $source,
                $record
            );
        }

        if (str_contains($status, 'EXPIRED')) {
            return $this->result('expired', 'The Kansas physician license is expired.', $source, $record);
        }

        if (in_array($status, ['INACTIVE', 'SUSPENDED', 'REVOKED'], true)) {
            return $this->result('failed', 'The Kansas physician license is not active.', $source, $record);
        }

        return $this->result('manual_review', 'The returned Kansas license status is not recognized as active. Manual review is required.', $source, $record);
    }

    private function isKansasSearchForm(string $html): bool
    {
        $document = new \DOMDocument;
        $previousErrorSetting = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorSetting);
        }

        if (! $loaded) {
            return false;
        }

        $xpath = new \DOMXPath($document);
        $forms = $xpath->query('//form[.//input[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz") = "licensenumber"]]');

        if ($forms !== false && $forms->length > 0) {
            return true;
        }

        return false;
    }

    private function parseKansasResults(string $html, string $finalPath): array
    {
        $document = new \DOMDocument;
        $previousErrorSetting = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorSetting);
        }

        if (! $loaded) {
            return ['recognized' => false, 'parse_failed' => true, 'records' => []];
        }

        $xpath = new \DOMXPath($document);
        $tables = $xpath->query('//table');
        if ($tables === false) {
            return ['recognized' => false, 'parse_failed' => true, 'records' => []];
        }

        $records = [];
        foreach ($tables as $table) {
            $rows = $xpath->query('.//tr', $table);
            if ($rows === false || $rows->length === 0) {
                continue;
            }

            $columns = [];
            foreach ($rows as $row) {
                $headings = $xpath->query('./th|./td', $row);
                if ($headings === false) {
                    continue;
                }

                $labels = [];
                foreach ($headings as $heading) {
                    $labels[] = strtolower(trim(preg_replace('/\s+/', ' ', $heading->textContent ?? '') ?? ''));
                }

                foreach ($labels as $index => $label) {
                    if (str_contains($label, 'license')) {
                        $columns['license'] = $index;
                    } elseif (str_contains($label, 'profession')) {
                        $columns['profession'] = $index;
                    } elseif (str_contains($label, 'status')) {
                        $columns['status'] = $index;
                    } elseif (str_contains($label, 'location')) {
                        $columns['location'] = $index;
                    } elseif (str_contains($label, 'name')) {
                        $columns['name'] = $index;
                    }
                }

                if (isset($columns['license'], $columns['profession'], $columns['status'])) {
                    break;
                }
            }

            if (! isset($columns['license'], $columns['profession'], $columns['status'])) {
                continue;
            }

            foreach ($rows as $row) {
                $cells = $xpath->query('./td', $row);
                if ($cells === false || $cells->length <= max($columns['license'], $columns['profession'], $columns['status'])) {
                    continue;
                }

                $cellText = static fn (int $index): string => trim(preg_replace('/\s+/', ' ', $cells->item($index)?->textContent ?? '') ?? '');
                $licenseNumber = $cellText($columns['license']);
                if ($licenseNumber === '') {
                    continue;
                }

                $name = isset($columns['name']) ? $cellText($columns['name']) : '';
                $link = $xpath->query('./td/a[1]', $row);
                if ($link !== false && $link->length > 0) {
                    $name = trim(preg_replace('/\s+/', ' ', $link->item(0)?->textContent ?? '') ?? '');
                }

                $records[] = [
                    'name' => $name,
                    'profession' => $cellText($columns['profession']),
                    'license_number' => $licenseNumber,
                    'normalized_license_number' => $this->normalizeLicenseNumber($licenseNumber),
                    'location' => isset($columns['location']) ? $cellText($columns['location']) : '',
                    'status' => $cellText($columns['status']),
                    'source_state' => 'KS',
                ];
            }

            return ['recognized' => true, 'parse_failed' => false, 'records' => $records];
        }

        $path = strtolower(basename($finalPath));
        $pageText = strtoupper(trim(preg_replace('/\s+/', ' ', $document->textContent ?? '') ?? ''));
        $noResults = preg_match('/\b(NO RECORDS|NO RESULTS|0 RESULTS|NO LICENSES FOUND)\b/', $pageText) === 1;

        return [
            'recognized' => $path === 'results.html' && $noResults,
            'parse_failed' => false,
            'records' => [],
        ];
    }

    private function kansasCookieJar(string $cookieHeader, string $host): CookieJar
    {
        $sessionCookies = [];
        foreach (explode(';', $cookieHeader) as $cookie) {
            $parts = explode('=', trim($cookie), 2);
            if (count($parts) !== 2) {
                continue;
            }

            [$name, $value] = $parts;
            $name = trim($name);
            $value = trim($value);

            if ($value !== ''
                && (strcasecmp($name, 'ASP.NET_SessionId') === 0
                    || strcasecmp($name, '__RequestVerificationToken') === 0
                    || str_starts_with(strtolower($name), 'bigipserver'))) {
                $sessionCookies[$name] = $value;
            }
        }

        return CookieJar::fromArray($sessionCookies, $host);
    }

    private function logKansasHttpError(string $stage, string $host, int $status, float $startedAt, array $redirects): void
    {
        Log::warning('Kansas license verification received an HTTP error', [
            'stage' => $stage,
            'failure_type' => str_starts_with((string) $status, '3') ? 'unexpected_redirect' : 'http_error',
            'host' => $host,
            'http_status' => $status,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'redirect_statuses' => array_column($redirects, 'status'),
            'redirect_hosts' => array_column($redirects, 'host'),
        ]);
    }

    private function logKansasResponse(string $stage, string $host, int $status, float $startedAt, array $redirects): void
    {
        Log::info('Kansas license verification HTTP response', [
            'stage' => $stage,
            'host' => $host,
            'http_status' => $status,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'redirect_statuses' => array_column($redirects, 'status'),
            'redirect_hosts' => array_column($redirects, 'host'),
        ]);
    }

    private function logKansasHtmlFailure(string $reason, string $host, int $status, float $startedAt, array $redirects): void
    {
        Log::warning('Kansas license verification HTML could not be used', [
            'reason' => $reason,
            'host' => $host,
            'http_status' => $status,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'redirect_statuses' => array_column($redirects, 'status'),
            'redirect_hosts' => array_column($redirects, 'host'),
        ]);
    }

    private function logKansasException(Throwable $exception, string $host, float $startedAt, array $redirects, string $stage, string $licenseNumber): void
    {
        $message = preg_replace('/https?:\/\/[^\s]+/i', '[URL redacted]', $exception->getMessage()) ?? '';
        $message = str_ireplace($licenseNumber, '[license redacted]', $message);
        $normalizedLicenseNumber = $this->normalizeLicenseNumber($licenseNumber);
        if ($normalizedLicenseNumber !== $licenseNumber) {
            $message = str_ireplace($normalizedLicenseNumber, '[license redacted]', $message);
        }
        $message = preg_replace('/\b(authorization|cookie|api[_-]?key)\b\s*[:=]\s*\S+/i', '$1=[redacted]', $message) ?? $message;

        Log::warning('Kansas license verification request failed', [
            'stage' => $stage,
            'failure_type' => $this->classifyKansasException($exception),
            'exception_class' => $exception::class,
            'exception_message' => $message,
            'host' => $host,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'redirect_statuses' => array_column($redirects, 'status'),
            'redirect_hosts' => array_column($redirects, 'host'),
        ]);
    }

    private function classifyKansasException(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        if (preg_match('/timed?\s*out|timeout|curl error 28/', $message)) {
            return 'timeout';
        }

        if (preg_match('/could not resolve|name or service not known|temporary failure in name resolution|curl error 6/', $message)) {
            return 'dns_error';
        }

        if (preg_match('/tls|ssl|certificate|handshake|curl error (35|60)/', $message)) {
            return 'tls_error';
        }

        if (str_contains($message, 'redirect')) {
            return 'unexpected_redirect';
        }

        return $exception instanceof ConnectionException ? 'connection_error' : 'unexpected_request_error';
    }

    private function isPhysicianLicense(string $board, string $licenseType): bool
    {
        $board = strtolower(trim($board));
        $licenseType = strtolower(trim($licenseType));
        $medicalBoard = str_contains($board, 'medical')
            || str_contains($board, 'medicine')
            || str_contains($board, 'physician');
        $mdType = preg_match('/^(?:md|m\.d\.|doctor of medicine(?:\s*\(md\))?|medical doctor(?:\s*\(md\))?|physician(?:\s+(?:m\.?\s*d\.?|doctor of medicine))?)$/i', $licenseType) === 1;
        $physicianType = $licenseType === 'physician license';

        return $medicalBoard && ($mdType || $physicianType);
    }

    private function normalizeLicenseNumber(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', trim($value)) ?? '');
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value)
            || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)
            || ! checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $value);
    }

    private function result(string $status, string $message, string $source, ?array $officialData = null): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'source' => $source,
            'official_data' => $officialData,
        ];
    }
}
