<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class USLicenseCheckVerificationService
{
    public function verify(array $application): array
    {
        $state = strtoupper(trim((string) ($application['license_state'] ?? '')));
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
                ->connectTimeout(max(1, (int) config('uslicensecheck.connect_timeout', 3)))
                ->timeout(max(1, (int) config('uslicensecheck.timeout', 10)))
                ->post($baseUrl . '/lookup/verify', [
                    'license_number' => $licenseNumber,
                    'source_state' => $state,
                ]);
        } catch (ConnectionException) {
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

        if (! $this->isMdLicense($payload['board'], $payload['license_type'])) {
            return $this->result('failed', 'This license does not qualify for MD physician verification.', $source, $payload);
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

    private function isMdLicense(string $board, string $licenseType): bool
    {
        $board = strtolower(trim($board));
        $licenseType = strtolower(trim($licenseType));
        $medicalBoard = str_contains($board, 'medical')
            || str_contains($board, 'medicine')
            || str_contains($board, 'physician');
        $mdType = preg_match('/^(?:md|doctor of medicine(?:\s*\(md\))?|medical doctor(?:\s*\(md\))?)$/i', trim($licenseType)) === 1;

        return $medicalBoard && $mdType;
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
