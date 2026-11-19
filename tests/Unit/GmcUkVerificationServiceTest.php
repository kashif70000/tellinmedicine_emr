<?php

namespace Tests\Unit;

use App\Services\GmcUkRegistrationSource;
use App\Services\GmcUkVerificationService;
use PHPUnit\Framework\TestCase;

class GmcUkVerificationServiceTest extends TestCase
{
    public function test_matching_gmc_record_can_be_verified_using_the_submitted_number_only(): void
    {
        $source = new class implements GmcUkRegistrationSource {
            public ?string $requestedGmcNumber = 'not-requested';

            public function find(string $gmcNumber): ?array
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
            'gmc_registration_number' => '1234567',
        ]);

        $this->assertSame('verified', $result['status']);
        $this->assertSame('1234567', $result['official_data']['gmc_number']);
        $this->assertSame('1234567', $source->requestedGmcNumber);
        $this->assertArrayNotHasKey('raw_text', $result['official_data']);
    }

    public function test_mismatched_gmc_number_fails_verification(): void
    {
        $source = new class implements GmcUkRegistrationSource {
            public function find(string $gmcNumber): ?array
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
            'gmc_registration_number' => '7654321',
        ]);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('does not match', $result['message']);
    }

    public function test_missing_gmc_number_fails_verification_without_searching(): void
    {
        $source = new class implements GmcUkRegistrationSource {
            public function find(string $gmcNumber): ?array
            {
                throw new \RuntimeException('Search should not run without a GMC number.');
            }

            public function sourceName(): string
            {
                return 'Fake GMC Register';
            }
        };

        $result = (new GmcUkVerificationService($source))->verify([
        ]);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('required', $result['message']);
    }

    public function test_invalid_gmc_number_fails_without_searching(): void
    {
        $source = new class implements GmcUkRegistrationSource {
            public function find(string $gmcNumber): ?array
            {
                throw new \RuntimeException('Search should not run with an invalid GMC number.');
            }

            public function sourceName(): string
            {
                return 'Fake GMC Register';
            }
        };

        $result = (new GmcUkVerificationService($source))->verify([
            'gmc_registration_number' => '1234ABC',
        ]);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('seven-digit', $result['message']);
    }

    public function test_matching_number_verifies_without_a_submitted_name(): void
    {
        $source = new class implements GmcUkRegistrationSource {
            public function find(string $gmcNumber): ?array
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
            'gmc_registration_number' => '1234567',
        ]);

        $this->assertSame('verified', $result['status']);
    }

    public function test_mismatched_number_fails_even_when_other_records_are_returned(): void
    {
        $source = new class implements GmcUkRegistrationSource {
            public function find(string $gmcNumber): ?array
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
            'gmc_registration_number' => '9999999',
        ]);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('does not match', $result['message']);
    }
}
