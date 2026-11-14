<?php

namespace App\Services;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class DoctorLicenseLookupService
{
    public function getStates(): array
    {
        return config('doctor_lookup.states', []);
    }

    public function getStateByCode(string $stateCode): ?array
    {
        $state = strtoupper(trim($stateCode));

        return config('doctor_lookup.states.'.$state, null);
    }

    public function normalizeStateSlug(string $value): string
    {
        $normalized = strtolower(trim((string) $value));
        $normalized = preg_replace('/[&]/u', ' and ', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^a-z0-9\s-]+/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', '-', trim($normalized)) ?? trim($normalized);
        $normalized = preg_replace('/-+/', '-', trim($normalized)) ?? trim($normalized);

        return trim($normalized, '-');
    }

    public function getStatePageUrl(string $stateSlug): string
    {
        return rtrim((string) config('doctor_lookup.base_url'), '/').'/state/'.$this->normalizeStateSlug($stateSlug);
    }

    public function getSearchUrl(string $stateSlug): string
    {
        return rtrim((string) config('doctor_lookup.base_url'), '/').'/'.$this->normalizeStateSlug($stateSlug).'/search';
    }

    public function verify(string $country, string $stateCode, string $licenseNumber): array
    {
        if (! filter_var((string) config('doctor_lookup.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return $this->result('unable_to_verify', 'DoctorLicenseLookup is disabled. Manual review is required.');
        }

        $country = strtoupper(trim((string) $country));
        if ($country !== 'US') {
            return $this->result('failed', 'DoctorLicenseLookup only supports the United States at this time.');
        }

        $state = strtoupper(trim((string) $stateCode));
        $stateData = $this->getStateByCode($state);
        if ($stateData === null) {
            return $this->result('failed', 'The selected state is not supported by DoctorLicenseLookup.');
        }

        $licenseNumber = trim((string) $licenseNumber);
        if ($licenseNumber === '') {
            return $this->result('failed', 'A physician license number is required.');
        }

        $stateSlug = (string) ($stateData['slug'] ?? $this->normalizeStateSlug($stateData['name'] ?? $state));
        $statePageUrl = $this->getStatePageUrl($stateSlug);
        $searchUrl = $this->getSearchUrl($stateSlug);

        try {
            $cookieJar = new CookieJar();
            $statePageResponse = Http::withOptions([
                'allow_redirects' => true,
                'verify' => (bool) config('doctor_lookup.verify_ssl', true),
                'connect_timeout' => max(1, (int) config('doctor_lookup.connect_timeout', 30)),
                'timeout' => max(1, (int) config('doctor_lookup.timeout', 30)),
                'cookies' => $cookieJar,
            ])->withHeaders($this->buildHeaders($statePageUrl, null))->get($statePageUrl);
        } catch (\Throwable $exception) {
            return $this->result('unable_to_verify', 'DoctorLicenseLookup could not be reached. Manual review is required.', ['exception' => $exception->getMessage()]);
        }

        if ($statePageResponse->failed() && $statePageResponse->status() >= 400) {
            return $this->result('unable_to_verify', 'DoctorLicenseLookup state page request failed. Manual review is required.', ['status_code' => $statePageResponse->status()]);
        }

        $csrfToken = $this->extractCsrfToken($statePageResponse) ?: (string) config('doctor_lookup.fallback_token', '');
        if ($csrfToken === '') {
            return $this->result('manual_review', 'DoctorLicenseLookup did not return a valid CSRF token. Manual review is required.');
        }

        $postHeaders = $this->buildHeaders($statePageUrl, $csrfToken);
        $payload = [
            '_token' => $csrfToken,
            'license_no' => $licenseNumber,
            'state_select' => $stateSlug,
            'page' => (int) config('doctor_lookup.default_page', 1),
            'per_page' => (int) config('doctor_lookup.per_page', 10),
        ];

        try {
            $searchResponse = Http::withOptions([
                'allow_redirects' => true,
                'verify' => (bool) config('doctor_lookup.verify_ssl', true),
                'connect_timeout' => max(1, (int) config('doctor_lookup.connect_timeout', 30)),
                'timeout' => max(1, (int) config('doctor_lookup.timeout', 30)),
                'cookies' => $cookieJar,
            ])->withHeaders($postHeaders)->asForm()->post($searchUrl, $payload);
        } catch (\Throwable $exception) {
            return $this->result('unable_to_verify', 'DoctorLicenseLookup search request failed. Manual review is required.', ['exception' => $exception->getMessage()]);
        }

        $body = $searchResponse->json();
        if (! is_array($body)) {
            return $this->result('manual_review', 'DoctorLicenseLookup returned an unexpected response format. Manual review is required.', ['raw_response' => $searchResponse->body()]);
        }

        $status = strtolower((string) ($body['status'] ?? $body['verification_status'] ?? ''));
        $success = filter_var($body['success'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $officialData = $this->extractOfficialData($body);

        if ($this->hasEmptyResultsCollection($body)
            || $this->hasExplicitNoResults($body)
            || in_array($status, ['not_found', 'no_results', 'empty'], true)) {
            return $this->result(
                'not_found',
                'License record not found. Please check the license number and selected state.',
                []
            );
        }

        if ($searchResponse->failed() && $searchResponse->status() >= 400) {
            return $this->result('failed', 'DoctorLicenseLookup did not accept the search request.', $officialData + ['status_code' => $searchResponse->status()]);
        }

        if ($success || in_array($status, ['verified', 'success', 'active'], true)) {
            $payloadLicense = $this->extractLicenseNumber($officialData, $body, $licenseNumber);
            if ($payloadLicense !== null && $this->normalizeLicenseNumber($payloadLicense) !== $this->normalizeLicenseNumber($licenseNumber)) {
                return $this->result('failed', 'The returned physician license does not match the submitted license number.', $officialData);
            }

            return $this->result('verified', 'The physician license was found on DoctorLicenseLookup.', $officialData);
        }

        if (isset($body['message']) && is_string($body['message']) && trim($body['message']) !== '') {
            $message = trim($body['message']);
            if (str_contains(strtolower($message), 'manual') || str_contains(strtolower($message), 'review')) {
                return $this->result('manual_review', $message, $officialData);
            }

            return $this->result('failed', $message, $officialData);
        }

        if ($searchResponse->successful() && $officialData === []) {
            return $this->result('failed', 'No matching physician license record was found for the selected state and license number.', $officialData);
        }

        return $this->result('manual_review', 'DoctorLicenseLookup returned an incomplete result. Manual review is required.', $officialData);
    }

    private function result(string $status, string $message, array $officialData = []): array
    {
        $successMap = [
            'verified' => true,
            'failed' => false,
            'manual_review' => false,
            'unable_to_verify' => false,
        ];

        return [
            'status' => $status,
            'success' => $successMap[$status] ?? false,
            'message' => $message,
            'source' => 'DoctorLicenseLookup',
            'official_data' => $officialData,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function buildHeaders(string $statePageUrl, ?string $csrfToken): array
    {
        $baseUrl = rtrim((string) config('doctor_lookup.base_url'), '/');
        $cookieValues = array_filter([
            config('doctor_lookup.cookie', ''),
            config('doctor_lookup.laravel_session') !== '' ? 'laravel_session='.trim((string) config('doctor_lookup.laravel_session')) : null,
            config('doctor_lookup.cf_clearance') !== '' ? 'cf_clearance='.trim((string) config('doctor_lookup.cf_clearance')) : null,
            config('doctor_lookup.fallback_token') !== '' ? 'XSRF-TOKEN='.trim((string) config('doctor_lookup.fallback_token')) : null,
        ], static fn ($value) => is_string($value) && trim($value) !== '');

        $headers = [
            'Accept' => (string) config('doctor_lookup.accept', 'application/json, text/plain, */*'),
            'Accept-Language' => (string) config('doctor_lookup.accept_language', 'en-US,en;q=0.9'),
            'Content-Type' => (string) config('doctor_lookup.content_type', 'application/x-www-form-urlencoded; charset=UTF-8'),
            'Origin' => (string) config('doctor_lookup.origin', $baseUrl),
            'Referer' => (string) config('doctor_lookup.referer', $statePageUrl),
            'User-Agent' => (string) config('doctor_lookup.user_agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
            'X-Requested-With' => (string) config('doctor_lookup.x_requested_with', 'XMLHttpRequest'),
        ];

        if ($csrfToken !== null && $csrfToken !== '') {
            $headers['X-CSRF-TOKEN'] = $csrfToken;
        } elseif (trim((string) config('doctor_lookup.xsrf_token', '')) !== '') {
            $headers['X-CSRF-TOKEN'] = trim((string) config('doctor_lookup.xsrf_token'));
        }

        if ($cookieValues !== []) {
            $headers['Cookie'] = implode('; ', $cookieValues);
        }

        return $headers;
    }

    private function extractCsrfToken(Response $response): ?string
    {
        $body = (string) $response->body();
        preg_match('/<meta[^>]+name=["\']csrf-token["\'][^>]+content=["\']([^"\']+)["\']/i', $body, $matches);

        if (! isset($matches[1])) {
            return null;
        }

        $token = trim((string) $matches[1]);

        return $token === '' ? null : $token;
    }

    private function extractLicenseNumber(array $officialData, array $payload, string $defaultLicenseNumber): ?string
    {
        foreach (['license_number', 'licenseNo', 'license_no', 'licenseNumber', 'doctor_license_number', 'number'] as $key) {
            if (isset($officialData[$key]) && is_scalar($officialData[$key])) {
                return (string) $officialData[$key];
            }
        }

        foreach (['data', 'record', 'records', 'result', 'results', 'items', 'licenses', 'license'] as $key) {
            if (! isset($payload[$key])) {
                continue;
            }

            $candidate = $payload[$key];
            if (is_array($candidate)) {
                foreach ($candidate as $entry) {
                    if (is_array($entry)) {
                        foreach (['license_number', 'licenseNo', 'license_no', 'licenseNumber', 'doctor_license_number', 'number'] as $nestedKey) {
                            if (isset($entry[$nestedKey]) && is_scalar($entry[$nestedKey])) {
                                return (string) $entry[$nestedKey];
                            }
                        }
                    }
                }
            }
        }

        return $defaultLicenseNumber;
    }

    private function extractOfficialData(array $payload): array
    {
        foreach (['data', 'record', 'records', 'result', 'results', 'items', 'licenses', 'license'] as $key) {
            if (isset($payload[$key])) {
                $value = $payload[$key];
                if (is_array($value)) {
                    if ($value !== []) {
                        return $value;
                    }
                }
            }
        }

        return $payload;
    }

    private function hasEmptyResultsCollection(array $payload): bool
    {
        $collectionKeys = ['data', 'records', 'results', 'items', 'licenses'];
        $foundCollection = false;

        foreach ($collectionKeys as $key) {
            if (! array_key_exists($key, $payload) || ! is_array($payload[$key])) {
                continue;
            }

            $foundCollection = true;
            if ($payload[$key] !== []) {
                return false;
            }
        }

        return $foundCollection;
    }

    private function hasExplicitNoResults(array $payload): bool
    {
        foreach (['no_results', 'noResults'] as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            if (filter_var($payload[$key], FILTER_VALIDATE_BOOLEAN)) {
                return true;
            }
        }

        foreach (['error_message', 'errorMessage', 'message'] as $key) {
            $message = $payload[$key] ?? null;
            if (is_string($message) && preg_match('/no results found|no matching (physician )?license|record not found/i', $message)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeLicenseNumber(string $value): string
    {
        $normalized = strtoupper(trim((string) $value));

        return preg_replace('/[^A-Z0-9]+/', '', $normalized) ?? $normalized;
    }
}
