<?php

namespace Tests\Unit;

use App\Services\DoctorLicenseLookupService;
use App\Services\USLicenseCheckVerificationService;
use App\Services\USLicenseVerificationOrchestrator;
use Mockery;
use Tests\TestCase;

class USLicenseVerificationOrchestratorTest extends TestCase
{
    public function test_massachusetts_stays_with_doctor_license_lookup_when_primary_is_unavailable(): void
    {
        $primary = Mockery::mock(DoctorLicenseLookupService::class);
        $primary->shouldReceive('verify')
            ->once()
            ->with('US', 'MA', 'MA123456')
            ->andReturn([
                'status' => 'unable_to_verify',
                'message' => 'DoctorLicenseLookup is unavailable.',
                'source' => 'DoctorLicenseLookup',
                'official_data' => [],
            ]);

        $fallback = Mockery::mock(USLicenseCheckVerificationService::class);
        $fallback->shouldNotReceive('verify');

        $result = (new USLicenseVerificationOrchestrator($primary, $fallback))->verify([
            'license_state' => 'MA',
            'license_number' => 'MA123456',
        ]);

        $this->assertSame('unable_to_verify', $result['status']);
        $this->assertSame('DoctorLicenseLookup', $result['source']);
    }
}
