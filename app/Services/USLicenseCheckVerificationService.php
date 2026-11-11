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
    public function __construct(private FloridaLicenseVerificationService $floridaVerifier) {}

    public function verify(array $application): array
    {
        $state = strtoupper(trim((string) ($application['license_state'] ?? '')));

        if ($state === 'FL') {
            return $this->floridaVerifier->verify($application);
        }

        if ($state === 'KS') {
            return $this->verifyKansas($application);
        }

        if ($state === 'MT') {
            return $this->verifyMontana($application);
        }

        if ($state === 'NH') {
            return $this->verifyNewHampshire($application);
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

    private function verifyMontana(array $application): array
    {
        $source = 'Montana';
        $endpoint = trim((string) config(
            'uslicensecheck.montana_license_verification_url',
            'https://ebizws.mt.gov/PUBLICPORTAL/searchform?mylist=licenses'
        ));
        $licenseNumber = trim((string) ($application['license_number'] ?? ''));

        if ($endpoint === '') {
            return $this->result('unable_to_verify', 'Montana license verification is not configured. This application requires manual license review.', $source);
        }

        if ($licenseNumber === '') {
            return $this->result('unable_to_verify', 'A license number is required for verification.', $source);
        }

        $formattedLicenseNumber = $this->formatMontanaLicenseNumber($licenseNumber);
        if ($formattedLicenseNumber === null) {
            return $this->result('manual_review', 'The Montana physician license number is not in a recognizable format. Manual license review is required.', $source);
        }

        $parsedEndpoint = parse_url($endpoint);
        if (! is_array($parsedEndpoint)
            || strtolower((string) ($parsedEndpoint['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($parsedEndpoint['host'] ?? '')) !== 'ebizws.mt.gov'
            || (string) ($parsedEndpoint['path'] ?? '') !== '/PUBLICPORTAL/searchform') {
            return $this->result('unable_to_verify', 'Montana license verification has an invalid endpoint configuration. This application requires manual license review.', $source);
        }

        $searchPageUrl = $endpoint;
        $verificationUrl = 'https://ebizws.mt.gov/PUBLICPORTAL/verif';
        $searchUrl = 'https://ebizws.mt.gov/PUBLICPORTAL/searchform';
        $searchParameters = [
            'licboard' => 'Medical Examiners',
            'lictype' => 'Medical Examiners Medical Doctor License',
            'licnumber' => $formattedLicenseNumber,
            'firstname' => '',
            'lastname' => '',
            'businessname' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'country' => '',
            'mylist' => 'license',
        ];
        $startedAt = microtime(true);
        $stage = 'search_page';
        $session = new CookieJar;

        try {
            $request = Http::asForm()
                ->withHeaders([
                    'Accept' => 'text/html,application/xhtml+xml,application/json',
                    'User-Agent' => 'Mozilla/5.0',
                ])
                ->withOptions([
                    'cookies' => $session,
                    'allow_redirects' => false,
                ])
                ->connectTimeout(max(1, (int) config('uslicensecheck.connect_timeout', 15)))
                ->timeout(max(1, (int) config('uslicensecheck.timeout', 30)));

            $pageResponse = $request->get($searchPageUrl);
            $pageBody = $pageResponse->body();
            $isSecurityPage = $this->isMontanaSecurityResponse($pageBody);
            $isVerificationQuestion = $this->hasMontanaVerificationQuestion($pageBody);
            if ($isSecurityPage || $isVerificationQuestion) {
                $this->logMontanaFailure('search_page_unavailable_or_blocked', $stage, $pageResponse->status(), $startedAt);
                $this->logMontanaResponseDiagnostics($pageResponse, $searchPageUrl, $pageBody, $stage);

                return $this->result('manual_review', 'Montana requires additional verification to continue. Manual license review is required.', $source);
            }

            if (! $pageResponse->successful()
                || trim($pageBody) === ''
                || ! $this->hasMontanaSearchForm($pageBody)) {
                $this->logMontanaFailure('search_page_unavailable_or_blocked', $stage, $pageResponse->status(), $startedAt);
                $this->logMontanaResponseDiagnostics($pageResponse, $searchPageUrl, $pageBody, $stage);

                return $this->result('unable_to_verify', 'Montana could not provide its license search page. This application requires manual license review.', $source);
            }

            $stage = 'verification_step';
            $verificationResponse = $request->post($verificationUrl, $searchParameters);
            $verificationBody = $verificationResponse->body();
            if ($this->isMontanaSecurityResponse($verificationBody)
                || $this->hasMontanaVerificationQuestion($verificationBody)) {
                $this->logMontanaFailure('verification_step_requires_manual_review', $stage, $verificationResponse->status(), $startedAt);
                $this->logMontanaResponseDiagnostics($verificationResponse, $verificationUrl, $verificationBody, $stage);

                return $this->result('manual_review', 'Montana requires additional verification to continue. Manual license review is required.', $source);
            }

            if (! $verificationResponse->successful()
                || $this->montanaVerificationRejected($verificationBody)) {
                $this->logMontanaFailure('verification_step_unavailable_or_rejected', $stage, $verificationResponse->status(), $startedAt);

                return $this->result('unable_to_verify', 'Montana could not complete its license search workflow. This application requires manual license review.', $source);
            }

            $stage = 'search_submission';
            $searchResponse = $request->post($searchUrl, $searchParameters);
            $searchBody = $searchResponse->body();
            if ($this->isMontanaSecurityResponse($searchBody)
                || $this->hasMontanaVerificationQuestion($searchBody)) {
                $this->logMontanaFailure('search_response_requires_manual_review', $stage, $searchResponse->status(), $startedAt);
                $this->logMontanaResponseDiagnostics($searchResponse, $searchUrl, $searchBody, $stage);

                return $this->result('manual_review', 'Montana requires additional verification to continue. Manual license review is required.', $source);
            }

            if (! $searchResponse->successful() || trim($searchBody) === '') {
                $this->logMontanaFailure('search_response_unavailable_or_blocked', $stage, $searchResponse->status(), $startedAt);

                return $this->result('unable_to_verify', 'Montana could not provide license search results. This application requires manual license review.', $source);
            }
        } catch (ConnectionException $exception) {
            $this->logMontanaException($exception, $stage, $startedAt);

            return $this->result('unable_to_verify', 'Montana could not be reached. This application requires manual license review.', $source);
        } catch (Throwable $exception) {
            $this->logMontanaException($exception, $stage, $startedAt);

            return $this->result('unable_to_verify', 'Montana returned an unexpected error. This application requires manual license review.', $source);
        }

        $records = $this->parseMontanaData($searchBody);
        if ($records === null) {
            $this->logMontanaFailure('results_data_missing_or_invalid', 'results_html', $searchResponse->status(), $startedAt);

            return $this->result('unable_to_verify', 'Montana returned unreadable license search results. This application requires manual license review.', $source);
        }

        if ($records === []) {
            return $this->result('manual_review', 'Montana returned no license records. Manual license review is required.', $source);
        }

        $submittedNumber = $this->normalizeMontanaLicenseNumber($licenseNumber);
        $matchingRecords = [];
        foreach ($records as $record) {
            if (! is_array($record)) {
                $this->logMontanaFailure('malformed_result_record', 'results_html', $searchResponse->status(), $startedAt);

                return $this->result('unable_to_verify', 'Montana returned an unexpected license record. This application requires manual license review.', $source);
            }

            $returnedNumber = $this->montanaLicenseNumber($record['licenseNumber'] ?? null);
            if ($returnedNumber !== null
                && $this->normalizeMontanaLicenseNumber($returnedNumber) === $submittedNumber) {
                $matchingRecords[] = [$record, $returnedNumber];
            }
        }

        if ($matchingRecords === []) {
            return $this->result('failed', 'No matching Montana physician license record was found for the submitted license number.', $source);
        }

        if (count($matchingRecords) !== 1) {
            return $this->result('manual_review', 'Montana returned multiple matching records. Manual license review is required.', $source);
        }

        [$record, $returnedNumber] = $matchingRecords[0];
        $recordAlias = $this->montanaText($record['recordAlias'] ?? null);
        $licenseStatus = $this->montanaText($record['licenseStatus'] ?? null);
        $expirationValue = $this->montanaText($record['expDate'] ?? null);

        if ($recordAlias === null
            || trim($recordAlias) === ''
            || $licenseStatus === null
            || trim($licenseStatus) === ''
            || $expirationValue === null
            || trim($expirationValue) === '') {
            return $this->result('manual_review', 'Montana omitted information required to confirm physician licensure. Manual review is required.', $source);
        }

        $officialData = [
            'licenseNumber' => $returnedNumber,
            'recordAlias' => $recordAlias,
            'licenseStatus' => $licenseStatus,
            'firstName' => $this->montanaText($record['firstName'] ?? null),
            'lastName' => $this->montanaText($record['lastName'] ?? null),
            'orgName' => $this->montanaText($record['orgName'] ?? null),
            'expDate' => $expirationValue,
            'city' => $this->montanaText($record['city'] ?? null),
            'state' => $this->montanaText($record['state'] ?? null),
            'zip' => $this->montanaText($record['zip'] ?? null),
        ];
        $expiration = $this->montanaDate($expirationValue);
        $officialData['license_number'] = $returnedNumber;
        $officialData['license_type'] = $recordAlias;
        $officialData['status'] = $licenseStatus;
        $officialData['expiration_date'] = $expiration?->toDateString();

        if (strtolower($recordAlias) !== 'physician license') {
            return $this->result('failed', 'This Montana record does not qualify as a physician license.', $source, $officialData);
        }

        if (strtolower($licenseStatus) !== 'active') {
            if (str_contains(strtolower($licenseStatus), 'expired')) {
                return $this->result('expired', 'The Montana physician license is expired.', $source, $officialData);
            }

            if (in_array(strtolower($licenseStatus), ['inactive', 'suspended', 'revoked'], true)) {
                return $this->result('failed', 'The Montana physician license is not active.', $source, $officialData);
            }

            return $this->result('manual_review', 'Montana returned an unrecognized license status. Manual license review is required.', $source, $officialData);
        }

        if ($expiration === null) {
            return $this->result('manual_review', 'Montana returned an unusable expiration date. Manual review is required.', $source, $officialData);
        }

        if ($expiration->isBefore(CarbonImmutable::today())) {
            return $this->result('expired', 'The Montana physician license is expired.', $source, $officialData);
        }

        return $this->result(
            'verified',
            'The license matched the Montana physician registry. Confirm current licensure with the issuing board for credentialing decisions.',
            $source,
            $officialData
        );
    }

    private function parseMontanaData(string $html): ?array
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
            return null;
        }

        $scripts = (new \DOMXPath($document))->query('//script');
        if ($scripts === false) {
            return null;
        }

        foreach ($scripts as $script) {
            $scriptContent = (string) ($script->textContent ?? '');
            if (preg_match('/\b(?:var|let|const)\s+data\s*=\s*/', $scriptContent, $assignment, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }

            $arrayStart = $assignment[0][1] + strlen($assignment[0][0]);
            while (isset($scriptContent[$arrayStart]) && ctype_space($scriptContent[$arrayStart])) {
                $arrayStart++;
            }
            if (($scriptContent[$arrayStart] ?? '') !== '[') {
                return null;
            }

            $json = $this->extractMontanaJsonArray($scriptContent, $arrayStart);
            if ($json === null) {
                return null;
            }

            try {
                $records = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                try {
                    $records = json_decode(
                        html_entity_decode($json, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                } catch (\JsonException) {
                    return null;
                }
            }

            return is_array($records) && array_is_list($records) ? $records : null;
        }

        return null;
    }

    private function extractMontanaJsonArray(string $content, int $start): ?string
    {
        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($content);

        for ($index = $start; $index < $length; $index++) {
            $character = $content[$index];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($character === '"') {
                $inString = true;
            } elseif ($character === '[') {
                $depth++;
            } elseif ($character === ']') {
                $depth--;
                if ($depth === 0) {
                    return substr($content, $start, $index - $start + 1);
                }
            }
        }

        return null;
    }

    private function montanaLicenseNumber(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $html = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $document = new \DOMDocument;
        $previousErrorSetting = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8"><div id="license-number">'. $html .'</div>');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorSetting);
        }

        if (! $loaded) {
            return null;
        }

        $licenseNumber = trim((string) $document->getElementById('license-number')?->textContent);

        return $licenseNumber !== '' ? html_entity_decode($licenseNumber, ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
    }

    private function montanaText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function normalizeMontanaLicenseNumber(string $value): string
    {
        $normalized = $this->normalizeLicenseNumber($value);
        $prefix = 'medphyslic';

        while (str_starts_with($normalized, $prefix)) {
            $normalized = substr($normalized, strlen($prefix));
        }

        return $normalized;
    }

    private function formatMontanaLicenseNumber(string $value): ?string
    {
        $number = $this->normalizeMontanaLicenseNumber($value);
        if ($number === '' || ! ctype_digit($number)) {
            return null;
        }

        return 'MED-PHYS-LIC-'.$number;
    }

    private function montanaDate(string $value): ?CarbonImmutable
    {
        $value = trim($value);
        $isoDate = $this->date($value);
        if ($isoDate !== null) {
            return $isoDate;
        }

        if (! preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $value)) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!n/j/Y', $value);
        $errors = CarbonImmutable::getLastErrors();
        if ($date === false
            || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date;
    }

    private function isMontanaSecurityResponse(string $body): bool
    {
        return preg_match('/\b(?:CAPTCHA|VERIFY YOU ARE HUMAN|ACCESS DENIED|ACCESS REJECTED|REQUEST REJECTED|REQUEST BLOCKED|AUTOMATED ACCESS|SECURITY INCIDENT|SUPPORT ID|TSPD|IMPERVA|INCAPSULA)\b/i', $body) === 1;
    }

    private function hasMontanaSearchForm(string $body): bool
    {
        return preg_match('/<form\b/i', $body) === 1
            && preg_match('/\b(?:licnumber|license[\s_-]*number)\b/i', $body) === 1;
    }

    private function hasMontanaVerificationQuestion(string $body): bool
    {
        return preg_match('/\b(?:verification|security)[\s_-]*questions?\b|\bquestions?[\s_-]+(?:and|&amp;|\/)?[\s_-]*answers?\b|\banswer\s+to\s+(?:the\s+)?questions?\b/i', $body) === 1;
    }

    private function logMontanaResponseDiagnostics(
        \Illuminate\Http\Client\Response $response,
        string $requestedUrl,
        string $body,
        string $stage
    ): void {
        $isSecurityPage = $this->isMontanaSecurityResponse($body);
        $isVerificationQuestion = $this->hasMontanaVerificationQuestion($body);
        $hasResultsData = preg_match('/\bvar\s+data\s*=/i', $body) === 1;
        $hasSearchForm = $this->hasMontanaSearchForm($body);
        $responseType = match (true) {
            $isSecurityPage => 'portal_security_page',
            $isVerificationQuestion => 'verification_question_page',
            $hasResultsData => 'results_page',
            $hasSearchForm => 'initial_search_page',
            default => 'unrecognized_page',
        };
        $effectiveUri = $response->effectiveUri();
        $responseUrl = is_object($effectiveUri) ? (string) $effectiveUri : $requestedUrl;
        $parsedUrl = parse_url($responseUrl);
        $safeUrl = is_array($parsedUrl)
            ? strtolower((string) ($parsedUrl['scheme'] ?? 'https')).'://'
                .strtolower((string) ($parsedUrl['host'] ?? 'ebizws.mt.gov'))
                .(isset($parsedUrl['port']) ? ':'.$parsedUrl['port'] : '')
                .(string) ($parsedUrl['path'] ?? '/')
            : 'https://ebizws.mt.gov/PUBLICPORTAL/searchform';
        $title = '';
        if (preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $body, $matches) === 1) {
            $title = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');
            if (preg_match('/\b(?:cookie|session|token|password|credential|secret|authorization|api[\s_-]*key)\b|[A-Za-z0-9_-]{24,}|[@=]|\bhttps?:\/\//i', $title) === 1) {
                $title = '[redacted]';
            } else {
                $title = substr($title, 0, 120);
            }
        }

        Log::warning('Montana license response diagnostics', [
            'stage' => $stage,
            'response_type' => $responseType,
            'http_status' => $response->status(),
            'final_response_url' => $safeUrl,
            'content_type' => $response->header('Content-Type'),
            'response_body_length' => strlen($body),
            'page_title' => $title,
            'var_data_present' => $hasResultsData,
            'search_form_present' => $hasSearchForm,
            'verification_question_present' => $isVerificationQuestion,
            'portal_security_page' => $isSecurityPage,
        ]);
    }

    private function montanaVerificationRejected(string $body): bool
    {
        $payload = json_decode($body, true);

        return is_array($payload)
            && (($payload['success'] ?? null) === false
                || ($payload['verified'] ?? null) === false
                || ($payload['allowed'] ?? null) === false);
    }

    private function logMontanaFailure(string $reason, string $stage, int $status, float $startedAt): void
    {
        Log::warning('Montana license verification response could not be used', [
            'reason' => $reason,
            'stage' => $stage,
            'host' => 'ebizws.mt.gov',
            'http_status' => $status,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }

    private function logMontanaException(Throwable $exception, string $stage, float $startedAt): void
    {
        Log::warning('Montana license verification request failed', [
            'stage' => $stage,
            'exception_class' => $exception::class,
            'host' => 'ebizws.mt.gov',
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }

    private function verifyNewHampshire(array $application): array
    {
        $source = 'New Hampshire';
        $endpoint = trim((string) config(
            'uslicensecheck.new_hampshire_license_verification_url',
            'https://forms.nh.gov/licenseverification/'
        ));
        $licenseNumber = trim((string) ($application['license_number'] ?? ''));

        if ($endpoint === '') {
            return $this->result('unable_to_verify', 'New Hampshire license verification is not configured. This application requires manual license review.', $source);
        }

        if ($licenseNumber === '') {
            return $this->result('unable_to_verify', 'A license number is required for verification.', $source);
        }

        $endpointHost = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        if (strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)) !== 'https'
            || $endpointHost !== 'forms.nh.gov') {
            return $this->result('unable_to_verify', 'New Hampshire license verification has an invalid endpoint configuration. This application requires manual license review.', $source);
        }

        $startedAt = microtime(true);
        $requestStage = 'search_page';
        $configuredCookie = (string) config('uslicensecheck.new_hampshire_request_cookie', '');
        $session = $this->newHampshireCookieJar(
            $configuredCookie,
            $endpointHost
        );
        $hasConfiguredCookie = trim($configuredCookie) !== '';

        try {
            $request = Http::withHeaders([
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'Mozilla/5.0',
            ])
                ->withOptions([
                    'cookies' => $session,
                    'allow_redirects' => false,
                ])
                ->connectTimeout(max(1, (int) config('uslicensecheck.connect_timeout', 15)))
                ->timeout(max(1, (int) config('uslicensecheck.timeout', 30)));

            $this->logNewHampshireCookieDiagnostics($requestStage, $session, $hasConfiguredCookie);
            $pageResponse = $request->get($endpoint);
            if (! $pageResponse->successful()) {
                $this->logNewHampshireFailure('search_page_http_error', $endpointHost, $pageResponse->status(), $startedAt, $requestStage);

                return $this->result('unable_to_verify', 'New Hampshire could not provide its license search page. This application requires manual license review.', $source);
            }

            $form = $this->parseNewHampshireSearchForm($pageResponse->body(), $endpoint);
            if ($form === null) {
                $this->logNewHampshireFailure('search_form_or_hidden_fields_missing', $endpointHost, $pageResponse->status(), $startedAt, $requestStage);

                return $this->result('unable_to_verify', 'New Hampshire returned an unexpected search page. This application requires manual license review.', $source);
            }

            $requestStage = 'form_submission';
            $this->logNewHampshireCookieDiagnostics($requestStage, $session, $hasConfiguredCookie);
            $postResponse = $request->asForm()->post($form['action'], array_merge($form['fields'], [
                't_web_lookup__profession_name' => 'Medicine',
                't_web_lookup__license_type_name' => 'Physician',
                't_web_lookup__first_name' => '',
                't_web_lookup__last_name' => '',
                't_web_lookup__license_no' => $licenseNumber,
                'sch_button' => 'Search',
            ]));

            if ($postResponse->status() !== 302) {
                $this->logNewHampshireFailure('unexpected_search_redirect', $endpointHost, $postResponse->status(), $startedAt, $requestStage);

                return $this->result('unable_to_verify', 'New Hampshire did not return the expected search results redirect. This application requires manual license review.', $source);
            }

            $location = trim((string) $postResponse->header('Location'));
            $resultsUrl = $this->resolveNewHampshireUrl($endpoint, $location);
            if ($resultsUrl === null
                || parse_url($resultsUrl, PHP_URL_PATH) !== '/licenseverification/SearchResults.aspx') {
                $this->logNewHampshireFailure('unexpected_results_location', $endpointHost, $postResponse->status(), $startedAt, $requestStage);

                return $this->result('unable_to_verify', 'New Hampshire returned an unexpected search results location. This application requires manual license review.', $source);
            }

            $requestStage = 'results_page';
            $this->logNewHampshireCookieDiagnostics($requestStage, $session, $hasConfiguredCookie);
            $resultsResponse = $request->get($resultsUrl);
            if (! $resultsResponse->successful()) {
                $this->logNewHampshireFailure('results_page_http_error', $endpointHost, $resultsResponse->status(), $startedAt, $requestStage);

                return $this->result('unable_to_verify', 'New Hampshire could not provide its license search results. This application requires manual license review.', $source);
            }
        } catch (ConnectionException $exception) {
            $this->logNewHampshireException($exception, $endpointHost, $startedAt, $requestStage);

            return $this->result('unable_to_verify', 'New Hampshire could not be reached. This application requires manual license review.', $source);
        } catch (Throwable $exception) {
            $this->logNewHampshireException($exception, $endpointHost, $startedAt, $requestStage);

            return $this->result('unable_to_verify', 'New Hampshire returned an unexpected error. This application requires manual license review.', $source);
        }

        try {
            $parsedResults = $this->parseNewHampshireResults($resultsResponse->body());
        } catch (Throwable $exception) {
            $this->logNewHampshireException($exception, $endpointHost, $startedAt, 'results_html');

            return $this->result('unable_to_verify', 'New Hampshire returned an unreadable license results page. This application requires manual license review.', $source);
        }

        if (! $parsedResults['recognized']) {
            $this->logNewHampshireFailure('results_table_or_fields_missing', $endpointHost, $resultsResponse->status(), $startedAt, 'results_html');

            return $this->result('unable_to_verify', 'New Hampshire returned unexpected license search results. This application requires manual license review.', $source);
        }

        if ($parsedResults['records'] === []) {
            return $this->result('failed', 'No matching New Hampshire physician license record was found for the submitted license number.', $source);
        }

        $exactMatches = array_values(array_filter(
            $parsedResults['records'],
            static fn (array $record): bool => $record['license_number'] === $licenseNumber
        ));
        if ($exactMatches === []) {
            return $this->result('failed', 'No exact New Hampshire license number match was found for the submitted physician license number.', $source);
        }

        if (count($exactMatches) > 1) {
            return $this->result('manual_review', 'New Hampshire returned multiple exact matches for the submitted license number. Manual review is required.', $source, ['matches' => $exactMatches]);
        }

        $record = $exactMatches[0];
        if (strcasecmp($record['profession'], 'Medicine') !== 0
            || strcasecmp($record['license_type'], 'Physician') !== 0) {
            return $this->result('failed', 'This New Hampshire license does not qualify as a physician license.', $source, $record);
        }

        $status = strtolower(trim($record['status']));
        if ($status === 'active') {
            return $this->result(
                'verified',
                'The license matched the New Hampshire physician registry. Confirm current licensure with the issuing board for credentialing decisions.',
                $source,
                $record
            );
        }

        if (str_contains($status, 'expired')) {
            return $this->result('expired', 'The New Hampshire physician license is expired.', $source, $record);
        }

        if (in_array($status, ['inactive', 'suspended', 'revoked'], true)) {
            return $this->result('failed', 'The New Hampshire physician license is not active.', $source, $record);
        }

        return $this->result('unable_to_verify', 'New Hampshire returned an unrecognized license status. This application requires manual license review.', $source, $record);
    }

    private function parseNewHampshireSearchForm(string $html, string $endpoint): ?array
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
            return null;
        }

        $xpath = new \DOMXPath($document);
        $forms = $xpath->query('//form[.//input[@name="__VIEWSTATE"]]');
        if ($forms === false) {
            return null;
        }

        foreach ($forms as $form) {
            $fields = [];
            $inputs = $xpath->query('.//input[@name]', $form);
            if ($inputs === false) {
                continue;
            }

            foreach ($inputs as $input) {
                $name = $input->getAttribute('name');
                if ($name !== '') {
                    $fields[$name] = $input->getAttribute('value');
                }
            }

            $requiredHiddenFields = [
                '__EVENTTARGET',
                '__EVENTARGUMENT',
                '__LASTFOCUS',
                '__VIEWSTATE',
                '__VIEWSTATEGENERATOR',
                '__EVENTVALIDATION',
            ];
            foreach ($requiredHiddenFields as $name) {
                if (! array_key_exists($name, $fields)) {
                    continue 2;
                }
            }

            $requiredSearchFields = [
                't_web_lookup__profession_name',
                't_web_lookup__license_type_name',
                't_web_lookup__first_name',
                't_web_lookup__last_name',
                't_web_lookup__license_no',
                'sch_button',
            ];
            foreach ($requiredSearchFields as $name) {
                $control = $xpath->query('.//*[@name="'.$name.'"]', $form);
                if ($control === false || $control->length === 0) {
                    continue 2;
                }
            }

            $action = trim($form->getAttribute('action'));
            $actionUrl = $this->resolveNewHampshireUrl(
                $endpoint,
                $action === '' ? $endpoint : $action
            );

            if ($actionUrl !== null) {
                return ['action' => $actionUrl, 'fields' => $fields];
            }
        }

        return null;
    }

    private function parseNewHampshireResults(string $html): array
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
            return ['recognized' => false, 'records' => []];
        }

        $xpath = new \DOMXPath($document);
        $tables = $xpath->query('//table[@id="datagrid_results"]');
        if ($tables === false || $tables->length !== 1) {
            return ['recognized' => false, 'records' => []];
        }

        $table = $tables->item(0);
        $rows = $xpath->query('.//tr', $table);
        if ($rows === false) {
            return ['recognized' => false, 'records' => []];
        }

        $columns = [];
        foreach ($rows as $row) {
            $cells = $xpath->query('./th|./td', $row);
            if ($cells === false) {
                continue;
            }

            foreach ($cells as $index => $cell) {
                $label = strtolower(trim(preg_replace('/\s+/', ' ', $cell->textContent ?? '') ?? ''));
                $columns[$label] = $index;
            }

            if (isset(
                $columns['full name'],
                $columns['profession'],
                $columns['license type'],
                $columns['license number'],
                $columns['license status']
            )) {
                break;
            }
        }

        $requiredColumns = ['full name', 'profession', 'license type', 'license number', 'license status'];
        foreach ($requiredColumns as $column) {
            if (! isset($columns[$column])) {
                $pageText = strtoupper(trim(preg_replace('/\s+/', ' ', $document->textContent ?? '') ?? ''));
                if (preg_match('/\b(NO RECORD(?:S)? FOUND|NO RESULTS|NO LICENSES FOUND)\b/', $pageText) === 1) {
                    return ['recognized' => true, 'records' => []];
                }

                return ['recognized' => false, 'records' => []];
            }
        }

        $records = [];
        $lastRequiredColumn = max(
            $columns['full name'],
            $columns['profession'],
            $columns['license type'],
            $columns['license number'],
            $columns['license status']
        );
        foreach ($rows as $row) {
            $cells = $xpath->query('./td', $row);
            if ($cells === false || $cells->length <= $lastRequiredColumn) {
                continue;
            }

            $cellText = static fn (string $column): string => trim(preg_replace(
                '/\s+/',
                ' ',
                $cells->item($columns[$column])?->textContent ?? ''
            ) ?? '');
            $record = [
                'full_name' => $cellText('full name'),
                'profession' => $cellText('profession'),
                'license_type' => $cellText('license type'),
                'license_number' => $cellText('license number'),
                'status' => $cellText('license status'),
                'source_state' => 'NH',
            ];

            if (in_array('', $record, true)) {
                return ['recognized' => false, 'records' => []];
            }
            if ($record['license_number'] !== '') {
                $records[] = $record;
            }
        }

        return ['recognized' => true, 'records' => $records];
    }

    private function resolveNewHampshireUrl(string $baseUrl, string $location): ?string
    {
        if ($location === '') {
            return null;
        }

        $base = parse_url($baseUrl);
        $target = parse_url($location);
        if (! is_array($base)
            || ! is_array($target)
            || strtolower((string) ($base['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($base['host'] ?? '')) !== 'forms.nh.gov') {
            return null;
        }

        if (isset($target['host'])) {
            if (strtolower((string) ($target['scheme'] ?? '')) !== 'https'
                || strtolower((string) $target['host']) !== 'forms.nh.gov') {
                return null;
            }

            return $location;
        }

        $path = (string) ($target['path'] ?? '');
        if ($path === '') {
            return null;
        }
        if (! str_starts_with($path, '/')) {
            $basePath = (string) ($base['path'] ?? '/');
            $path = rtrim(substr($basePath, 0, (int) strrpos($basePath, '/') + 1), '/').'/'.$path;
        }

        return 'https://forms.nh.gov'.$path
            .(isset($target['query']) ? '?'.$target['query'] : '')
            .(isset($target['fragment']) ? '#'.$target['fragment'] : '');
    }

    private function newHampshireCookieJar(string $cookieHeader, string $host): CookieJar
    {
        $cookies = [];
        foreach (explode(';', trim($cookieHeader)) as $cookie) {
            $parts = explode('=', trim($cookie), 2);
            if (count($parts) !== 2) {
                continue;
            }

            [$name, $value] = $parts;
            $name = trim($name);
            $value = trim($value);

            if ($name !== '' && $value !== '' && ! preg_match('/[\r\n;]/', $name.$value)) {
                $cookies[$name] = $value;
            }
        }

        return CookieJar::fromArray($cookies, $host);
    }

    private function logNewHampshireFailure(string $reason, string $host, int $status, float $startedAt, string $stage): void
    {
        Log::warning('New Hampshire license verification response could not be used', [
            'reason' => $reason,
            'stage' => $stage,
            'host' => $host,
            'http_status' => $status,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }

    private function logNewHampshireCookieDiagnostics(string $stage, CookieJar $session, bool $configured): void
    {
        Log::info('New Hampshire license verification cookies sent', [
            'stage' => $stage,
            'cookie_configured' => $configured,
            'cookie_names' => array_values(array_filter(array_map(
                static fn (array $cookie): string => (string) ($cookie['Name'] ?? ''),
                $session->toArray()
            ))),
        ]);
    }

    private function logNewHampshireException(Throwable $exception, string $host, float $startedAt, string $stage): void
    {
        Log::warning('New Hampshire license verification request failed', [
            'stage' => $stage,
            'exception_class' => $exception::class,
            'host' => $host,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
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
