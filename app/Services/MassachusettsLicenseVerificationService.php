<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use App\Services\MassachusettsLicenseSource;
use Throwable;

class MassachusettsLicenseVerificationService
{
    public const SUPPORTED_TYPES = [
        'fullphysician',
        'limitedphysician',
        'temporaryphysician',
    ];

    public function __construct(private MassachusettsLicenseSource $source) {}

    public function verify(array $application): array
    {
        $sourceName = $this->source->sourceName();
        $result = [
            'status' => 'failed',
            'message' => 'The submitted license information could not be verified.',
            'source' => $sourceName,
            'official_data' => null,
        ];

        if (strtoupper((string) ($application['license_state'] ?? '')) !== 'MA') {
            $result['message'] = 'Massachusetts verification only supports MA licenses.';
            return $result;
        }

        $type = $this->licenseType((string) ($application['license_type'] ?? ''));
        if (! $type) {
            $result['message'] = 'The submitted physician license type is not supported.';
            return $result;
        }

        try {
            $record = $this->source->find((string) $application['license_type'], trim((string) $application['license_number']));
        } catch (Throwable) {
            $record = null;
        }

        if ($record === null) {
            return [
                'status' => 'unable_to_verify',
                'message' => 'Unable to Verify: the configured Massachusetts licensing source did not return a usable official record. Check API access and use the Board manual verification process if this license type is not available through the API.',
                'source' => $sourceName,
                'official_data' => null,
            ];
        }

        if ($record === []) {
            return [
                'status' => 'failed',
                'message' => 'No exact matching Massachusetts license number was found in the official response.',
                'source' => $sourceName,
                'official_data' => null,
            ];
        }

        $result['official_data'] = $record;

        foreach (['license_number', 'license_type', 'status', 'issue_date', 'expiration_date'] as $requiredField) {
            if (! is_string($record[$requiredField] ?? null) || trim($record[$requiredField]) === '') {
                $result['status'] = 'unable_to_verify';
                $result['message'] = 'Unable to Verify: the official response is missing a required license field.';
                return $result;
            }
        }

        if ($this->normalize((string) ($record['license_number'] ?? '')) !== $this->normalize((string) $application['license_number'])) {
            $result['message'] = 'The official license number does not match the submitted number.';
            return $result;
        }

        if ($this->licenseType((string) ($record['license_type'] ?? '')) !== $type) {
            $result['message'] = 'The official license type does not match the submitted type.';
            return $result;
        }

        if (strtolower(trim($record['status'])) !== 'active') {
            $result['status'] = 'failed';
            $result['message'] = 'The official license status is not acceptable.';
            return $result;
        }

        $officialExpiration = $this->date($record['expiration_date'] ?? null);
        if (! $officialExpiration) {
            $result['status'] = 'unable_to_verify';
            $result['message'] = 'The official source did not provide an expiration date, so current licensure cannot be confirmed.';
            return $result;
        }

        if ($officialExpiration->isBefore(CarbonImmutable::today())) {
            $result['status'] = 'expired';
            $result['message'] = 'The official license record is expired.';
            return $result;
        }

        if ($this->date($application['license_expiration_date'] ?? null)?->toDateString() !== $officialExpiration->toDateString()) {
            $result['message'] = 'The submitted expiration date does not match the official record.';
            return $result;
        }

        $officialIssue = $this->date($record['issue_date'] ?? null);
        if (! $officialIssue) {
            $result['status'] = 'unable_to_verify';
            $result['message'] = 'Unable to Verify: the official source did not provide a usable issue date.';
            return $result;
        }

        if ($this->date($application['license_issue_date'] ?? null)?->toDateString() !== $officialIssue->toDateString()) {
            $result['message'] = 'The submitted issue date does not match the official record.';
            return $result;
        }

        return [
            'status' => 'verified',
            'message' => 'The Massachusetts physician license was verified against the official record.',
            'source' => $sourceName,
            'official_data' => $record,
        ];
    }

    private function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/^(dr\.?\s+|doctor\s+)/', '', $value);

        return preg_replace('/[^a-z0-9]/', '', $value) ?? '';
    }

    private function licenseType(string $value): ?string
    {
        $category = str_replace(['physician', 'license'], '', $this->normalize($value));

        return match ($category) {
            'full' => 'fullphysician',
            'limited' => 'limitedphysician',
            'temporary' => 'temporaryphysician',
            default => null,
        };
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})?)?$/', $value, $matches)) {
            return null;
        }

        if (! checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));
    }
}