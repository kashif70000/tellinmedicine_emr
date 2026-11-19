<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SouthSudanGmcLicenseVerificationService
{
    private const SOURCE = 'South Sudan General Medical Council (SSGMC)';

    public function verify(string $licenseNumber): array
    {
        $licenseNumber = trim($licenseNumber);
        if ($licenseNumber === '') {
            return $this->result('failed', 'A South Sudan physician licence number is required.');
        }

        $searchUrl = trim((string) config('south_sudan_gmc.search_url'));
        if ($searchUrl === '') {
            return $this->result('unable_to_verify', 'The SSGMC registry is not configured. Manual review is required.');
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Referer' => (string) config('south_sudan_gmc.registry_url'),
                ])
                ->connectTimeout(max(1, (int) config('south_sudan_gmc.connect_timeout', 10)))
                ->timeout(max(1, (int) config('south_sudan_gmc.timeout', 20)))
                ->get($searchUrl, [
                    'q' => $licenseNumber,
                    'filter' => 'doctor',
                    'sort' => 'name',
                    'page' => 1,
                ]);
        } catch (Throwable $exception) {
            Log::warning('SSGMC physician licence verification request failed', [
                'exception' => $exception::class,
            ]);

            return $this->result('unable_to_verify', 'The SSGMC registry could not be reached. Manual review is required.');
        }

        if (! $response->successful()) {
            Log::warning('SSGMC physician licence verification returned an unsuccessful HTTP response', [
                'http_status' => $response->status(),
            ]);

            return $this->result('unable_to_verify', 'The SSGMC registry is temporarily unavailable. Manual review is required.');
        }

        $payload = $response->json();
        if (! $this->hasExpectedStructure($payload)) {
            Log::warning('SSGMC physician licence verification returned an unexpected response structure');

            return $this->result('unable_to_verify', 'The SSGMC registry returned an unexpected response. Manual review is required.');
        }

        if ($payload['items'] === []) {
            if ((int) $payload['counts']['all'] === 0
                && (int) $payload['counts']['doctor'] === 0
                && (int) $payload['pagination']['total'] === 0) {
                return $this->notFound();
            }

            return $this->result('unable_to_verify', 'The SSGMC registry returned inconsistent search results. Manual review is required.');
        }

        $matchingRecords = [];
        foreach ($payload['items'] as $item) {
            if (! is_array($item)
                || ! is_string($item['givennumber'] ?? null)
                || trim($item['givennumber']) === ''
                || ! is_string($item['practitioner_category'] ?? null)) {
                return $this->result('unable_to_verify', 'The SSGMC registry returned an incomplete practitioner record. Manual review is required.');
            }

            if ($this->normalizeNumber($item['givennumber']) === $this->normalizeNumber($licenseNumber)) {
                $matchingRecords[] = $item;
            }
        }

        if (count($matchingRecords) !== 1) {
            return $matchingRecords === []
                ? $this->notFound()
                : $this->result('unable_to_verify', 'The SSGMC registry returned ambiguous matching records. Manual review is required.');
        }

        $record = $matchingRecords[0];
        $officialData = $this->officialData($record);
        if (strtolower(trim($record['practitioner_category'])) !== 'medical doctor') {
            return $this->result('not_found', 'The matching SSGMC record is not classified as a medical doctor.', $officialData);
        }

        return $this->result('verified', 'The SSGMC physician licence was verified.', $officialData);
    }

    private function hasExpectedStructure(mixed $payload): bool
    {
        if (! is_array($payload)
            || ! is_array($payload['items'] ?? null)
            || ! array_is_list($payload['items'])
            || ! is_array($payload['counts'] ?? null)
            || ! is_array($payload['pagination'] ?? null)) {
            return false;
        }

        foreach (['all', 'specialist', 'doctor', 'dentist', 'pharmacist'] as $countKey) {
            if (! isset($payload['counts'][$countKey]) || ! is_numeric($payload['counts'][$countKey])) {
                return false;
            }
        }

        foreach (['total', 'per_page', 'current_page', 'total_pages'] as $paginationKey) {
            if (! isset($payload['pagination'][$paginationKey]) || ! is_numeric($payload['pagination'][$paginationKey])) {
                return false;
            }
        }

        return true;
    }

    private function officialData(array $record): array
    {
        $fields = [
            'givennumber',
            'first_name',
            'middle_name',
            'surname',
            'practitioner_category',
            'reg_type',
            'academic_degree',
            'university_name',
            'nationality',
            'specialty',
            'sub_specialty',
        ];
        $officialData = [];
        foreach ($fields as $field) {
            $officialData[$field] = is_string($record[$field] ?? null)
                ? trim($record[$field]) ?: null
                : null;
        }

        $nameParts = array_filter([
            $officialData['first_name'],
            $officialData['middle_name'],
            $officialData['surname'],
        ], static fn (?string $part): bool => $part !== null && $part !== '');
        $fullName = implode(' ', $nameParts);

        $officialData['license_number'] = $officialData['givennumber'];
        $officialData['full_name'] = $fullName !== '' ? $fullName : null;
        unset($officialData['practitioner_name']);

        return $officialData;
    }

    private function normalizeNumber(string $number): string
    {
        return strtoupper(trim($number));
    }

    private function notFound(): array
    {
        return $this->result('not_found', 'No matching SSGMC medical doctor licence was found.');
    }

    private function result(string $status, string $message, ?array $officialData = null): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'source' => self::SOURCE,
            'official_data' => $officialData,
        ];
    }
}