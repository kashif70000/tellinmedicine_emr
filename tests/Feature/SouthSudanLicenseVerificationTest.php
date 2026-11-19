<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SouthSudanLicenseVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://ssgmc.test/online/registry_search';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        config([
            'south_sudan_gmc.registry_url' => 'https://ssgmc.test/online/registry/0',
            'south_sudan_gmc.search_url' => self::ENDPOINT,
            'south_sudan_gmc.connect_timeout' => 10,
            'south_sudan_gmc.timeout' => 20,
        ]);
    }

    public function test_registration_verification_endpoint_returns_ssgmc_result_and_records_attempt(): void
    {
        $this->get(route('register.doctor'))
            ->assertOk()
            ->assertSee('<option value="SS">South Sudan</option>', false);

        Http::fake([self::ENDPOINT.'*' => Http::response($this->payload([$this->record()]))]);

        $this->postJson(route('register.doctor.verify-license'), [
            'license_country' => 'SS',
            'license_state' => 'SS',
            'license_number' => 'A00000293',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'verified')
            ->assertJsonPath('official_data.full_name', 'Abeer Ahmed Akasha Mohammed')
            ->assertJsonPath('official_data.specialty', null)
            ->assertJsonMissingPath('official_data.practitioner_name');

        $this->assertDatabaseHas('license_verification_attempts', [
            'license_state' => 'SS',
            'license_number' => 'A00000293',
            'status' => 'verified',
            'source' => 'South Sudan General Medical Council (SSGMC)',
        ]);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === self::ENDPOINT.'?q=A00000293&filter=doctor&sort=name&page=1'
            && $request->hasHeader('Accept', 'application/json'));
    }

    public function test_verified_registry_data_is_persisted_during_registration(): void
    {
        Http::fake([self::ENDPOINT.'*' => Http::response($this->payload([$this->record()]))]);

        $this->postJson(route('register.doctor.store'), $this->application())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('verification_pending', false);

        $this->assertDatabaseHas('doctors', [
            'license_state' => 'SS',
            'license_number' => 'A00000293',
            'license_type' => 'SSGMC Physician Registration',
            'license_verification_status' => 'verified',
            'license_verification_source' => 'South Sudan General Medical Council (SSGMC)',
            'is_verified' => true,
            'verification_status' => 'approved',
        ]);
        $doctor = \App\Models\Doctor::query()->where('license_state', 'SS')->firstOrFail();
        $this->assertSame('Abeer Ahmed Akasha Mohammed', $doctor->license_verification_data['full_name']);
        $this->assertSame('Medical Doctor', $doctor->license_verification_data['practitioner_category']);
        $this->assertNotNull($doctor->license_verified_at);
        $this->assertDatabaseHas('license_verification_attempts', [
            'doctor_id' => $doctor->id,
            'license_state' => 'SS',
            'status' => 'verified',
        ]);
    }

    public function test_empty_registry_result_blocks_registration_and_logs_not_found(): void
    {
        Http::fake([self::ENDPOINT.'*' => Http::response($this->payload([]))]);

        $this->postJson(route('register.doctor.store'), $this->application())
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 'not_found');

        $this->assertDatabaseMissing('users', ['email' => 'south-sudan-doctor@example.com']);
        $this->assertDatabaseHas('license_verification_attempts', [
            'license_state' => 'SS',
            'license_number' => 'A00000293',
            'status' => 'not_found',
        ]);
    }

    public function test_admin_can_select_south_sudan_and_uses_the_same_service_with_attempt_logging(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Http::fake([self::ENDPOINT.'*' => Http::response($this->payload([$this->record()]))]);

        $this->actingAs($admin)
            ->get(route('admin.license-verification.index'))
            ->assertOk()
            ->assertSee('<option value="SS">South Sudan</option>', false);

        $this->actingAs($admin)
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'SS',
                'license_number' => 'A00000293',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'verified')
            ->assertJsonPath('official_data.full_name', 'Abeer Ahmed Akasha Mohammed')
            ->assertJsonPath('official_data.academic_degree', 'Bachelor of Medicine & Surgery');

        $this->assertDatabaseHas('license_verification_attempts', [
            'actor_user_id' => $admin->id,
            'license_state' => 'SS',
            'license_number' => 'A00000293',
            'status' => 'verified',
            'source' => 'South Sudan General Medical Council (SSGMC)',
        ]);
    }

    private function application(array $overrides = []): array
    {
        return array_merge([
            'name' => 'South Sudan Doctor',
            'email' => 'south-sudan-doctor@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '+211700000000',
            'specialization' => 'General Medicine',
            'experience' => 6,
            'fees' => 150,
            'clinic_name' => 'Juba Clinic',
            'address' => 'Juba',
            'license_country' => 'SS',
            'license_state' => 'SS',
            'license_number' => 'A00000293',
            'certificate' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf'),
        ], $overrides);
    }

    private function payload(array $items): array
    {
        return [
            'items' => $items,
            'counts' => [
                'all' => count($items),
                'specialist' => 0,
                'doctor' => count($items),
                'dentist' => 0,
                'pharmacist' => 0,
            ],
            'pagination' => [
                'total' => count($items),
                'per_page' => 12,
                'current_page' => 1,
                'total_pages' => count($items) > 0 ? 1 : 0,
            ],
        ];
    }

    private function record(array $overrides = []): array
    {
        return array_merge([
            'id' => '550',
            'givennumber' => 'A00000293',
            'first_name' => 'Abeer',
            'middle_name' => 'Ahmed Akasha',
            'surname' => 'Mohammed',
            'practitioner_category' => 'Medical Doctor',
            'reg_type' => 'permanent',
            'academic_degree' => 'Bachelor of Medicine & Surgery',
            'university_name' => 'Zamzam University College',
            'nationality' => 'South Sudan',
            'specialty' => null,
            'sub_specialty' => null,
        ], $overrides);
    }
}