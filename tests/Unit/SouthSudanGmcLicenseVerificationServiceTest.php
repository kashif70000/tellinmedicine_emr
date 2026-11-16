<?php

namespace Tests\Unit;

use App\Services\SouthSudanGmcLicenseVerificationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SouthSudanGmcLicenseVerificationServiceTest extends TestCase
{
    private const ENDPOINT = 'https://ssgmc.test/online/registry_search';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'south_sudan_gmc.registry_url' => 'https://ssgmc.test/online/registry/0',
            'south_sudan_gmc.search_url' => self::ENDPOINT,
            'south_sudan_gmc.connect_timeout' => 10,
            'south_sudan_gmc.timeout' => 20,
        ]);
    }

    public function test_matching_medical_doctor_record_is_verified_and_extracted(): void
    {
        Http::fake([self::ENDPOINT.'*' => Http::response($this->payload([$this->record()]))]);

        $result = app(SouthSudanGmcLicenseVerificationService::class)->verify(' a00000293 ');

        $this->assertSame('verified', $result['status']);
        $this->assertSame('Abeer Ahmed Akasha Mohammed', $result['official_data']['full_name']);
        $this->assertSame('A00000293', $result['official_data']['license_number']);
        $this->assertSame('permanent', $result['official_data']['reg_type']);
        $this->assertSame('Zamzam University College', $result['official_data']['university_name']);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === self::ENDPOINT.'?q=a00000293&filter=doctor&sort=name&page=1'
            && $request->hasHeader('Accept', 'application/json')
            && $request->hasHeader('Referer', 'https://ssgmc.test/online/registry/0'));
    }

    public function test_valid_empty_items_response_is_not_found_not_an_api_failure(): void
    {
        Http::fake([self::ENDPOINT.'*' => Http::response($this->payload([]))]);

        $result = app(SouthSudanGmcLicenseVerificationService::class)->verify('A00000000');

        $this->assertSame('not_found', $result['status']);
        $this->assertNull($result['official_data']);
    }

    public function test_non_doctor_and_mismatched_records_are_not_verified(): void
    {
        Http::fake([self::ENDPOINT.'*' => Http::sequence()
            ->push($this->payload([$this->record(['practitioner_category' => 'Dentist'])]))
            ->push($this->payload([$this->record(['practitioner_category' => 'Pharmacist'])]))
            ->push($this->payload([$this->record(['givennumber' => 'B00000293'])]))]);
        $service = app(SouthSudanGmcLicenseVerificationService::class);

        $this->assertSame('not_found', $service->verify('A00000293')['status']);
        $this->assertSame('not_found', $service->verify('A00000293')['status']);
        $this->assertSame('not_found', $service->verify('A00000293')['status']);
    }

    public function test_timeout_invalid_json_server_error_and_malformed_structure_require_manual_review(): void
    {
        Http::fake([self::ENDPOINT.'*' => Http::sequence()
            ->pushFailedConnection('simulated timeout')
            ->push('not-json', 200, ['Content-Type' => 'application/json'])
            ->push(['items' => []])
            ->push(['items' => [], 'counts' => ['all' => 0, 'doctor' => 0], 'pagination' => [
                'total' => 0,
                'per_page' => 12,
                'current_page' => 1,
                'total_pages' => 0,
            ]], 503)]);
        $service = app(SouthSudanGmcLicenseVerificationService::class);

        foreach (range(1, 4) as $_) {
            $this->assertSame('unable_to_verify', $service->verify('A00000293')['status']);
        }
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
            'profileshacraina' => 'EFZXAHCFYOJIUJDMQRNL',
            'givennumber' => 'A00000293',
            'form_type' => 'practitioner',
            'legalnames' => null,
            'surname' => 'Mohammed',
            'first_name' => 'Abeer',
            'middle_name' => 'Ahmed Akasha',
            'practitioner_category' => 'Medical Doctor',
            'specialty' => null,
            'sub_specialty' => null,
            'postgrad_qualification' => null,
            'academic_degree' => 'Bachelor of Medicine & Surgery',
            'university_name' => 'Zamzam University College',
            'country_study' => 'Sudan',
            'year_graduation' => null,
            'year_completion' => '2024',
            'reg_type' => 'permanent',
            'practice_institution' => '',
            'nationality' => 'South Sudan',
            'photo_path' => 'client-550.jpeg',
            'created_at' => '2026-06-26 12:45:03',
        ], $overrides);
    }
}