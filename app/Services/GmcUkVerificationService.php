<?php

namespace App\Services;

use Throwable;

class GmcUkVerificationService
{
    public function __construct(private GmcUkRegistrationSource $source) {}

    public function verify(array $application): array
    {
        $sourceName = $this->source->sourceName();
        $gmcNumber = trim((string) ($application['gmc_registration_number'] ?? $application['gmc_number'] ?? ''));

        if ($gmcNumber === '') {
            return [
                'status' => 'failed',
                'message' => 'A GMC registration number is required for verification.',
                'source' => $sourceName,
                'official_data' => null,
            ];
        }

        if (preg_match('/^\d{7}$/', $gmcNumber) !== 1) {
            return [
                'status' => 'failed',
                'message' => 'Enter a valid seven-digit GMC registration number.',
                'source' => $sourceName,
                'official_data' => null,
            ];
        }

        try {
            $records = $this->source->find($gmcNumber);
        } catch (Throwable) {
            return [
                'status' => 'unavailable',
                'message' => 'GMC registration verification is currently unavailable. Please retry later or ask for manual review.',
                'source' => $sourceName,
                'official_data' => null,
            ];
        }

        if ($records === null) {
            return [
                'status' => 'unavailable',
                'message' => 'GMC registration verification could not be reached or timed out. Please retry later or request manual review.',
                'source' => $sourceName,
                'official_data' => null,
            ];
        }

        if ($records === [] || empty($records)) {
            return [
                'status' => 'failed',
                'message' => 'No GMC registration record was found for the submitted number.',
                'source' => $sourceName,
                'official_data' => null,
            ];
        }

        $candidates = array_values(array_filter($records, fn (array $record) => is_array($record)));
        $normalizedGmcNumber = $this->normalizeNumeric($gmcNumber);
        $numberMatches = array_values(array_filter($candidates, fn (array $record) => $this->normalizeNumeric((string) ($record['gmc_number'] ?? '')) === $normalizedGmcNumber));

        if ($numberMatches === []) {
            return [
                'status' => 'failed',
                'message' => 'The GMC number does not match any record returned by the register.',
                'source' => $sourceName,
                'official_data' => null,
            ];
        }

        if (count($numberMatches) !== 1) {
            return [
                'status' => 'manual_review',
                'message' => 'Multiple GMC records were returned for this number and require administrative review.',
                'source' => $sourceName,
                'official_data' => null,
            ];
        }

        $best = $numberMatches[0];
        $officialData = [
            'full_name' => (string) ($best['full_name'] ?? ''),
            'gmc_number' => (string) ($best['gmc_number'] ?? ''),
            'profile_url' => (string) ($best['profile_url'] ?? ''),
            'registration_status' => $best['registration_status'] ?? null,
            'licence_status' => $best['licence_status'] ?? null,
            'source_url' => $best['source_url'] ?? null,
        ];

        if (! $this->isTrustedProfileUrl($officialData['profile_url'])) {
            return [
                'status' => 'manual_review',
                'message' => 'The GMC result is missing a trusted official profile link and requires manual review.',
                'source' => $sourceName,
                'official_data' => $officialData,
            ];
        }

        if ($this->normalizeNumeric($officialData['gmc_number']) !== $this->normalizeNumeric($gmcNumber)) {
            return [
                'status' => 'failed',
                'message' => 'The GMC number does not match the record returned by the register.',
                'source' => $sourceName,
                'official_data' => $officialData,
            ];
        }

        $gmcNumberValue = $this->normalizeNumeric((string) $officialData['gmc_number']);
        if ($gmcNumberValue === '') {
            return [
                'status' => 'manual_review',
                'message' => 'The GMC record is missing a registration number and cannot be confirmed as eligible.',
                'source' => $sourceName,
                'official_data' => $officialData,
            ];
        }

        $registrationStatus = $this->normalizeStatus((string) ($officialData['registration_status'] ?? ''));
        $licenceStatus = $this->normalizeStatus((string) ($officialData['licence_status'] ?? ''));

        if ($registrationStatus !== '' && $this->isDefinitiveFailureStatus($registrationStatus)) {
            return [
                'status' => 'failed',
                'message' => 'The GMC registration status does not meet the required eligibility criteria.',
                'source' => $sourceName,
                'official_data' => $officialData,
            ];
        }

        if ($licenceStatus !== '' && $this->isDefinitiveFailureStatus($licenceStatus)) {
            return [
                'status' => 'failed',
                'message' => 'The GMC licence to practise status does not meet the required eligibility criteria.',
                'source' => $sourceName,
                'official_data' => $officialData,
            ];
        }

        if ($registrationStatus === '' && $licenceStatus === '') {
            return [
                'status' => 'manual_review',
                'message' => 'The GMC response did not include enough status information to confirm meeting the registration requirements.',
                'source' => $sourceName,
                'official_data' => $officialData,
            ];
        }

        if ($licenceStatus !== '' && ! $this->isPositiveLicenceStatus($licenceStatus)) {
            return [
                'status' => 'manual_review',
                'message' => 'The GMC record requires manual review because the licence-to-practise status is inconclusive.',
                'source' => $sourceName,
                'official_data' => $officialData,
            ];
        }

        if (! $this->isPositiveRegistrationStatus($registrationStatus)) {
            return [
                'status' => 'manual_review',
                'message' => 'The GMC registration status requires manual review because it is not clearly eligible.',
                'source' => $sourceName,
                'official_data' => $officialData,
            ];
        }

        return [
            'status' => 'verified',
            'message' => 'The GMC registration record was verified using the submitted GMC number.',
            'source' => $sourceName,
            'official_data' => $officialData,
        ];
    }

    private function normalizeNumeric(string $value): string
    {
        return preg_replace('/\D+/', '', trim($value)) ?? '';
    }

    private function normalizeStatus(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value);
        $value = preg_replace('/[^a-z0-9 ]+/', ' ', $value);

        return trim($value);
    }

    private function isTrustedProfileUrl(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        return preg_match('/^https?:\/\/([a-z0-9.-]+\.)?gmc-uk\.org\//i', $value) === 1;
    }

    private function isPositiveRegistrationStatus(string $status): bool
    {
        if ($status === '') {
            return false;
        }

        foreach (['registered', 'full registration', 'full registered', 'licence to practise', 'licensed'] as $positive) {
            if (str_contains($status, $positive)) {
                return true;
            }
        }

        return false;
    }

    private function isPositiveLicenceStatus(string $status): bool
    {
        if ($status === '') {
            return true;
        }

        foreach (['active', 'licensed', 'licence granted', 'registered', 'full', 'available'] as $positive) {
            if (str_contains($status, $positive)) {
                return true;
            }
        }

        return false;
    }

    private function isDefinitiveFailureStatus(string $status): bool
    {
        foreach (['removed', 'suspended', 'not registered', 'inactive', 'expired', 'revoked', 'denied', 'rejected'] as $negative) {
            if (str_contains($status, $negative)) {
                return true;
            }
        }

        return false;
    }
}
