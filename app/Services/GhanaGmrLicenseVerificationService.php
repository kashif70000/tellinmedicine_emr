<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class GhanaGmrLicenseVerificationService
{
    private const SOURCE = 'Ghana Medical Registry public credential lookup';

    public function verify(string $registrationNumber): array
    {
        $registrationNumber = trim($registrationNumber);
        if ($registrationNumber === '') {
            return $this->result('failed', 'A Ghana MDC registration number is required.');
        }

        $baseUrl = rtrim((string) config('ghana_gmr.base_url'), '/');
        $publishableKey = trim((string) config('ghana_gmr.publishable_key'));
        if ($baseUrl === '' || $publishableKey === '') {
            return $this->result('unable_to_verify', 'Ghana Medical Registry verification is not configured.');
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'apikey' => $publishableKey,
                    'Authorization' => 'Bearer '.$publishableKey,
                ])
                ->connectTimeout(max(1, (int) config('ghana_gmr.connect_timeout', 10)))
                ->timeout(max(1, (int) config('ghana_gmr.timeout', 20)))
                ->post($baseUrl.'/rest/v1/rpc/gmr_public_verify', [
                    'p_query' => $registrationNumber,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Ghana GMR credential verification connection failed', [
                'exception' => $exception::class,
            ]);

            return $this->result('unable_to_verify', 'The Ghana Medical Registry could not be reached.');
        } catch (InvalidArgumentException $exception) {
            Log::warning('Ghana GMR credential verification request failed', [
                'exception' => $exception::class,
            ]);

            return $this->result('unable_to_verify', 'The Ghana Medical Registry returned an unexpected response.');
        }

        if (! $response->successful()) {
            $this->logApiFailure($response);

            return $this->result('unable_to_verify', 'The Ghana Medical Registry is temporarily unavailable.');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            $this->logApiFailure($response);

            return $this->result('unable_to_verify', 'The Ghana Medical Registry returned an invalid response.');
        }

        if (array_is_list($payload)) {
            if (count($payload) !== 1 || ! is_array($payload[0])) {
                return $this->result(
                    count($payload) > 1 ? 'manual_review' : 'unable_to_verify',
                    count($payload) > 1
                        ? 'The Ghana Medical Registry returned ambiguous credential records requiring manual review.'
                        : 'The Ghana Medical Registry returned no usable credential record.'
                );
            }

            $payload = $payload[0];
        }

        if (! is_bool($payload['found'] ?? null)) {
            $this->logApiFailure($response, $payload);

            return $this->result('unable_to_verify', 'The Ghana Medical Registry response did not establish whether a credential was found.');
        }

        $officialData = $this->officialData($payload, $registrationNumber);
        if ($payload['found'] === false) {
            return $this->result(
                'unable_to_verify',
                'No matching record was returned. The registry states that a missing record is not proof of invalid registration.',
                $officialData
            );
        }

        if (strtoupper($this->stringField($payload, 'kind') ?? '') !== 'LICENCE') {
            return $this->result('unable_to_verify', 'The registry result is not an individual licence record.', $officialData);
        }

        $number = trim($this->stringField($payload, 'currentLicenceNumber') ?? '');
        $fallbackNumber = trim($this->stringField($payload, 'licenceNumber') ?? '');
        if ($number === '') {
            return $this->result('unable_to_verify', 'The registry record did not include a current registration number.', $officialData);
        }

        if ($fallbackNumber !== '' && $this->normalizeNumber($number) !== $this->normalizeNumber($fallbackNumber)) {
            return $this->result('manual_review', 'The registry returned conflicting current and historical registration numbers.', $officialData);
        }

        if ($this->normalizeNumber($number) !== $this->normalizeNumber($registrationNumber)) {
            return $this->result('failed', 'The registration number does not exactly match the registry record.', $officialData);
        }

        $councilCode = strtoupper(trim($this->stringField($payload, 'councilCode') ?? ''));
        $licenceType = strtoupper(trim($this->stringField($payload, 'licenceType') ?? ''));
        $professionCode = strtoupper(trim($this->stringField($payload, 'professionCode') ?? ''));
        $designation = strtoupper(trim($this->stringField($payload, 'designation') ?? ''));
        if ($councilCode === '' || $licenceType === '') {
            return $this->result('unable_to_verify', 'The registry record did not include the council and professional classification.', $officialData);
        }

        $councilName = strtoupper(trim($this->stringField($payload, 'council') ?? ''));
        $regulatorName = strtoupper(trim($this->stringField($payload, 'regulatorName') ?? ''));
        $expectedCouncilNames = ['MEDICAL AND DENTAL COUNCIL', 'MEDICAL AND DENTAL COUNCIL OF GHANA'];
        if (($councilName !== '' && ! in_array($councilName, $expectedCouncilNames, true))
            || ($regulatorName !== '' && ! in_array($regulatorName, $expectedCouncilNames, true))) {
            return $this->result('manual_review', 'The registry returned inconsistent council or regulator names.', $officialData);
        }

        if ($councilCode !== 'MDC'
            || $licenceType !== 'MDC-DOCTOR'
            || ($professionCode !== '' && $professionCode !== 'MDC-DOCTOR')
            || ($designation !== '' && $designation !== 'MEDICAL DOCTOR')) {
            return $this->result('failed', 'The registry record is not classified as a Ghana Medical and Dental Council medical doctor licence.', $officialData);
        }

        $status = strtoupper(trim($this->stringField($payload, 'status') ?? ''));
        if (($payload['isActive'] ?? null) === false
            || in_array($status, ['INACTIVE', 'EXPIRED', 'REVOKED', 'SUSPENDED', 'CANCELLED'], true)) {
            return $this->result('failed', 'The Ghana Medical Registry reports that this credential is not currently active.', $officialData);
        }

        $expiryDate = $this->parseDate($payload['expiryDate'] ?? null);
        if ($expiryDate !== null && $expiryDate->lt(CarbonImmutable::today())) {
            return $this->result('failed', 'The registry reports that this credential has expired.', $officialData);
        }

        if ($status !== 'ACTIVE' || ($payload['isActive'] ?? null) !== true || $expiryDate === null) {
            return $this->result('manual_review', 'The registry record requires manual confirmation of current licence status and expiry.', $officialData);
        }

        return $this->result(
            'manual_review',
            'The Ghana Medical Registry returned a matching active medical doctor record, but its regulatory authority and current practising-licence validity could not be independently confirmed. Manual review is required.',
            $officialData
        );
    }

    private function officialData(array $record, string $registrationNumber): array
    {
        $number = $this->stringField($record, 'currentLicenceNumber')
            ?? $this->stringField($record, 'licenceNumber');
        $expiryDate = $this->stringField($record, 'expiryDate');

        return [
            'kind' => $this->stringField($record, 'kind'),
            'found' => $record['found'] ?? null,
            'practitioner_name' => $this->stringField($record, 'practitionerName'),
            'registration_number' => $number,
            'current_registration_number' => $this->stringField($record, 'currentLicenceNumber'),
            'council' => $this->stringField($record, 'council'),
            'council_code' => $this->stringField($record, 'councilCode'),
            'designation' => $this->stringField($record, 'designation') ?? $this->stringField($record, 'licenceType'),
            'profession_code' => $this->stringField($record, 'professionCode'),
            'licence_type' => $this->stringField($record, 'licenceType'),
            'status' => $this->stringField($record, 'status'),
            'is_active' => is_bool($record['isActive'] ?? null) ? $record['isActive'] : null,
            'issue_date' => $this->parseDate($this->stringField($record, 'issueDate'))?->toDateString(),
            'expiry_date' => $this->parseDate($expiryDate)?->toDateString(),
            'expiration_date' => $this->parseDate($expiryDate)?->toDateString(),
            'regulator_name' => $this->stringField($record, 'regulatorName'),
            'authority_label' => $this->stringField($record, 'authorityLabel'),
            'registry_verified_at' => $this->stringField($record, 'verifiedAt'),
            'source_url' => 'https://www.ghanamedicalregistry.com/verify/credential/'.rawurlencode($registrationNumber),
        ];
    }

    private function stringField(array $record, string $field): ?string
    {
        return is_string($record[$field] ?? null) ? $record[$field] : null;
    }

    private function parseDate(mixed $date): ?CarbonImmutable
    {
        if (! is_string($date) || trim($date) === '') {
            return null;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', trim($date));

            return $parsed !== false && $parsed->format('Y-m-d') === trim($date) ? $parsed : null;
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function normalizeNumber(string $number): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($number))) ?? '';
    }

    private function logApiFailure(Response $response, ?array $payload = null): void
    {
        Log::warning('Ghana GMR credential verification API request failed', [
            'http_status' => $response->status(),
            'response_fields' => is_array($payload) ? array_slice(array_keys($payload), 0, 30) : [],
        ]);
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
