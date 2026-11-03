<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IndiaNmcLicenseVerificationService
{
    public function verify(string $registrationNumber): array
    {
        $registrationNumber = trim((string) $registrationNumber);
        $source = 'National Medical Commission (NMC) Indian Medical Register';

        if ($registrationNumber === '') {
            return $this->result('failed', 'An India NMC registration number is required.', $source);
        }

        $endpoint = trim((string) config('india_nmc.license_verification_url'));
        if ($endpoint === '') {
            return $this->result('unavailable', 'India NMC license verification is not configured.', $source);
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Referer' => 'https://nmc.org.in/information-desk/indian-medical-register',
                ])
                ->timeout(max(1, (int) config('india_nmc.timeout', 15)))
                ->get($endpoint, [
                    'search_type' => 'reg_no',
                    'reg_no' => $registrationNumber,
                    'page' => 1,
                    'per_page' => 25,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('India NMC license verification connection failed', [
                'exception' => $exception::class,
                'reason' => $exception->getMessage(),
            ]);

            return $this->result('unable_to_verify', 'India NMC verification could not be reached.', $source);
        } catch (\InvalidArgumentException $exception) {
            Log::warning('India NMC license verification request failed', [
                'exception' => $exception::class,
                'reason' => $exception->getMessage(),
            ]);

            return $this->result('unable_to_verify', 'India NMC verification returned an unexpected error.', $source);
        }

        $payload = $response->json();
        if (! $response->successful()) {
            $this->logApiFailure($response, is_array($payload) ? $payload : null);

            return $this->result('unavailable', 'The India NMC verification service is temporarily unavailable.', $source);
        }

        if (! is_array($payload) || ! array_key_exists('success', $payload) || ! is_bool($payload['success']) || ! $payload['success']) {
            $this->logApiFailure($response, is_array($payload) ? $payload : null);

            return $this->result('unable_to_verify', 'India NMC verification returned an unexpected response.', $source);
        }

        $records = $payload['data'] ?? [];
        if (! is_array($records)) {
            return $this->result('not_found', 'No India NMC record was found for this registration number.', $source);
        }

        if ($records === []) {
            return $this->result('not_found', 'No India NMC record was found for this registration number.', $source);
        }

        $matchingRecords = array_values(array_filter(
            $records,
            fn ($record): bool => is_array($record)
                && is_string($record['registration_no'] ?? null)
                && strcasecmp(trim($record['registration_no']), $registrationNumber) === 0
        ));

        if ($matchingRecords === []) {
            return $this->result('not_found', 'No matching India NMC registration number was found.', $source);
        }

        if (count($matchingRecords) > 1) {
            return $this->result('manual_review', 'India NMC returned multiple matching records. Manual review is required.', $source, $matchingRecords);
        }

        $record = $matchingRecords[0];
        $officialData = $this->officialData($record);

        $removedStatus = $this->normalizeStatus($record['removed_status'] ?? null);
        if ($removedStatus !== '' && $this->isRemovedStatus($removedStatus)) {
            return $this->result(
                'removed',
                'The India NMC registration has been marked as removed and cannot be accepted as a verified active registration.',
                $source,
                $officialData
            );
        }

        $verificationStatus = 'verified';
        $message = 'The India NMC record was found and matches the submitted registration number.';

        if ($removedStatus !== '') {
            $verificationStatus = 'manual_review';
            $message = 'The India NMC record matches, but the removal status is not clearly active and requires manual review.';
        }

        return $this->result($verificationStatus, $message, $source, $officialData);
    }

    private function officialData(array $record): array
    {
        return [
            'registration_number' => $record['registration_no'] ?? null,
            'name' => $record['name'] ?? null,
            'father_name' => $record['father_name'] ?? null,
            'date_of_birth' => $this->parseDateOfBirth($record['dob'] ?? null),
            'registration_date' => $this->parseRegistrationDate($record['registration_date'] ?? null),
            'state_medical_council' => $record['state_medical_council'] ?? null,
            'state_code' => $record['state_code'] ?? null,
            'year_of_info' => $record['year_of_info'] ?? null,
            'permanent_address' => $record['permanent_address'] ?? null,
            'qualification' => $record['qualification'] ?? null,
            'qualification_year' => $record['qualification_year'] ?? null,
            'university' => $record['university'] ?? null,
            'additional_qualifications' => $record['additional_qualifications'] ?? null,
            'removed_status' => $record['removed_status'] ?? null,
            'official_record_id' => $record['id'] ?? null,
            'uprn_no' => $record['uprn_no'] ?? null,
        ];
    }

    private function parseRegistrationDate(mixed $date): ?string
    {
        if (! is_string($date) || trim($date) === '') {
            return null;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('!d-m-Y', trim($date));

            return $parsed && $parsed->format('d-m-Y') === trim($date)
                ? $parsed->toDateString()
                : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDateOfBirth(mixed $date): ?string
    {
        if (! is_string($date) || trim($date) === '' || preg_match('/^0{4}[-\/]0{1,2}[-\/]0{1,2}/', trim($date))) {
            return null;
        }

        try {
            return CarbonImmutable::parse(trim($date))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeStatus(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return strtolower(trim($value));
    }

    private function isRemovedStatus(string $status): bool
    {
        $normalized = strtolower(trim($status));

        return $normalized !== '' && str_contains($normalized, 'removed');
    }

    private function logApiFailure(\Illuminate\Http\Client\Response $response, ?array $payload): void
    {
        Log::warning('India NMC license verification API request failed', [
            'http_status' => $response->status(),
            'payload' => is_array($payload) ? array_slice($payload, 0, 10, true) : null,
        ]);
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
