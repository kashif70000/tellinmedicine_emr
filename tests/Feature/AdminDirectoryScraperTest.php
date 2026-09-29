<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\Contracts\LabScraperInterface;
use App\Services\Contracts\PharmacyScraperInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDirectoryScraperTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake scrapers to prevent external network calls during tests
        $this->app->bind(PharmacyScraperInterface::class, function () {
            return new class implements PharmacyScraperInterface {
                public function search(string $city, string $state, ?int $limit = null): array
                {
                    return [
                        [
                            'name' => 'Walgreens Pharmacy',
                            'street_address' => '100 Main St',
                            'city' => $city,
                            'state' => $state,
                            'postal_code' => '02111',
                            'phone' => '(555) 123-4567',
                            'website' => 'https://walgreens.com',
                            'source' => 'google_maps',
                        ],
                        [
                            'name' => 'CVS Pharmacy',
                            'street_address' => '200 Washington St',
                            'city' => $city,
                            'state' => $state,
                            'postal_code' => '02112',
                            'phone' => '(555) 987-6543',
                            'website' => 'https://cvs.com',
                            'source' => 'google_maps',
                        ]
                    ];
                }

                public function getLastStopReason(): ?string
                {
                    return 'requested_limit_reached';
                }
            };
        });

        $this->app->bind(LabScraperInterface::class, function () {
            return new class implements LabScraperInterface {
                public function search(string $city, string $state, ?int $limit = null): array
                {
                    return [
                        [
                            'name' => 'Quest Diagnostics',
                            'category' => 'Medical laboratory',
                            'street_address' => '300 Medical Center Dr',
                            'city' => $city,
                            'state' => $state,
                            'postal_code' => '02115',
                            'phone' => '(555) 456-7890',
                            'website' => 'https://questdiagnostics.com',
                            'source' => 'google_maps',
                        ]
                    ];
                }

                public function getLastStopReason(): ?string
                {
                    return 'end_of_results_reached';
                }
            };
        });
    }

    public function test_non_admin_cannot_access_scraper_portal(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);

        $response = $this->actingAs($patient)->get(route('admin.directory-scraper.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_view_scraper_portal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Pharmacy::create([
            'name' => 'Existing Pharmacy',
            'street_address' => '123 Test St',
            'city' => 'Boston',
            'state' => 'MA',
        ]);

        Laboratory::create([
            'name' => 'Existing Lab',
            'category' => 'Medical laboratory',
            'street_address' => '456 Lab Ave',
            'city' => 'Boston',
            'state' => 'MA',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.directory-scraper.index'));
        $response->assertStatus(200);
        $response->assertSee('Directory Scraper');
        $response->assertSee('Healthcare Directory Importer');
    }

    public function test_admin_can_scrape_pharmacies(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson(route('admin.directory-scraper.scrape'), [
            'type' => 'pharmacies',
            'city' => 'Boston',
            'state' => 'MA',
            'limit' => 10,
            'dry_run' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'type',
            'city',
            'state',
            'summary' => [
                'found',
                'inserted',
                'updated',
                'skipped',
                'failed',
            ],
            'items',
        ]);

        $this->assertDatabaseHas('pharmacies', [
            'name' => 'Walgreens Pharmacy',
            'city' => 'Boston',
            'state' => 'MA',
        ]);
    }

    public function test_admin_can_scrape_laboratories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson(route('admin.directory-scraper.scrape'), [
            'type' => 'laboratories',
            'city' => 'Boston',
            'state' => 'MA',
            'limit' => 5,
            'dry_run' => false,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('laboratories', [
            'name' => 'Quest Diagnostics',
            'city' => 'Boston',
            'state' => 'MA',
        ]);
    }

    public function test_admin_can_scrape_with_no_limit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson(route('admin.directory-scraper.scrape'), [
            'type' => 'pharmacies',
            'city' => 'Boston',
            'state' => 'MA',
            'limit' => 0,
            'dry_run' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_admin_can_get_location_suggestions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->getJson(route('admin.directory-scraper.location-suggest', ['q' => 'Colorado']));
        $response->assertStatus(200);
        $response->assertJsonStructure(['results']);
    }
}
