<?php

namespace Tests\Unit;

use App\Services\GmcUkRegistrationSource;
use App\Services\GmcUkVerificationService;
use PHPUnit\Framework\TestCase;

class GmcUkVerificationServiceTest extends TestCase
{
    public function test_matching_gmc_record_can_be_verified_using_name_and_submitted_number(): void
    {
        $source = new class implements GmcUkRegistrationSource {
            public ?string $requestedGmcNumber = 'not-requested';

            public function find(string $fullName, ?string $gmcNumber = null): ?array
            {
                $this->requestedGmcNumber = $gmcNumber;

                return [[
                    'full_name' => 'Alice Smith',
                    'gmc_number' => '1234567',
                    'profile_url' => 'https://www.gmc-uk.org/registrants/1234567',
                    'registration_status' => 'Registered',
                    'licence_status' => 'Licensed to practise',
                ]];
            }

            public function sourceName(): string
            {
                return 'Fake GMC Register';
            }
        };

        $result = (new GmcUkVerificationService($source))->verify([
            'name' => 'Alice Smith',
            'gmc_registration_number' => '1234567',
        ]);

        $this->assertSame('verified', $result['status']);
        $this->assertSame('1234567', $result['official_data']['gmc_number']);
        $this->assertSame('1234567', $source->requestedGmcNumber);
    }

    public function test_mismatched_gmc_number_fails_verification(): void
    {
        $source = new class implements GmcUkRegistrationSource {
            public function find(string $fullName, ?string $gmcNumber = null): ?array
            {
                return [[
                    'full_name' => 'Alice Smith',
                    'gmc_number' => '1234567',
                    'profile_url' => 'https://www.gmc-uk.org/registrants/1234567',
                    'registration_status' => 'Registered',
                    'licence_status' => 'Licensed to practise',
                ]];
            }

            public function sourceName(): string
            {
                return 'Fake GMC Register';
            }
        };

        $result = (new GmcUkVerificationService($source))->verify([
            'name' => 'Alice Smith',
            'gmc_registration_number' => '7654321',
        ]);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('does not match', $result['message']);
    }

    public function test_missing_gmc_number_fails_verification_without_searching(): void
    {
        $source = new class implements GmcUkRegistrationSource {
            public function find(string $fullName, ?string $gmcNumber = null): ?array
            {
                throw new \RuntimeException('Search should not run without a GMC number.');
            }

            public function sourceName(): string
            {
                return 'Fake GMC Register';
            }
        };

        $result = (new GmcUkVerificationService($source))->verify([
            'name' => 'Alice Smith',
        ]);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('required', $result['message']);
    }

    public function test_matching_number_verifies_name_with_omitted_middle_names(): void
    {
        $source = new class implements GmcUkRegistrationSource {
            public function find(string $fullName, ?string $gmcNumber = null): ?array
            {
                return [[
                    'full_name' => 'Martin Ivo Metombe Ngomba',
                    'gmc_number' => '1234567',
                    'profile_url' => 'https://www.gmc-uk.org/registrants/1234567',
                    'registration_status' => 'Registered',
                    'licence_status' => 'Licensed to practise',
                ]];
            }

            public function sourceName(): string
            {
                return 'Fake GMC Register';
            }
        };

        $result = (new GmcUkVerificationService($source))->verify([
            'name' => 'Martin Ngomba',
            'gmc_registration_number' => '1234567',
        ]);

        $this->assertSame('verified', $result['status']);
    }

    public function test_mismatched_number_fails_even_when_multiple_name_matches_are_returned(): void
    {
        $source = new class implements GmcUkRegistrationSource {
            public function find(string $fullName, ?string $gmcNumber = null): ?array
            {
                return [
                    [
                        'full_name' => 'Alice Smith',
                        'gmc_number' => '1234567',
                        'profile_url' => 'https://www.gmc-uk.org/registrants/1234567',
                        'registration_status' => 'Registered',
                        'licence_status' => 'Licensed to practise',
                    ],
                    [
                        'full_name' => 'Alice Smith',
                        'gmc_number' => '7654321',
                        'profile_url' => 'https://www.gmc-uk.org/registrants/7654321',
                        'registration_status' => 'Registered',
                        'licence_status' => 'Licensed to practise',
                    ],
                ];
            }

            public function sourceName(): string
            {
                return 'Fake GMC Register';
            }
        };

        $result = (new GmcUkVerificationService($source))->verify([
            'name' => 'Alice Smith',
            'gmc_registration_number' => '9999999',
        ]);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('does not match', $result['message']);
    }
}
