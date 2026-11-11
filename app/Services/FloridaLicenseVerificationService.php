<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FloridaLicenseVerificationService
{
    private const HOST = 'mqa-internet.doh.state.fl.us';

    private const SEARCH_URL = 'https://mqa-internet.doh.state.fl.us/MQASearchServices/HealthCareProviders';

    private const VERIFICATION_PATH = '/MQASearchServices/HealthcareProviders/LicenseVerification';

    private const ACCEPT = 'text/html,application/xhtml+xml,application/xml;q=0.9,image/jxl,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36';

    public function verify(array $application): array
    {
        $source = 'Florida Department of Health';
        $licenseNumber = trim((string) ($application['license_number'] ?? ''));
        if ($licenseNumber === '') {
            return $this->result('unable_to_verify', 'A license number is required for verification.', $source);
        }

        $startedAt = microtime(true);
        $stage = 'session_initialization';
        $session = new CookieJar;
        Log::info('Florida license verification session initialized', [
            'host' => self::HOST,
            'cookie_names' => [],
        ]);

        try {
            $stage = 'initial_get';
            $requestStartedAt = microtime(true);
            $searchPage = $this->request($session)
                ->withHeaders($this->navigationHeaders('none'))
                ->get(self::SEARCH_URL);
            $this->logResponse($stage, $searchPage, $requestStartedAt, $session, false, $licenseNumber);

            if ($searchPage->status() === 403) {
                return $this->unavailableForResponse($source);
            }
            if (! $searchPage->successful()) {
                return $this->result('unable_to_verify', 'Florida could not provide its license search page. Manual license review is required.', $source);
            }

            $token = $this->extractRequestVerificationToken($searchPage->body());
            Log::info('Florida license verification anti-forgery token extraction completed', [
                'host' => self::HOST,
                'token_obtained' => $token !== null,
            ]);
            if ($token === null) {
                return $this->result('unable_to_verify', 'Florida returned a search page without the required anti-forgery token. Manual license review is required.', $source);
            }

            $fields = [
                '__RequestVerificationToken' => $token,
                'SearchDto.Board' => '15',
                'SearchDto.Profession' => '1501',
                'SearchDto.LicenseNumber' => $licenseNumber,
                'SearchDto.BusinessName' => '',
                'SearchDto.LastName' => '',
                'SearchDto.FirstName' => '',
                'SearchDto.City' => '',
                'SearchDto.County' => '',
                'SearchDto.ZipCode' => '',
                'SearchDto.LicenseStatus' => 'ACT',
            ];

            $stage = 'search_post';
            $requestStartedAt = microtime(true);
            Log::info('Florida license verification search submitted', [
                'host' => self::HOST,
                'token_obtained' => true,
                'cookie_names' => $this->cookieNames($session),
                'profession_code' => '1501',
                'board_code' => '15',
            ]);
            $searchResponse = $this->request($session)
                ->withHeaders($this->navigationHeaders('same-origin', self::SEARCH_URL, true))
                ->asForm()
                ->post(self::SEARCH_URL, $fields);
            $this->logResponse($stage, $searchResponse, $requestStartedAt, $session, true, $licenseNumber);

            if ($searchResponse->status() === 403) {
                return $this->unavailableForResponse($source);
            }
            if ($searchResponse->status() !== 302) {
                if ($searchResponse->successful() && $this->isNotFoundPage($searchResponse->body())) {
                    return $this->result('failed', 'No matching Florida Medical Doctor license record was found.', $source);
                }

                return $this->result('unable_to_verify', 'Florida did not return the expected license search redirect. Manual license review is required.', $source);
            }

            $location = trim((string) $searchResponse->header('Location'));
            Log::info('Florida license verification search redirect received', [
                'host' => self::HOST,
                'http_status' => $searchResponse->status(),
                'location_host' => strtolower((string) parse_url($location, PHP_URL_HOST)) ?: self::HOST,
                'location_path' => parse_url($location, PHP_URL_PATH) ?: '',
            ]);
            $verificationUrl = $this->validatedVerificationUrl($location);
            if ($verificationUrl === null) {
                Log::warning('Florida license verification redirect rejected', [
                    'host' => self::HOST,
                    'reason' => 'unexpected_location',
                ]);

                return $this->result('unable_to_verify', 'Florida returned an unexpected license verification location. Manual license review is required.', $source);
            }

            $stage = 'final_get';
            $requestStartedAt = microtime(true);
            $verificationResponse = $this->request($session)
                ->withHeaders($this->navigationHeaders('same-origin', self::SEARCH_URL))
                ->get($verificationUrl);
            $this->logResponse($stage, $verificationResponse, $requestStartedAt, $session, true, $licenseNumber);

            if ($verificationResponse->status() === 403) {
                return $this->unavailableForResponse($source);
            }
            if ($verificationResponse->status() !== 200) {
                return $this->result('unable_to_verify', 'Florida could not provide the final license record. Manual license review is required.', $source);
            }

            $record = $this->parseVerificationHtml($verificationResponse->body());
            Log::info('Florida license verification HTML parsing completed', [
                'host' => self::HOST,
                'record_parsed' => $record !== null,
            ]);
            if ($record === null) {
                if ($this->isNotFoundPage($verificationResponse->body())) {
                    return $this->result('failed', 'No matching Florida Medical Doctor license record was found.', $source);
                }

                return $this->result('unable_to_verify', 'Florida returned an incomplete or unexpected license record. Manual license review is required.', $source);
            }

            $officialData = $record['data'];
            $numberMatches = $this->normalizeLicenseNumber($officialData['license_number'])
                === $this->normalizeLicenseNumber($licenseNumber);
            Log::info('Florida license verification license-number comparison completed', [
                'host' => self::HOST,
                'matches' => $numberMatches,
            ]);
            if (! $numberMatches) {
                return $this->result('failed', 'The Florida license number does not match the submitted number.', $source, $officialData);
            }

            $professionMatches = $this->isMedicalDoctor($officialData['profession']);
            Log::info('Florida license verification profession comparison completed', [
                'host' => self::HOST,
                'matches' => $professionMatches,
            ]);
            if (! $professionMatches) {
                return $this->result('failed', 'The Florida license record is not for a Medical Doctor.', $source, $officialData);
            }

            if ($record['ambiguous']) {
                return $this->result('unable_to_verify', 'Florida returned an ambiguous license record. Manual license review is required.', $source, $officialData);
            }

            $status = $this->normalizeStatus($officialData['status']);
            Log::info('Florida license verification status normalized', [
                'host' => self::HOST,
                'status' => $status,
            ]);
            if ($status !== 'clear_active') {
                $resultStatus = $status === 'expired' ? 'expired' : 'failed';

                return $this->result($resultStatus, 'The official Florida license status is not Clear/Active.', $source, $officialData);
            }
            if ($officialData['expiration_date'] === null) {
                return $this->result('unable_to_verify', 'The Florida record did not include a usable expiration date. Manual license review is required.', $source, $officialData);
            }
            if ($officialData['expiration_date'] < CarbonImmutable::today()->toDateString()) {
                return $this->result('expired', 'The official Florida license record is expired.', $source, $officialData);
            }

            return $this->result(
                'verified',
                'The Florida Medical Doctor license was verified against the official record.',
                $source,
                $officialData
            );
        } catch (Throwable $exception) {
            $connectionDiagnostics = $this->connectionDiagnostics($exception, $licenseNumber);
            Log::warning('Florida license verification request failed', [
                'stage' => $stage,
                'host' => self::HOST,
                'exception_class' => $exception::class,
                ...$connectionDiagnostics,
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'cookie_names' => $this->cookieNames($session),
            ]);

            return $this->result('unable_to_verify', 'Florida license verification could not be completed. Manual license review is required.', $source);
        }
    }

    private function request(CookieJar $session): PendingRequest
    {
        return Http::withOptions([
            'cookies' => $session,
            'allow_redirects' => false,
        ])
            ->connectTimeout(max(1, (int) config('uslicensecheck.florida_connect_timeout', 30)))
            ->timeout(max(1, (int) config('uslicensecheck.florida_timeout', 90)));
    }

    private function connectionDiagnostics(Throwable $exception, string $licenseNumber): array
    {
        $diagnostics = [
            'connection_error' => $this->sanitizeDiagnostic($exception->getMessage(), $licenseNumber),
        ];

        for ($cause = $exception->getPrevious(); $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof ConnectException) {
                $handlerContext = $cause->getHandlerContext();
                $diagnostics['connection_error'] = $this->sanitizeDiagnostic($cause->getMessage(), $licenseNumber);
                $diagnostics['curl_errno'] = isset($handlerContext['errno']) && is_numeric($handlerContext['errno'])
                    ? (int) $handlerContext['errno']
                    : null;
                $diagnostics['curl_error'] = isset($handlerContext['error']) && is_string($handlerContext['error'])
                    ? $this->sanitizeDiagnostic($handlerContext['error'], $licenseNumber)
                    : null;

                break;
            }

            if ($cause->getMessage() !== '') {
                $diagnostics['connection_error'] = $this->sanitizeDiagnostic($cause->getMessage(), $licenseNumber);
            }
        }

        return $diagnostics;
    }

    private function sanitizeDiagnostic(string $message, string $licenseNumber): string
    {
        if ($licenseNumber !== '') {
            $message = str_ireplace($licenseNumber, '[license redacted]', $message);
        }
        $message = preg_replace('/(?:__RequestVerificationToken|ASP\.NET_SessionId|session(?:\s+id)?|cookie|authorization)\s*[:=]\s*\S+/i', '[sensitive value redacted]', $message) ?? $message;

        return substr($message, 0, 500);
    }

    private function navigationHeaders(string $site, ?string $referer = null, bool $isFormSubmission = false): array
    {
        $headers = [
            'Accept' => self::ACCEPT,
            'Accept-Language' => 'en-PK,en-GB;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cache-Control' => 'max-age=0',
            'Sec-CH-UA' => '"Not:A-Brand";v="8", "Chromium";v="156", "Google Chrome";v="156"',
            'Sec-CH-UA-Mobile' => '?0',
            'Sec-CH-UA-Platform' => '"Windows"',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => $site,
            'Upgrade-Insecure-Requests' => '1',
            'User-Agent' => self::USER_AGENT,
        ];

        if ($referer !== null) {
            $headers['Referer'] = $referer;
        }
        if ($isFormSubmission) {
            $headers['Origin'] = 'https://'.self::HOST;
            $headers['Sec-Fetch-User'] = '?1';
        }

        return $headers;
    }

    private function extractRequestVerificationToken(string $html): ?string
    {
        $document = $this->loadHtml($html);
        if ($document === null) {
            return null;
        }

        $xpath = new DOMXPath($document);
        $inputs = $xpath->query('//form//input[@name="__RequestVerificationToken"]');
        if ($inputs === false || $inputs->length !== 1) {
            return null;
        }

        $token = trim((string) $inputs->item(0)?->attributes?->getNamedItem('value')?->nodeValue);

        return $token !== '' ? $token : null;
    }

    private function validatedVerificationUrl(string $location): ?string
    {
        $parts = parse_url($location);
        if (! is_array($parts)) {
            return null;
        }

        if (isset($parts['scheme']) || isset($parts['host'])) {
            if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
                || strtolower((string) ($parts['host'] ?? '')) !== self::HOST
                || isset($parts['user'])
                || isset($parts['pass'])
                || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
                return null;
            }
        } elseif (isset($parts['port']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        if (($parts['path'] ?? '') !== self::VERIFICATION_PATH || ! isset($parts['query'])) {
            return null;
        }

        parse_str($parts['query'], $query);
        if (! is_string($query['LicInd'] ?? null)
            || preg_match('/^\d+$/', $query['LicInd']) !== 1
            || (string) ($query['Procde'] ?? '') !== '1501') {
            return null;
        }

        return 'https://'.self::HOST.self::VERIFICATION_PATH.'?'.$parts['query'];
    }

    private function parseVerificationHtml(string $html): ?array
    {
        $document = $this->loadHtml($html);
        if ($document === null) {
            return null;
        }

        $labels = [
            'full_name' => ['physician name', 'licensee name', 'license name', 'practitioner name', 'provider name', 'name'],
            'license_number' => ['license number', 'license no', 'license #', 'license'],
            'profession' => ['profession', 'profession name', 'license profession', 'profession type', 'license type'],
            'status' => ['license status', 'status', 'current status'],
            'expiration_date' => ['expiration date', 'license expiration date', 'expiration', 'expires'],
            'original_issue_date' => ['original issue date', 'original date', 'initial license date', 'issue date'],
            'discipline_on_file' => ['discipline on file', 'discipline'],
            'public_complaint' => ['public complaint', 'public complaints', 'complaint on file'],
        ];
        $values = array_fill_keys(array_keys($labels), []);
        $labelLookup = [];
        foreach ($labels as $key => $aliases) {
            foreach ($aliases as $alias) {
                $labelLookup[$this->normalizeLabel($alias)] = $key;
            }
        }

        $xpath = new DOMXPath($document);
        foreach ($xpath->query('//tr') ?: [] as $row) {
            $cells = $xpath->query('./th|./td', $row);
            if ($cells === false) {
                continue;
            }
            for ($index = 0; $index < $cells->length - 1; $index++) {
                $label = $this->labelKey($cells->item($index)?->textContent ?? '', $labelLookup);
                if ($label !== null) {
                    $this->addValue($values[$label], $cells->item($index + 1)?->textContent ?? '');
                }
            }
        }

        foreach ($xpath->query('//dt') ?: [] as $term) {
            $label = $this->labelKey($term->textContent, $labelLookup);
            if ($label !== null) {
                $next = $term->nextElementSibling;
                if ($next instanceof DOMElement && strtolower($next->tagName) === 'dd') {
                    $this->addValue($values[$label], $next->textContent);
                }
            }
        }

        foreach ($xpath->query('//label') ?: [] as $element) {
            $label = $this->labelKey($element->textContent, $labelLookup);
            if ($label === null) {
                continue;
            }

            $value = null;
            $for = trim($element->getAttribute('for'));
            if ($for !== '') {
                foreach ($xpath->query('//*[@id]') ?: [] as $target) {
                    if ($target instanceof DOMElement && $target->getAttribute('id') === $for) {
                        $value = $target->getAttribute('value') ?: $target->textContent;
                        break;
                    }
                }
            }
            if ($value === null && $element->parentNode instanceof DOMElement) {
                $value = $element->parentNode->textContent;
                $value = preg_replace('/^\s*'.preg_quote(trim($element->textContent), '/').'\s*:?\s*/iu', '', $value) ?? $value;
            }
            $this->addValue($values[$label], $value ?? '');
        }

        $ambiguous = false;
        $record = [];
        foreach ($values as $key => $candidates) {
            $candidates = array_values(array_unique(array_filter(array_map(
                fn (string $value): string => $this->cleanText($value),
                $candidates
            ), static fn (string $value): bool => $value !== '')));
            if (count($candidates) > 1) {
                $ambiguous = true;
            }
            $record[$key] = $candidates[0] ?? null;
        }

        foreach (['full_name', 'license_number', 'profession', 'status'] as $requiredField) {
            if (! is_string($record[$requiredField]) || $record[$requiredField] === '') {
                return null;
            }
        }

        $dateInvalid = false;
        foreach (['expiration_date', 'original_issue_date'] as $dateField) {
            if ($record[$dateField] !== null) {
                $normalizedDate = $this->normalizeDate($record[$dateField]);
                if ($normalizedDate === null) {
                    $dateInvalid = true;
                }
                $record[$dateField] = $normalizedDate;
            }
        }
        if ($dateInvalid) {
            return null;
        }

        $record['issue_date'] = $record['original_issue_date'];

        return ['data' => $record, 'ambiguous' => $ambiguous];
    }

    private function loadHtml(string $html): ?DOMDocument
    {
        $document = new DOMDocument;
        $previousErrorMode = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }

        return $loaded ? $document : null;
    }

    private function labelKey(string $label, array $labelLookup): ?string
    {
        $normalized = $this->normalizeLabel($label);

        return $labelLookup[$normalized] ?? null;
    }

    private function normalizeLabel(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $this->cleanText($value)) ?? '');
    }

    private function cleanText(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function addValue(array &$values, string $value): void
    {
        $cleaned = $this->cleanText($value);
        if ($cleaned !== '') {
            $values[] = $cleaned;
        }
    }

    private function normalizeDate(string $value): ?string
    {
        foreach (['!m/d/Y', '!n/j/Y', '!Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, trim($value));
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date !== false
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
                && $date->format(substr($format, 1)) === trim($value)) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function normalizeLicenseNumber(string $value): string
    {
        return strtoupper(preg_replace('/[^a-z0-9]/i', '', trim($value)) ?? '');
    }

    private function isMedicalDoctor(string $profession): bool
    {
        $normalized = $this->normalizeLabel($profession);

        return in_array($normalized, ['medicaldoctor', 'medicaldoctormd', 'doctorofmedicine'], true);
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '', $status) ?? '');
        if ($normalized === 'clearactive') {
            return 'clear_active';
        }
        if (str_contains($normalized, 'expired')) {
            return 'expired';
        }
        if (str_contains($normalized, 'suspended')) {
            return 'suspended';
        }
        if (str_contains($normalized, 'revoked')) {
            return 'revoked';
        }
        if (str_contains($normalized, 'inactive')) {
            return 'inactive';
        }

        return 'other';
    }

    private function isNotFoundPage(string $html): bool
    {
        $text = strtolower($this->cleanText(strip_tags($html)));

        return preg_match('/no (?:matching )?(?:records?|licenses?) found|no results? found|zero results/i', $text) === 1;
    }

    private function logResponse(
        string $stage,
        Response $response,
        float $requestStartedAt,
        CookieJar $session,
        bool $tokenObtained,
        string $licenseNumber
    ): void {
        $context = [
            'stage' => $stage,
            'host' => self::HOST,
            'http_status' => $response->status(),
            'content_type' => $response->header('Content-Type'),
            'response_body_length' => strlen($response->body()),
            'elapsed_ms' => (int) round((microtime(true) - $requestStartedAt) * 1000),
            'token_obtained' => $tokenObtained,
            'cookie_names' => $this->cookieNames($session),
        ];

        if ($response->status() === 403) {
            $context['denial_classification'] = $this->classifyDenial($response->body());
            $context['response_excerpt'] = $this->safeExcerpt($response->body(), $licenseNumber);
        }

        Log::log($response->successful() || $response->status() === 302 ? 'info' : 'warning', 'Florida license verification HTTP response', $context);
    }

    private function unavailableForResponse(string $source): array
    {
        return $this->result('unable_to_verify', 'Florida license verification was denied by the portal. Manual license review is required.', $source);
    }

    private function classifyDenial(string $body): string
    {
        $text = strtolower(strip_tags($body));
        if (preg_match('/captcha|cloudflare|waf|request blocked|access denied|automated request/i', $text) === 1) {
            return 'infrastructure_block';
        }
        if (preg_match('/request verification|anti.?forgery|invalid token|token validation/i', $text) === 1) {
            return 'anti_forgery_rejection';
        }
        if (preg_match('/session (?:has )?(?:expired|timed out|timeout)|invalid session/i', $text) === 1) {
            return 'session_failure';
        }

        return 'application_level_denial';
    }

    private function safeExcerpt(string $body, string $licenseNumber): string
    {
        $body = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $body) ?? $body;
        $body = preg_replace('/<input\b[^>]*>/i', ' [input redacted] ', $body) ?? $body;
        $text = $this->cleanText(strip_tags($body));
        $text = str_ireplace($licenseNumber, '[license redacted]', $text);
        $text = preg_replace('/\b(?:set-cookie|cookie)\s*:\s*[^\r\n]*/i', '[cookies redacted]', $text) ?? $text;
        $text = preg_replace('/(?:__requestverificationtoken|requestverificationtoken|asp\.net_sessionid|session(?:\s+id)?)\s*(?::|=|\s)\s*[^\s,;]+/i', '[sensitive value redacted]', $text) ?? $text;
        $text = preg_replace('/\bauthorization\s*[:=]\s*\S+/i', '[sensitive value redacted]', $text) ?? $text;

        return substr($text, 0, 200);
    }

    private function cookieNames(CookieJar $session): array
    {
        $names = array_map(
            static fn (array $cookie): string => (string) $cookie['Name'],
            $session->toArray()
        );
        sort($names);

        return array_values(array_unique($names));
    }

    private function result(string $status, string $message, string $source, ?array $officialData = null): array
    {
        Log::info('Florida license verification result', [
            'host' => self::HOST,
            'status' => $status,
            'source' => $source,
        ]);

        return [
            'status' => $status,
            'message' => $message,
            'source' => $source,
            'official_data' => $officialData,
        ];
    }
}
