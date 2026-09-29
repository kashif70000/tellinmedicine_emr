<?php

namespace App\Console\Commands;

use App\Services\Nppes\NppesBulkImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportNppesBulkCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'nppes:import
                            {file : Path to the official CMS NPPES CSV or ZIP file}
                            {--type=all : Target healthcare entity type (all, pharmacy, laboratory)}
                            {--dry-run : Scan and score file without writing any database records}
                            {--batch-size=1000 : Number of records to process per batch}
                            {--force : Force re-import even if the file hash matches a previous import}';

    /**
     * The console command description.
     */
    protected $description = 'Import or update local NPPES healthcare directory from CMS bulk dataset file';

    /**
     * Execute the console command.
     */
    public function handle(NppesBulkImportService $importService): int
    {
        $filePath = $this->argument('file');
        $type = $this->option('type') ?: 'all';
        $dryRun = (bool) $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size') ?: 1000;
        $force = (bool) $this->option('force');

        if (!file_exists($filePath)) {
            $this->error("Target file does not exist: {$filePath}");
            return 1;
        }

        $this->newLine();
        $this->line('============================================================');
        $this->info('      NPPES Bulk Dataset Ingestion' . ($dryRun ? ' — DRY RUN' : ''));
        $this->line('============================================================');
        $this->line('Source File: ' . realpath($filePath));
        $this->line("Target Type: " . ucfirst($type));
        $this->line("Batch Size:  {$batchSize}");
        if ($dryRun) {
            $this->warn('DRY RUN MODE ENABLED: ZERO database changes will be written.');
        }
        $this->line('------------------------------------------------------------');

        $lastReported = 0;
        $progressCallback = function (int $scanned, int $relevant, int $batchCount) use (&$lastReported) {
            if ($this->output->isQuiet()) {
                return;
            }

            // Print progress milestone every 50,000 scanned rows
            if ($scanned - $lastReported >= 50000) {
                $lastReported = $scanned;
                $formattedScanned = number_format($scanned);
                $formattedRelevant = number_format($relevant);
                $this->line("Processed: {$formattedScanned} rows scanned ({$formattedRelevant} relevant records matched)...");
            }
        };

        try {
            $stats = $importService->import(
                $filePath,
                [
                    'type' => $type,
                    'dry_run' => $dryRun,
                    'batch_size' => $batchSize,
                    'force' => $force,
                ],
                $progressCallback
            );
        } catch (Throwable $e) {
            $this->newLine();
            $this->error("NPPES Bulk Import Error: " . $e->getMessage());
            return 1;
        }

        $this->newLine();
        $this->line('============================================================');
        $this->info('                   Import Statistics' . ($dryRun ? ' (Simulated)' : ''));
        $this->line('============================================================');

        $rows = [
            ['File Name', $stats['file']],
            ['Rows Scanned', number_format($stats['rows_scanned'])],
            ['Organizations Scanned', number_format($stats['organizations'])],
            ['Relevant Pharmacy Records', number_format($stats['pharmacy_rows'])],
            ['Relevant Laboratory Records', number_format($stats['laboratory_rows'])],
        ];

        if ($dryRun) {
            $rows[] = ['Would Insert', number_format($stats['would_insert'])];
            $rows[] = ['Would Update', number_format($stats['would_update'])];
        } else {
            $rows[] = ['Imported / Upserted', number_format($stats['imported'])];
        }

        $rows[] = ['Skipped Invalid Records', number_format($stats['skipped_invalid'])];

        $this->table(['Metric', 'Count'], $rows);

        if ($dryRun) {
            $this->warn('*** DRY RUN COMPLETE: NO DATABASE CHANGES WERE MADE. ***');
        } else {
            $this->info('Local NPPES directory successfully updated.');
        }

        $this->line('============================================================');
        $this->newLine();

        return 0;
    }
}
