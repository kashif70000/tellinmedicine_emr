<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Throwable;

class GmcUkVerificationService
{
    public function __construct(private GmcUkRegistrationSource $source) {}

    public function verify(array $application): array
    {
        $sourceName = $this->source->sourceName();
        $name = trim((string) ($application['name'] ?? ''));
        $gmcNumber = trim((string) ($application['gmc_registration_number'] ?? $application['gmc_number'] ?? ''));

        if ($name === '') {
            return [
                'status' => 'manual_review',
                'message' => 'The doctor name is required before GMC verification can proceed.',
                'source' => $sourceName,
                'official_data' => null,
            ];
        }

        if ($gmcNumber === '') {
            return [
                'status' => 'failed',
                'message' => 'A GMC registration number is required for verification.',
                'source' => $sourceName,
                'official_data' => null,
            ];
        }

        if ($this->normalizeNumeric($gmcNumber) === '') {
            return [
                'status' => 'failed',
                'message' => 'Enter a valid GMC registration number.',
                'source' => $sourceName,
                'official_data' => null,
            ];
        }

        try {
            $records = $this->source->find($name, $gmcNumber !== '' ? $gmcNumber : null);
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
                'message' => 'No matching GMC registration record was found for the submitted doctor name.',
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
                'message' => 'The GMC number does not match any record returned for the submitted name. Check the name and GMC number, then try again.',
                'source' => $sourceName,
                'official_data' => null,
            ];
        }

        $best = $this->pickBestMatch($name, $gmcNumber, $numberMatches);

        if ($best === null) {
            return [
                'status' => 'manual_review',
                'message' => 'Multiple similar GMC records were returned and require administrative review.',
                'source' => $sourceName,
                'official_data' => $candidates,
            ];
        }

        $officialData = [
            'full_name' => (string) ($best['full_name'] ?? ''),
            'gmc_number' => (string) ($best['gmc_number'] ?? ''),
            'profile_url' => (string) ($best['profile_url'] ?? ''),
            'registration_status' => $best['registration_status'] ?? null,
            'licence_status' => $best['licence_status'] ?? null,
            'source_url' => $best['source_url'] ?? null,
            'raw_text' => $best['raw_text'] ?? null,
        ];

        if (! $this->isTrustedProfileUrl($officialData['profile_url'])) {
            return [
                'status' => 'manual_review',
                'message' => 'The GMC result is missing a trusted official profile link and requires manual review.',
                'source' => $sourceName,
                'official_data' => $officialData,
            ];
        }

        if ($this->normalizeName($officialData['full_name']) === '') {
            return [
                'status' => 'manual_review',
                'message' => 'The GMC result did not provide a usable doctor name.',
                'source' => $sourceName,
                'official_data' => $officialData,
            ];
        }

        if ($gmcNumber !== '' && $this->normalizeNumeric($officialData['gmc_number']) !== $this->normalizeNumeric($gmcNumber)) {
            return [
                'status' => 'failed',
                'message' => 'The GMC number does not match the record found for the submitted name. Check the name and GMC number, then try again.',
                'source' => $sourceName,
                'official_data' => $officialData,
            ];
        }

        if (! $this->namesMatch($name, (string) $officialData['full_name'])) {
            return [
                'status' => 'manual_review',
                'message' => 'The GMC record name differs from the submitted doctor name and requires manual review.',
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
            'message' => 'The GMC registration record was verified against the submitted doctor details.',
            'source' => $sourceName,
            'official_data' => $officialData,
        ];
    }

    private function pickBestMatch(string $name, string $gmcNumber, array $records): ?array
    {
        $normalizedName = $this->normalizeName($name);
        $normalizedNumber = $this->normalizeNumeric($gmcNumber);

        $score = [];
        foreach ($records as $record) {
            $candidateName = $this->normalizeName((string) ($record['full_name'] ?? ''));
            $candidateNumber = $this->normalizeNumeric((string) ($record['gmc_number'] ?? ''));
            $matchScore = 0;

            if ($normalizedNumber !== '' && $candidateNumber !== '') {
                $matchScore += $candidateNumber === $normalizedNumber ? 100 : 0;
            }

            if ($normalizedName !== '' && $candidateName !== '') {
                if ($candidateName === $normalizedName) {
                    $matchScore += 80;
                } elseif (str_contains($candidateName, $normalizedName) || str_contains($normalizedName, $candidateName)) {
                    $matchScore += 50;
                }
            }

            $score[] = [$matchScore, $record];
        }

        usort($score, fn (array $a, array $b) => $b[0] <=> $a[0]);
        $candidate = $score[0][1] ?? null;
        if (! $candidate) {
            return null;
        }

        $highest = $score[0][0] ?? 0;
        $second = $score[1][0] ?? 0;

        if ($highest > 0 && $second > 0 && $second >= $highest) {
            $sameNameMatches = array_filter($score, fn (array $entry) => $entry[0] >= $highest);
            if (count($sameNameMatches) > 1) {
                return null;
            }
        }

        if ($highest <= 0) {
            return null;
        }

        return $candidate;
    }

    private function normalizeName(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9]/', '', $value);

        return (string) $value;
    }

    private function namesMatch(string $submittedName, string $officialName): bool
    {
        $submittedParts = $this->nameParts($submittedName);
        $officialParts = $this->nameParts($officialName);

        return $submittedParts !== [] && array_diff($submittedParts, $officialParts) === [];
    }

    private function nameParts(string $value): array
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
        $parts = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique($parts ?: []));
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
