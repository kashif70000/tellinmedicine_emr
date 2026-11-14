<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class TanzaniaLicenseVerificationService
{
    private const SOURCE = 'Medical Council of Tanganyika (MCT)';

    public function verify(string $registrationNumber): array
    {
        $registrationNumber = trim($registrationNumber);
        if ($registrationNumber === '') {
            return $this->result('failed', 'An MCT registration number is required.', self::SOURCE);
        }

        if (! (bool) config('tanzania.enabled', true)) {
            return $this->result('unavailable', 'Tanzania physician verification is disabled.', self::SOURCE);
        }

        $url = trim((string) config('tanzania.base_url', 'https://hprs.moh.go.tz/api/user_management/search-user/'));
        $councilUuid = trim((string) config('tanzania.council_uuid', '6904e90f-5df9-4e7e-b681-a4f2044ef042'));
        if ($url === '' || $councilUuid === '') {
            return $this->result('unavailable', 'Tanzania physician verification is not configured.', self::SOURCE);
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->connectTimeout(max(1, (int) config('tanzania.connect_timeout', 10)))
                ->timeout(max(1, (int) config('tanzania.timeout', 20)))
                ->post($url, [
                    'council' => $councilUuid,
                    'search_type' => trim((string) config('tanzania.search_type', 'mct_number')),
                    'mct_number' => $registrationNumber,
                ]);
        } catch (ConnectionException|InvalidArgumentException $exception) {
            Log::warning('Tanzania MCT verification connection failed', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->result('unable_to_verify', 'The Tanzania Medical Council verification service could not be reached.', self::SOURCE);
        }

        if (! $response->successful()) {
            return $this->result('unavailable', 'The Tanzania Medical Council verification service is temporarily unavailable.', self::SOURCE);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return $this->result('unable_to_verify', 'The Tanzania Medical Council response was invalid or empty.', self::SOURCE);
        }

        if ($payload === []) {
            return $this->result('not_found', 'No practitioner was found for the supplied MCT registration number.', self::SOURCE);
        }

        $matchingPractitioner = null;
        foreach ($payload as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $registrationMatches = $this->matchingRegistrationRecords($entry, $registrationNumber);
            if ($registrationMatches !== []) {
                $matchingPractitioner = $entry;
                break;
            }
        }

        if ($matchingPractitioner === null) {
            return $this->result('not_found', 'No practitioner was found for the supplied MCT registration number.', self::SOURCE);
        }

        $officialData = $this->officialData($matchingPractitioner, $registrationNumber);
        $profession = $this->profession($matchingPractitioner);
        if (! $this->isMedicalDoctor($profession)) {
            return $this->result(
                'failed',
                'The practitioner record does not belong to a medical doctor profession and cannot be verified as a physician.',
                self::SOURCE,
                $officialData
            );
        }

        if (empty($matchingPractitioner['registration'] ?? [])) {
            return $this->result(
                'manual_review',
                'The practitioner exists but no registration record was returned for verification.',
                self::SOURCE,
                $officialData
            );
        }

        $selectedRegistration = $this->matchingRegistrationRecord($matchingPractitioner, $registrationNumber);
        if ($selectedRegistration === null) {
            return $this->result(
                'manual_review',
                'The practitioner was found, but their MCT registration record could not be matched to the submitted number.',
                self::SOURCE,
                $officialData
            );
        }

        $applicationStatus = strtoupper(trim((string) ($selectedRegistration['application_status'] ?? '')));
        $expired = $this->coerceBoolean($selectedRegistration['expired'] ?? false);
        $expiryDate = $this->parseDateValue($selectedRegistration['expire_date'] ?? null);
        $isExpiredByDate = $expiryDate !== null && $expiryDate->lt(CarbonImmutable::today());
        $normalizedStatus = $this->normalizeApplicationStatus($applicationStatus);

        if ($normalizedStatus === 'approved') {
            if ($expired || $isExpiredByDate) {
                return $this->result(
                    'expired',
                    'The practitioner record is approved but the MCT registration has expired.',
                    self::SOURCE,
                    $officialData
                );
            }

            return $this->result(
                'verified',
                'The MCT registration is active and verified.',
                self::SOURCE,
                $officialData
            );
        }

        if ($normalizedStatus === 'pending') {
            return $this->result(
                'pending',
                'The practitioner record was found, but the MCT application is still pending approval.',
                self::SOURCE,
                $officialData
            );
        }

        if ($normalizedStatus === 'rejected') {
            return $this->result(
                'rejected',
                'The practitioner record was found, but the MCT application was rejected.',
                self::SOURCE,
                $officialData
            );
        }

        if ($normalizedStatus === 'manual_review') {
            return $this->result(
                'manual_review',
                'The practitioner record was found, but the MCT application status could not be conclusively verified.',
                self::SOURCE,
                $officialData
            );
        }

        return $this->result(
            'manual_review',
            'The practitioner record was found, but the MCT application status is not currently acceptable for automatic physician verification.',
            self::SOURCE,
            $officialData
        );
    }

    private function matchingRegistrationRecords(array $practitioner, string $registrationNumber): array
    {
        $registration = $practitioner['registration'] ?? [];
        if (! is_array($registration)) {
            return [];
        }

        return array_values(array_filter($registration, fn ($item): bool => is_array($item)
            && isset($item['registration_number'])
            && is_string($item['registration_number'])
            && strtolower(trim($item['registration_number'])) === strtolower(trim($registrationNumber))));
    }

    private function matchingRegistrationRecord(array $practitioner, string $registrationNumber): ?array
    {
        $matches = $this->matchingRegistrationRecords($practitioner, $registrationNumber);

        return $matches[0] ?? null;
    }

    private function officialData(array $practitioner, string $registrationNumber): array
    {
        $registrationRecord = $this->matchingRegistrationRecord($practitioner, $registrationNumber);
        $qualifications = $practitioner['professional_qualifications'] ?? [];
        $qualification = is_array($qualifications) && count($qualifications) > 0 ? $qualifications[0] : [];
        $profession = $this->profession($practitioner);

        return [
            'practitioner_name' => $this->stringValue($practitioner, 'get_full_name')
                ?? trim(($this->stringValue($practitioner, 'first_name') ?? '').' '.($this->stringValue($practitioner, 'middle_name') ?? '').' '.($this->stringValue($practitioner, 'last_name') ?? '')),
            'first_name' => $this->stringValue($practitioner, 'first_name'),
            'middle_name' => $this->stringValue($practitioner, 'middle_name'),
            'last_name' => $this->stringValue($practitioner, 'last_name'),
            'full_name' => $this->stringValue($practitioner, 'get_full_name')
                ?? trim(($this->stringValue($practitioner, 'first_name') ?? '').' '.($this->stringValue($practitioner, 'middle_name') ?? '').' '.($this->stringValue($practitioner, 'last_name') ?? '')),
            'sex' => $this->stringValue($practitioner, 'sex'),
            'nationality' => $this->stringValue($practitioner, 'nationality'),
            'profession_name' => $profession['profession_name'] ?? null,
            'profession_code' => $profession['profession_code'] ?? null,
            'registration_number' => $this->stringValue($registrationRecord ?? [], 'registration_number') ?? $registrationNumber,
            'application_status' => $this->stringValue($registrationRecord ?? [], 'application_status'),
            'expiration_date' => $this->stringValue($registrationRecord ?? [], 'expire_date'),
            'expired' => (bool) (($registrationRecord['expired'] ?? false) ?? false),
            'approval_date' => $this->stringValue($registrationRecord ?? [], 'approved_at'),
            'service_name' => $this->stringValue($registrationRecord ?? [], 'service_name'),
            'start_date' => $this->stringValue($registrationRecord ?? [], 'start_date'),
            'end_date' => $this->stringValue($registrationRecord ?? [], 'end_date'),
            'program_name' => $this->nestedStringValue($qualification, 'course_name', 'program_name'),
            'qualification_level' => $this->nestedStringValue($qualification, 'qualification_level', 'qualification_level_name'),
            'qualification_type' => $this->nestedStringValue($qualification, 'qualification_type', 'qualification_type_name'),
            'study_country' => $this->nestedStringValue($qualification, 'study_country', 'name'),
            'institution_name' => $this->stringValue($qualification, 'institution_or_university_name_other'),
            'start_year' => $this->numericValue($qualification, 'start_year'),
            'end_year' => $this->numericValue($qualification, 'end_year') ?? $this->numericValue($qualification, 'graduation_year'),
            'issue_year' => $this->numericValue($qualification, 'issue_year'),
            'qualification_verified' => (bool) ($qualification['is_verified'] ?? false),
            'raw_response' => $practitioner,
        ];
    }

    private function profession(array $practitioner): array
    {
        $userProfessions = $practitioner['user_profession'] ?? [];
        if (! is_array($userProfessions)) {
            return [];
        }

        foreach ($userProfessions as $userProfession) {
            if (! is_array($userProfession)) {
                continue;
            }

            $profession = $userProfession['profession'] ?? [];
            if (! is_array($profession)) {
                continue;
            }

            return [
                'profession_name' => $this->stringValue($profession, 'profession_name'),
                'profession_code' => $this->stringValue($profession, 'profession_code'),
                'profession_short_name' => $this->stringValue($profession, 'profession_short_name'),
            ];
        }

        return [];
    }

    private function isMedicalDoctor(array $profession): bool
    {
        $professionName = strtolower((string) ($profession['profession_name'] ?? ''));
        $professionCode = strtolower((string) ($profession['profession_code'] ?? ''));
        $professionShort = strtolower((string) ($profession['profession_short_name'] ?? ''));

        return str_contains($professionName, 'medical doctor')
            || str_contains($professionName, 'doctor of medicine')
            || str_contains($professionName, 'mbbs')
            || str_contains($professionName, 'mbchb')
            || in_array($professionCode, ['md', 'mbbs', 'mbchb', 'm.d.'], true)
            || in_array($professionShort, ['md', 'mbbs', 'mbchb'], true);
    }

    private function normalizeApplicationStatus(string $status): string
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', trim($status)));

        if ($normalized === '') {
            return 'manual_review';
        }

        if (str_contains($normalized, 'approved')) {
            return 'approved';
        }

        if (str_contains($normalized, 'pending') || str_contains($normalized, 'in_progress') || str_contains($normalized, 'under_review')) {
            return 'pending';
        }

        if (str_contains($normalized, 'rejected') || str_contains($normalized, 'declined') || str_contains($normalized, 'denied')) {
            return 'rejected';
        }

        if (str_contains($normalized, 'manual') || str_contains($normalized, 'review')) {
            return 'manual_review';
        }

        return 'manual_review';
    }

    private function coerceBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return filter_var(trim($value), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }

        return false;
    }

    private function parseDateValue(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $date = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return CarbonImmutable::createFromFormat('Y-m-d', $date);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T/', $date)) {
            return CarbonImmutable::parse($date);
        }

        return null;
    }

    private function stringValue(array|string|null $value, string $key): ?string
    {
        if (is_array($value) && array_key_exists($key, $value)) {
            return is_string($value[$key]) ? trim($value[$key]) : null;
        }

        return null;
    }

    private function nestedStringValue(array $value, string $key, string $nestedKey): ?string
    {
        if (! is_array($value[$key] ?? null)) {
            return null;
        }

        $nested = $value[$key];

        return is_string($nested[$nestedKey] ?? null) ? trim($nested[$nestedKey]) : null;
    }

    private function numericValue(array $value, string $key): mixed
    {
        if (! array_key_exists($key, $value)) {
            return null;
        }

        return $value[$key];
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
