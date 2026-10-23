<?php

namespace Tests\Unit;

use App\Services\MassachusettsLicenseSource;
use App\Services\MassachusettsLicenseVerificationService;
use App\Services\UnavailableMassachusettsLicenseSource;
use PHPUnit\Framework\TestCase;

class MassachusettsLicenseVerificationServiceTest extends TestCase
{
    private function service(?array $record): MassachusettsLicenseVerificationService
    {
        $source = new class($record) implements MassachusettsLicenseSource {
            public function __construct(private ?array $record) {}

            public function find(string $licenseType, string $licenseNumber): ?array
            {
                return $this->record;
            }

            public function sourceName(): string
            {
                return 'Authorized test source';
            }
        };

        return new MassachusettsLicenseVerificationService($source);
    }

    private function record(array $overrides = []): array
    {
        return array_merge([
            'license_number' => 'MA123456',
            'full_name' => 'Alice Smith',
            'license_type' => 'Full Physician',
            'status' => 'Active',
            'issue_date' => '2021-01-01',
            'expiration_date' => now()->addYear()->toDateString(),
        ], $overrides);
    }

    private function application(array $overrides = []): array
    {
        return array_merge([
            'license_state' => 'MA',
            'license_type' => 'Full Physician',
            'license_number' => 'MA123456',
            'license_issue_date' => '2021-01-01',
            'license_expiration_date' => now()->addYear()->toDateString(),
            'name' => 'Dr. Alice Smith',
        ], $overrides);
    }

    public function test_matching_official_record_is_verified(): void
    {
        $result = $this->service($this->record())->verify($this->application());

        $this->assertSame('verified', $result['status']);
    }

    public function test_name_mismatch_does_not_prevent_verification_when_license_number_and_dates_match(): void
    {
        $result = $this->service($this->record(['full_name' => 'Other Person']))
            ->verify($this->application());

        $this->assertSame('verified', $result['status']);
    }

    public function test_missing_official_name_does_not_prevent_verification(): void
    {
        $result = $this->service($this->record(['full_name' => '']))
            ->verify($this->application());

        $this->assertSame('verified', $result['status']);
    }

    public function test_issue_date_mismatch_does_not_verify(): void
    {
        $result = $this->service($this->record(['issue_date' => '2020-01-01']))
            ->verify($this->application());

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('issue date', strtolower($result['message']));
    }

    public function test_expiration_date_mismatch_does_not_verify(): void
    {
        $result = $this->service($this->record(['expiration_date' => '2030-01-01']))
            ->verify($this->application());

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('expiration date', strtolower($result['message']));
    }

    public function test_expired_official_record_is_expired(): void
    {
        $result = $this->service($this->record([
            'expiration_date' => now()->subDay()->toDateString(),
        ]))->verify($this->application());

        $this->assertSame('expired', $result['status']);
    }

    public function test_unavailable_source_never_verifies(): void
    {
        $service = new MassachusettsLicenseVerificationService(new UnavailableMassachusettsLicenseSource());

        $result = $service->verify($this->application());

        $this->assertSame('unable_to_verify', $result['status']);
        $this->assertStringContainsString('manual verification', strtolower($result['message']));
    }
}