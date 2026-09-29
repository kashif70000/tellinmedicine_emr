<?php

namespace App\Console\Commands;

use App\Models\Medicine;
use App\Models\RxnormConcept;
use App\Models\RxnormImport;
use App\Services\RxNormImporterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Exception;

class RxnormImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rxnorm:import
                            {--path= : Path to local RxNorm zip or extracted folder}
                            {--force : Force re-downloading official RxNorm dataset}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import official U.S. NLM RxNorm medicine dataset into MySQL database';

    /**
     * Execute the console command.
     */
    public function handle(RxNormImporterService $importer): int
    {
        $this->info("=================================================");
        $this->info("   RxNorm Official Medicine Importer (Phase 1)   ");
        $this->info("=================================================");

        $path = $this->option('path');
        $force = $this->option('force');

        if (!$path) {
            $defaultZipPath = storage_path('app/rxnorm/RxNorm_full_prescribe_current.zip');
            if (file_exists($defaultZipPath) && filesize($defaultZipPath) > 10000000 && !$force) {
                $this->info("Found existing RxNorm package: {$defaultZipPath} (" . round(filesize($defaultZipPath) / 1048576, 2) . " MB)");
                $path = $defaultZipPath;
            } else {
                $url = 'https://download.nlm.nih.gov/rxnorm/RxNorm_full_prescribe_current.zip';
                $this->info("Downloading official RxNorm Prescribable Content from: {$url}");
                
                $dir = dirname($defaultZipPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                $cmd = "curl.exe -L -o " . escapeshellarg($defaultZipPath) . " " . escapeshellarg($url);
                $this->info("Executing download command: {$cmd}");
                exec($cmd, $outputLines, $returnCode);

                if ($returnCode !== 0 || !file_exists($defaultZipPath) || filesize($defaultZipPath) < 10000000) {
                    $this->error("Failed to download official RxNorm data from {$url}. Download stopped.");
                    return Command::FAILURE;
                }

                $path = $defaultZipPath;
                $this->info("Successfully downloaded RxNorm package (" . round(filesize($path) / 1048576, 2) . " MB)");
            }
        }

        $this->info("Starting import from source: {$path}");
        $bar = null;

        try {
            $record = $importer->import($path, function ($message, $step, $total) use (&$bar) {
                $this->info($message);
            });

            $this->info("\n=================================================");
            $this->info("               IMPORT COMPLETED                  ");
            $this->info("=================================================");
            $this->info("Import ID:        {$record->id}");
            $this->info("Release Version:  {$record->release_version}");
            $this->info("Status:           {$record->status}");
            $this->info("Records Imported: {$record->records_imported}");

            $this->verifyImport();

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("\n[ERROR] Import failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Run database verification checks.
     */
    protected function verifyImport(): void
    {
        $this->info("\n-------------------------------------------------");
        $this->info("          POST-IMPORT VERIFICATION CHECKS        ");
        $this->info("-------------------------------------------------");

        $totalMedicines = Medicine::count();
        $uniqueRxcuis = Medicine::distinct('rxcui')->count('rxcui');
        $duplicateRxcuis = Medicine::select('rxcui', DB::raw('count(*) as count'))
            ->groupBy('rxcui')
            ->having('count', '>', 1)
            ->count();
        $missingNames = Medicine::whereNull('name')->orWhere('name', '')->count();
        $missingRxcuis = Medicine::whereNull('rxcui')->orWhere('rxcui', '')->count();

        $clinicalCount = Medicine::where('tty', 'SCD')->count();
        $brandedCount = Medicine::where('tty', 'SBD')->count();
        $ingredientCount = Medicine::whereIn('tty', ['IN', 'PIN'])->count();
        $dosageFormsCount = Medicine::whereNotNull('dosage_form')->count();
        $strengthsCount = Medicine::whereNotNull('strength')->count();
        $routesCount = Medicine::whereNotNull('route')->count();

        $latestImport = RxnormImport::latest()->first();

        $this->info("Total Records Imported:  {$totalMedicines}");
        $this->info("Unique RxCUIs:          {$uniqueRxcuis}");
        $this->info("Duplicate RxCUIs:       {$duplicateRxcuis}");
        $this->info("Records Without Name:   {$missingNames}");
        $this->info("Records Without RxCUI:  {$missingRxcuis}");
        $this->info("Clinical Drugs (SCD):   {$clinicalCount}");
        $this->info("Branded Drugs (SBD):    {$brandedCount}");
        $this->info("Ingredients (IN/PIN):   {$ingredientCount}");
        $this->info("Dosage Forms Mapped:    {$dosageFormsCount}");
        $this->info("Strengths Mapped:       {$strengthsCount}");
        $this->info("Routes Mapped:          {$routesCount}");
        $this->info("RxNorm Release Version: " . ($latestImport ? $latestImport->release_version : 'N/A'));

        $this->info("\n--- Sample Database Records Check ---");
        $samples = Medicine::where('name', 'LIKE', '%Amoxicillin%')
            ->take(5)
            ->get(['id', 'rxcui', 'name', 'generic_name', 'brand_name', 'strength', 'dosage_form', 'route']);

        foreach ($samples as $sample) {
            $this->line("RxCUI: {$sample->rxcui} | Name: {$sample->name} | Generic: {$sample->generic_name} | Strength: {$sample->strength} | Form: {$sample->dosage_form} | Route: {$sample->route}");
        }

        $this->info("-------------------------------------------------\n");
    }
}
