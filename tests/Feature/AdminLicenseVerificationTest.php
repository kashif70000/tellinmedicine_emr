<?php

namespace Tests\Feature;

use App\Models\User;
use App\Http\Controllers\Admin\LicenseVerificationController;
use App\Services\MassachusettsLicenseSource;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminLicenseVerificationTest extends TestCase
{
    private const ENDPOINT = 'https://uslicensecheck.test/api/v1/lookup/verify';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'uslicensecheck.base_url' => 'https://uslicensecheck.test/api/v1',
            'uslicensecheck.api_key' => 'test-key-not-a-secret',
            'uslicensecheck.supported_states' => ['OH', 'CT', 'CO', 'DE', 'TX', 'IL', 'NH', 'AK', 'WA'],
        ]);
    }

    public function test_admin_page_lists_massachusetts_and_configured_states_but_not_kansas(): void
    {
        $request = HttpRequest::create('/admin/license-verification');
        $request->setUserResolver(fn () => $this->user('admin'));
        $view = app(LicenseVerificationController::class)->index($request);

        $this->assertSame('admin.license-verification', $view->name());
        $this->assertSame([
            'MA' => 'Massachusetts',
            'OH' => 'Ohio',
            'CT' => 'Connecticut',
            'CO' => 'Colorado',
            'DE' => 'Delaware',
            'TX' => 'Texas',
            'IL' => 'Illinois',
            'NH' => 'New Hampshire',
            'AK' => 'Alaska',
            'WA' => 'Washington',
        ], $view->getData()['states']);
    }

    public function test_non_admin_cannot_access_page_or_verification_endpoint(): void
    {
        $user = $this->user('doctor');

        $this->actingAs($user)
            ->get(route('admin.license-verification.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'US',
                'state' => 'OH',
                'license_number' => 'OH123456',
            ])
            ->assertForbidden();
    }

    public function test_supported_us_state_lookup_returns_official_result_without_writing_doctor_records(): void
    {
        Http::fake([
            self::ENDPOINT => function (Request $request) {
                return Http::response([
                    'found' => true,
                    'license_number' => $request->data()['license_number'],
                    'full_name' => 'Smith, Alice',
                    'board' => 'Medical Board',
                    'license_type' => 'Physician License',
                    'status' => 'Active',
                    'source_state' => $request->data()['source_state'],
                    'expiry_date' => now()->addYear()->toDateString(),
                    'multiple' => null,
                ]);
            },
        ]);

        $response = $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'US',
                'state' => 'OH',
                'license_number' => 'OH123456',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'verified')
            ->assertJsonPath('official_data.full_name', 'Smith, Alice')
            ->assertJsonPath('official_data.license_number', 'OH123456');
        Http::assertSent(fn (Request $request) => $request->data() === [
            'license_number' => 'OH123456',
            'source_state' => 'OH',
        ]);
    }

    public function test_massachusetts_lookup_uses_existing_verifier_inputs_without_writing_records(): void
    {
        $issueDate = now()->subYears(5)->toDateString();
        $expirationDate = now()->addYear()->toDateString();
        $this->app->instance(MassachusettsLicenseSource::class, new class($issueDate, $expirationDate) implements MassachusettsLicenseSource {
            public function __construct(private string $issueDate, private string $expirationDate) {}

            public function find(string $licenseType, string $licenseNumber): ?array
            {
                return [
                    'license_number' => $licenseNumber,
                    'full_name' => 'Alice Smith',
                    'license_type' => $licenseType,
                    'status' => 'Active',
                    'issue_date' => $this->issueDate,
                    'expiration_date' => $this->expirationDate,
                ];
            }

            public function sourceName(): string
            {
                return 'Test Massachusetts source';
            }
        });

        $response = $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'US',
                'state' => 'MA',
                'license_number' => 'MA123456',
                'license_type' => 'Full Physician',
                'license_issue_date' => $issueDate,
                'license_expiration_date' => $expirationDate,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'verified')
            ->assertJsonPath('official_data.full_name', 'Alice Smith')
            ->assertJsonPath('official_data.license_number', 'MA123456');
    }

    public function test_kansas_and_unconfigured_states_are_rejected_without_verification(): void
    {
        Http::fake();
        $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'US',
                'state' => 'KS',
                'license_number' => 'KS123456',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('state');

        $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'US',
                'state' => 'CA',
                'license_number' => 'CA123456',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('state');

        Http::assertNothingSent();
    }

    public function test_massachusetts_requires_its_existing_license_type_and_date_inputs(): void
    {
        $this->actingAs($this->user('admin'))
            ->postJson(route('admin.license-verification.verify'), [
                'country' => 'US',
                'state' => 'MA',
                'license_number' => 'MA123456',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'license_type',
                'license_issue_date',
                'license_expiration_date',
            ]);
    }

    public function test_multiple_consecutive_lookups_return_the_latest_submitted_license(): void
    {
        Http::fake([
            self::ENDPOINT => function (Request $request) {
                return Http::response([
                    'found' => true,
                    'license_number' => $request->data()['license_number'],
                    'board' => 'Medical Board',
                    'license_type' => 'Physician License',
                    'status' => 'Active',
                    'source_state' => $request->data()['source_state'],
                    'multiple' => null,
                ]);
            },
        ]);
        $admin = $this->user('admin');

        foreach (['OH111111', 'OH222222'] as $licenseNumber) {
            $this->actingAs($admin)
                ->postJson(route('admin.license-verification.verify'), [
                    'country' => 'US',
                    'state' => 'OH',
                    'license_number' => $licenseNumber,
                ])
                ->assertOk()
                ->assertJsonPath('official_data.license_number', $licenseNumber);
        }

        Http::assertSentCount(2);
    }

    private function user(string $role): User
    {
        $user = new User([
            'name' => ucfirst($role),
            'email' => $role.'@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'is_active' => true,
        ]);
        $user->forceFill(['id' => 1, 'uuid' => '00000001']);

        return $user;
    }
}
