<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class MassachusettsLicensingApiSource implements MassachusettsLicenseSource
{
    public function find(string $licenseType, string $licenseNumber): ?array
    {
        $endpoint = (string) config('services.massachusetts_license.endpoint_url');
        if ($endpoint === '') {
            return null;
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(3)
                ->timeout(8)
                ->post($endpoint, [
                    'licenseMetaId' => null,
                    'licenseNumber' => trim($licenseNumber),
                    'searchType' => 'BY_LICENSE_NUMBER',
                    'showOnlyAuthorizedForHerbalTherapy' => false,
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();
        $records = data_get($payload, 'results.data');
        if (! is_array($records) || ! array_is_list($records)) {
            return null;
        }

        foreach ($records as $record) {
            if (! is_array($record)) {
                return null;
            }

            $returnedNumber = $record['licenseNumber'] ?? null;
            if ((string) $returnedNumber !== trim($licenseNumber)) {
                continue;
            }

            return [
                'license_number' => is_scalar($returnedNumber) ? trim((string) $returnedNumber) : null,
                'license_meta_id' => $record['licenseMetaId'] ?? null,
                'full_name' => $this->stringValue($record['fullName'] ?? null),
                'license_type' => $this->stringValue($record['licenseMetaName'] ?? null),
                'status' => $this->stringValue($record['profileStatus'] ?? null),
                'expiration_date' => $this->stringValue($record['expirationDate'] ?? null),
                'issue_date' => $this->stringValue($record['startDate'] ?? null),
                'original_issue_date' => $this->stringValue($record['originalDate'] ?? null),
                'start_date' => $this->stringValue($record['startDate'] ?? null),
                'degree' => $this->stringValue($record['degree'] ?? null),
                'specialties' => is_array($record['specialties'] ?? null) ? $record['specialties'] : [],
                'api_record' => $record,
            ];
        }

        return [];
    }

    public function sourceName(): string
    {
        return 'Massachusetts Physician License Search API';
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : null;
    }
}