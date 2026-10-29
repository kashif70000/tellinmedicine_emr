<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Services\GmcUkRegistrationSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class GmcUkRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function useGmcRecords(?array $records): void
    {
        $this->app->instance(GmcUkRegistrationSource::class, new class($records) implements GmcUkRegistrationSource {
            public function __construct(private ?array $records) {}

            public function find(string $fullName, ?string $gmcNumber = null): ?array
            {
                return $this->records;
            }

            public function sourceName(): string
            {
                return 'Test GMC source';
            }
        });
    }

    private function application(): array
    {
        return [
            'name' => 'Alice Smith',
            'email' => 'alice.uk@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '07123456789',
            'specialization' => 'General Practice',
            'experience' => 8,
            'fees' => 125,
            'clinic_name' => 'London Clinic',
            'address' => 'London',
            'license_state' => 'UK',
            'license_number' => '1234567',
            'certificate' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf'),
        ];
    }

    public function test_unavailable_gmc_check_allows_registration_pending_admin_review_with_gmc_number(): void
    {
        $this->useGmcRecords(null);

        $response = $this->postJson(route('register.doctor.store'), $this->application());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('verification_pending', true);
        $this->assertDatabaseHas('doctors', [
            'license_state' => 'UK',
            'license_number' => '1234567',
            'uk_verification_status' => 'unavailable',
            'is_verified' => false,
            'verification_status' => 'pending',
        ]);
    }

    public function test_gmc_no_match_still_blocks_registration(): void
    {
        $this->useGmcRecords([]);

        $response = $this->postJson(route('register.doctor.store'), $this->application());

        $response->assertStatus(422)->assertJsonPath('status', 'failed');
        $this->assertDatabaseMissing('users', ['email' => 'alice.uk@example.com']);
    }

    public function test_inconclusive_gmc_check_returns_a_successful_http_response_for_manual_review(): void
    {
        $this->useGmcRecords([[
            'full_name' => 'Alice Smith',
            'gmc_number' => '1234567',
            'profile_url' => 'https://www.gmc-uk.org/registrants/1234567',
            'registration_status' => null,
            'licence_status' => null,
        ]]);

        $response = $this->postJson(route('register.doctor.verify-uk-registration'), [
            'name' => 'Alice Smith',
            'gmc_registration_number' => '1234567',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 'manual_review');
    }

    public function test_gmc_verification_requires_a_registration_number(): void
    {
        $response = $this->postJson(route('register.doctor.verify-uk-registration'), [
            'name' => 'Alice Smith',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('gmc_registration_number');
    }
}
