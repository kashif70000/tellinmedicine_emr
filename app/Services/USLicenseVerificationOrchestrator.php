<?php

namespace App\Services;

class USLicenseVerificationOrchestrator
{
    public function __construct(
        private DoctorLicenseLookupService $doctorLicenseLookup,
        private USLicenseCheckVerificationService $usLicenseCheckVerifier
    ) {}

    public function verify(array $application): array
    {
        $state = strtoupper(trim((string) ($application['license_state'] ?? '')));
        $licenseNumber = trim((string) ($application['license_number'] ?? ''));
        $primaryResult = $this->doctorLicenseLookup->verify('US', $state, $licenseNumber);

        if ($primaryResult['status'] === 'verified' || ! $this->shouldUseFallback($primaryResult)) {
            return $primaryResult;
        }

        if ($state === 'MA' || ! $this->hasUSLicenseCheckFallback($state)) {
            return $primaryResult;
        }

        return $this->usLicenseCheckVerifier->verify($application);
    }

    private function shouldUseFallback(array $result): bool
    {
        if (($result['status'] ?? null) === 'unable_to_verify') {
            return true;
        }

        $statusCode = (int) ($result['official_data']['status_code'] ?? 0);

        return in_array($statusCode, [401, 403, 408, 425, 429], true) || $statusCode >= 500;
    }

    private function hasUSLicenseCheckFallback(string $state): bool
    {
        $supportedStates = array_merge(
            config('uslicensecheck.supported_states', []),
            ['FL', 'KS', 'MT', 'NH']
        );

        return in_array($state, $supportedStates, true);
    }
}
