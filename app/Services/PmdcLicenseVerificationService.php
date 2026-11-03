<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PmdcLicenseVerificationService
{
    public function verify(string $registrationNumber): array
    {
        $registrationNumber = trim($registrationNumber);
        $source = 'Pakistan Medical & Dental Council (PMDC)';

        if ($registrationNumber === '') {
            return $this->result('failed', 'A PMDC registration number is required.', $source);
        }

        $endpoint = trim((string) config('pmdc.license_verification_url'));
        if ($endpoint === '') {
            return $this->result('unavailable', 'PMDC license verification is not configured.', $source);
        }

        try {
            $response = Http::acceptJson()
                ->asForm()
                ->contentType('application/x-www-form-urlencoded; charset=UTF-8')
                ->withHeaders([
                    'Origin' => 'https://pmdc.pk',
                    'Referer' => 'https://pmdc.pk/',
                ])
                ->timeout(max(1, (int) config('pmdc.timeout', 15)))
                ->post($endpoint, [
                    'RegistrationNo' => $registrationNumber,
                    'Name' => '',
                    'FatherName' => '',
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('PMDC license verification connection failed', [
                'exception' => $exception::class,
                'reason' => $exception->getMessage(),
            ]);

            return $this->result('unable_to_verify', 'The PMDC verification service could not be reached.', $source);
        } catch (\InvalidArgumentException $exception) {
            Log::warning('PMDC license verification request failed', [
                'exception' => $exception::class,
                'reason' => $exception->getMessage(),
            ]);

            return $this->result('unable_to_verify', 'The PMDC verification service returned an unexpected error.', $source);
        }

        $payload = $response->json();
        if (! $response->successful()) {
            $this->logApiFailure($response, is_array($payload) ? $payload : null);

            return $this->result('unavailable', 'The PMDC verification service is temporarily unavailable.', $source);
        }

        if (! is_array($payload) || ! is_bool($payload['status'] ?? null)) {
            $this->logApiFailure($response, is_array($payload) ? $payload : null);

            return $this->result('unable_to_verify', 'The PMDC verification service returned an invalid response.', $source);
        }

        if ($payload['status'] === false) {
            $message = is_string($payload['message'] ?? null) ? $payload['message'] : '';
            if (preg_match('/(?:no\s+records?|0\s+records?)\s+found|registration\s+(?:number\s+)?not\s+found/i', $message)) {
                return $this->result('not_found', 'No PMDC record was found for this registration number.', $source);
            }

            $this->logApiFailure($response, $payload);
            $failureMessage = strcasecmp(trim($message), 'Invalid Parameters') === 0
                ? 'PMDC rejected the registration-number-only lookup parameters. This does not confirm the registration is invalid.'
                : 'PMDC could not complete the lookup. This does not confirm the registration is invalid; please retry later.';

            return $this->result(
                'unavailable',
                $failureMessage,
                $source
            );
        }

        if (! is_array($payload['data'] ?? null)) {
            $this->logApiFailure($response, $payload);

            return $this->result('unable_to_verify', 'The PMDC verification response did not contain valid registration data.', $source);
        }

        $matchingRecords = array_values(array_filter(
            $payload['data'],
            fn ($record): bool => is_array($record)
                && is_string($record['RegistrationNo'] ?? null)
                && strcasecmp(trim($record['RegistrationNo']), $registrationNumber) === 0
        ));

        if ($matchingRecords === []) {
            return $this->result('not_found', 'No matching PMDC registration number was found.', $source);
        }

        if (count($matchingRecords) !== 1) {
            return $this->result('manual_review', 'PMDC returned multiple matching records. Manual review is required.', $source);
        }

        $record = $matchingRecords[0];
        if (! is_string($record['Status'] ?? null) || trim($record['Status']) === '') {
            return $this->result('unable_to_verify', 'PMDC did not return a registration status.', $source);
        }

        $officialData = $this->officialData($record);
        if (strtoupper(trim($record['Status'])) !== 'ACTIVE') {
            return $this->result('inactive', 'The PMDC registration is not active.', $source, $officialData);
        }

        return $this->result('verified', 'The PMDC registration is active and verified.', $source, $officialData);
    }

    private function logApiFailure(Response $response, ?array $payload): void
    {
        $sanitizedBody = [];
        if (is_bool($payload['status'] ?? null)) {
            $sanitizedBody['status'] = $payload['status'];
        }
        if (is_string($payload['message'] ?? null)) {
            $sanitizedBody['message'] = mb_substr($payload['message'], 0, 300);
        }
        if (is_array($payload['error'] ?? null) && is_string($payload['error']['ClassName'] ?? null)) {
            $sanitizedBody['error_class'] = mb_substr($payload['error']['ClassName'], 0, 120);
        }
        if ($sanitizedBody === []) {
            $sanitizedBody['response'] = 'Non-JSON or unrecognized response omitted.';
        }

        Log::warning('PMDC license verification API request failed', [
            'http_status' => $response->status(),
            'sanitized_response_body' => $sanitizedBody,
        ]);
    }

    private function officialData(array $record): array
    {
        $registrationDate = $record['RegistrationDate'] ?? null;
        $validUpto = $record['ValidUpto'] ?? null;

        return [
            'registration_number' => $record['RegistrationNo'] ?? null,
            'name' => $record['Name'] ?? null,
            'registration_type' => $record['RegistrationType'] ?? null,
            'registration_date' => $registrationDate,
            'valid_upto' => $validUpto,
            'status' => $record['Status'] ?? null,
            'issue_date' => $this->databaseDate($registrationDate),
            'expiration_date' => $this->databaseDate($validUpto),
        ];
    }

    private function databaseDate(mixed $date): ?string
    {
        if (! is_string($date) || trim($date) === '') {
            return null;
        }

        try {
            $parsedDate = CarbonImmutable::createFromFormat('!d/m/Y', trim($date));

            return $parsedDate->format('d/m/Y') === trim($date)
                ? $parsedDate->toDateString()
                : null;
        } catch (\InvalidArgumentException) {
            return null;
        }
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
