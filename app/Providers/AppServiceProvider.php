<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Models\Prescription;
use App\Models\Invoice;
use App\Policies\PrescriptionPolicy;
use App\Policies\InvoicePolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Services\Contracts\PharmacyScraperInterface::class,
            \App\Services\GoogleMapsPharmacyScraper::class
        );

        $this->app->bind(
            \App\Services\Contracts\LabScraperInterface::class,
            \App\Services\GoogleMapsLabScraper::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrap();

        Gate::policy(Prescription::class, PrescriptionPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        date_default_timezone_set('Asia/Karachi');
        config(['app.timezone' => 'Asia/Karachi']);

        try {
            $siteName = \App\Models\Setting::getSiteName();
            if (! empty($siteName)) {
                config(['app.name' => $siteName]);
            }
        } catch (\Throwable $e) {
            // Fallback gracefully if database or table is not initialized
        }
    }
}
 