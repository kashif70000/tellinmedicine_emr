<?php

namespace App\Console\Commands;

use App\Services\Fax\LaboratoryFaxEnrichmentService;
use Illuminate\Console\Command;

class EnrichLaboratoryFaxCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'labs:enrich-fax
                            {--city= : Filter laboratories by city}
                            {--state= : Filter laboratories by 2-letter state code}
                            {--limit= : Maximum number of laboratories to process}
                            {--dry-run : Perform evaluation and scoring without writing any database changes}
                            {--only-missing : Process only records that have never been checked}
                            {--retry-not-found : Include records previously marked as not_found or failed}';

    /**
     * The console command description.
     */
    protected $description = 'Enrich existing diagnostic laboratories with verified NPI and Fax numbers from CMS NPPES Registry';

    /**
     * Execute the console command.
     */
    public function handle(LaboratoryFaxEnrichmentService $service): int
    {
        $city = $this->option('city');
        $state = $this->option('state');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');
        $onlyMissing = (bool) $this->option('only-missing');
        $retryNotFound = (bool) $this->option('retry-not-found');

        $this->newLine();
        $this->line('============================================================');
        $this->info('     Laboratory Fax & NPI Enrichment' . ($dryRun ? ' — DRY RUN' : ''));
        $this->line('============================================================');
        $sourceType = config('nppes.source', 'bulk') === 'bulk'
            ? 'CMS NPPES Bulk Dataset (Local Directory)'
            : 'CMS NPPES Registry API (Public)';
        $this->line("Source: {$sourceType}");
        if ($city || $state) {
            $this->line('Location Filter: ' . trim("{$city}, {$state}", ', '));
        }
        if ($limit) {
            $this->line("Batch Limit: {$limit}");
        }
        if ($dryRun) {
            $this->warn('DRY RUN MODE ENABLED: Zero database changes will be written.');
        }
        $this->line('------------------------------------------------------------');

        $response = $service->enrich([
            'city' => $city,
            'state' => $state,
            'limit' => $limit,
            'only_missing' => $onlyMissing,
            'retry_not_found' => $retryNotFound,
            'dry_run' => $dryRun,
        ]);

        $stats = $response['stats'];
        $results = $response['results'];

        if (!empty($results) && isset($results[0]['notes']) && str_contains($results[0]['notes'], 'NPPES local bulk directory is empty')) {
            $this->error('Error: NPPES local bulk directory is empty. Run nppes:import first.');
            return 1;
        }

        if (empty($results)) {
            $this->warn('No laboratory records found matching the specified criteria.');
            return 0;
        }

        foreach ($results as $index => $r) {
            $num = $index + 1;
            $this->newLine();
            $this->line("<comment>[#{$num}] Local Laboratory:</comment> {$r['entity_name']}");
            $this->line("      Address: {$r['entity_address']}");

            if (!empty($r['candidate_org'])) {
                $this->line("      <info>NPPES Candidate:</info> {$r['candidate_org']}");
                $this->line("      Practice Address: {$r['candidate_address']}");
                $this->line("      NPI: <info>{$r['npi']}</info>");
                $this->line("      Practice Fax: " . ($r['fax'] ? "<info>{$r['fax']}</info>" : '<comment>Not provided in NPPES</comment>'));
            } else {
                $this->line("      <comment>NPPES Candidate:</comment> None matched safely");
            }

            $scoreColor = ($r['match_score'] >= 80) ? 'info' : 'comment';
            $this->line("      Match Score: <{$scoreColor}>{$r['match_score']}/100</{$scoreColor}>");
            $this->line("      Status: <info>{$r['status']}</info> (" . ($r['notes'] ?? 'No notes') . ')');
            $this->line("      DB Action: " . ($dryRun ? '<comment>DRY RUN (No Write)</comment>' : "<info>{$r['action']}</info>"));
        }

        $this->newLine();
        $this->line('============================================================');
        $this->info('                      Summary Statistics');
        $this->line('============================================================');

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Laboratories Checked', $stats['checked']],
                ['Confident NPPES Matches', $stats['matched']],
                ['  └─ Fax Number Found', $stats['fax_found']],
                ['  └─ Matched Without Fax (no_fax)', $stats['no_fax']],
                ['Not Found in NPPES', $stats['not_found']],
                ['Ambiguous Candidates', $stats['ambiguous']],
                ['Lookup / Network Failed', $stats['failed']],
            ]
        );

        if ($dryRun) {
            $this->warn('*** DRY RUN COMPLETE: NO DATABASE CHANGES WERE MADE. ***');
        } else {
            $this->info('Database enrichment successfully completed.');
        }

        $this->line('============================================================');
        $this->newLine();

        return 0;
    }
}
