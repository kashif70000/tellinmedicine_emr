<?php

namespace App\Console\Commands;

use App\Services\PharmacyImportService;
use Illuminate\Console\Command;

class ImportPharmacies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pharmacies:import
                            {city : The city to search pharmacies in}
                            {state : The 2-letter state code or state name}
                            {--limit= : Maximum number of pharmacy listings to import}
                            {--dry-run : Simulate the import and output statistics without writing to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import and normalize pharmacy listings from Google Maps for a given city and state';

    /**
     * Execute the console command.
     */
    public function handle(PharmacyImportService $importer): int
    {
        $city = trim((string) $this->argument('city'));
        $state = trim((string) $this->argument('state'));
        $rawLimit = $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        if (empty($city)) {
            $this->error('Error: City argument cannot be empty.');
            return Command::FAILURE;
        }

        if (empty($state)) {
            $this->error('Error: State argument cannot be empty.');
            return Command::FAILURE;
        }

        $limit = null;
        if ($rawLimit !== null) {
            if (!is_numeric($rawLimit) || (int) $rawLimit <= 0) {
                $this->error('Error: --limit must be a positive integer.');
                return Command::FAILURE;
            }
            $limit = (int) $rawLimit;
        }

        $this->info("=================================================");
        $this->info("          Pharmacy Directory Importer            ");
        $this->info("=================================================");
        $this->line("Target Location: <info>{$city}, {$state}</info>");
        if ($limit !== null) {
            $this->line("Limit:           <info>{$limit}</info>");
        } else {
            $this->line("Limit:           <info>No limit (until results exhausted)</info>");
        }
        if ($dryRun) {
            $this->warn("Mode:            DRY RUN (No database changes will be made)");
        } else {
            $this->line("Mode:            <info>LIVE INSERT/UPDATE</info>");
        }
        $this->newLine();

        $this->line("Fetching and processing listings from Google Maps...");
        $stats = $importer->import($city, $state, $limit, $dryRun);

        $this->newLine();
        $this->info("----------------- Import Summary ----------------");
        $this->line("City:     <info>{$stats['city']}</info>");
        $this->line("Found:    <info>{$stats['found']}</info>");
        $this->line("Inserted: <info>{$stats['inserted']}</info>");
        $this->line("Updated:  <info>{$stats['updated']}</info>");
        $this->line("Skipped:  <comment>{$stats['skipped']}</comment>");
        $this->line("Failed:   " . ($stats['failed'] > 0 ? "<error>{$stats['failed']}</error>" : "<info>0</info>"));
        if (!empty($stats['stop_reason'])) {
            $this->line("Stop Reason: <comment>" . $this->formatStopReason($stats['stop_reason']) . "</comment>");
        }
        $this->info("-------------------------------------------------");

        if ($dryRun) {
            $this->warn("Dry run complete. No records were written to the database.");
        } elseif ($stats['found'] === 0) {
            $this->warn("No pharmacy listings were found or imported for {$stats['city']}.");
        } else {
            $this->info("Import completed successfully.");
        }

        return Command::SUCCESS;
    }

    /**
     * Format the scraper stop reason for human-readable output.
     */
    protected function formatStopReason(string $reason): string
    {
        return match ($reason) {
            'requested_limit_reached' => 'Requested limit reached',
            'end_of_results_reached' => 'End of Google Maps results reached',
            'no_new_results_after_repeated_scrolling' => 'No new results after repeated scrolling',
            'safety_guard_reached', 'safety_timeout' => 'Safety scroll guard / timeout reached',
            'google_challenge_blocked', 'blocked' => 'Google challenge / bot detection encountered',
            default => ucfirst(str_replace('_', ' ', $reason)),
        };
    }
}
