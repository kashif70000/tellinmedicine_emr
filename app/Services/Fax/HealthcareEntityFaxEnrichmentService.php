<?php

namespace App\Services\Fax;

use App\Repositories\NppesDirectoryRepository;
use App\Services\Nppes\NppesClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class HealthcareEntityFaxEnrichmentService
{
    public function __construct(
        protected NppesClient $nppesClient,
        protected NppesDirectoryRepository $directoryRepo,
        protected FaxMatchService $matchService
    ) {}

    /**
     * Enrich a single healthcare entity (Pharmacy or Laboratory) with NPPES Fax & NPI data.
     *
     * @param Model $entity
     * @param bool $dryRun
     * @return array<string, mixed>
     */
    public function enrichEntity(Model $entity, bool $dryRun = false): array
    {
        $entityClass = class_basename($entity);
        $localData = [
            'name' => $entity->name,
            'street_address' => $entity->street_address,
            'city' => $entity->city,
            'state' => $entity->state,
            'postal_code' => $entity->postal_code,
            'phone' => $entity->phone,
        ];

        $result = [
            'entity_id' => $entity->id,
            'entity_name' => $entity->name,
            'entity_address' => trim("{$entity->street_address}, {$entity->city}, {$entity->state} {$entity->postal_code}"),
            'action' => 'checked',
            'status' => 'pending',
            'npi' => null,
            'fax' => null,
            'match_score' => 0,
            'candidate_org' => null,
            'candidate_address' => null,
            'notes' => null,
            'dry_run' => $dryRun,
        ];

        try {
            $source = config('nppes.source', 'bulk');

            // 1. Query NPPES Registry (Local Bulk Directory or Live API)
            if ($source === 'bulk') {
                if ($this->directoryRepo->isEmpty()) {
                    throw new RuntimeException("NPPES local bulk directory is empty. Run nppes:import first.");
                }

                $candidates = $this->directoryRepo->search(
                    $entity->name,
                    $entity->city,
                    $entity->state,
                    $entity->postal_code
                );
            } else {
                $candidates = $this->nppesClient->search(
                    $entity->name,
                    $entity->city,
                    $entity->state,
                    $entity->postal_code
                );
            }

            if (empty($candidates) && $source === 'api' && $this->nppesClient->hadNetworkFailure()) {
                $status = 'lookup_failed';
                $score = 0;
                $notes = 'NPPES network/server request failed after retries';
                $match = null;
            } else {
                // 2. Score Candidates & Pick Best Match
                $matchResult = $this->matchService->findBestMatch($localData, $candidates);
                $status = $matchResult['status'];
                $score = $matchResult['score'];
                $notes = $matchResult['notes'];
                $match = $matchResult['match'];
            }

            $result['status'] = $status;
            $result['match_score'] = $score;
            $result['notes'] = $notes;

            if ($match) {
                $npi = $match['npi'];
                $fax = $match['practice_fax'];
                $result['npi'] = $npi;
                $result['fax'] = $fax;
                $result['candidate_org'] = $match['candidate_org'] ?? null;
                $result['candidate_address'] = $match['candidate_address'] ?? null;

                // 3. Evaluate Safe Updates
                $updates = $this->determineSafeUpdates($entity, $npi, $fax, $status, $score, $notes);

                if (!$dryRun && !empty($updates)) {
                    $entity->update($updates);
                    $result['action'] = 'updated';
                } else {
                    $result['action'] = $dryRun ? 'dry_run_evaluated' : 'skipped';
                }
            } else {
                // No match or ambiguous or lookup_failed
                if (!$dryRun) {
                    $entity->update([
                        'fax_lookup_status' => $status,
                        'fax_match_score' => $score,
                        'fax_lookup_notes' => $notes,
                        'fax_last_checked_at' => now(),
                    ]);
                }
                $result['action'] = $dryRun ? 'dry_run_evaluated' : 'status_updated';
            }

            Log::info("NPPES Fax Enrichment for {$entityClass} #{$entity->id} ({$entity->name})", [
                'status' => $result['status'],
                'score' => $result['match_score'],
                'npi' => $result['npi'],
                'fax' => $result['fax'],
                'dry_run' => $dryRun,
            ]);

            return $result;
        } catch (Throwable $e) {
            $isEmptyDir = str_contains($e->getMessage(), 'NPPES local bulk directory is empty');

            Log::error("NPPES Fax Enrichment failed for {$entityClass} #{$entity->id}: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            $result['status'] = 'lookup_failed';
            $result['notes'] = $e->getMessage();
            $result['action'] = 'failed';

            // Requirement 26: Do not modify fax lookup statuses if local bulk directory is empty
            if (!$dryRun && !$isEmptyDir) {
                try {
                    $entity->update([
                        'fax_lookup_status' => 'lookup_failed',
                        'fax_lookup_notes' => substr($e->getMessage(), 0, 500),
                        'fax_last_checked_at' => now(),
                    ]);
                } catch (Throwable) { }
            }

            return $result;
        }
    }

    /**
     * Determine safe database updates for the entity.
     *
     * @param Model $entity
     * @param string|null $npi
     * @param string|null $fax
     * @param string $status
     * @param int $score
     * @param string $notes
     * @return array<string, mixed>
     */
    public function determineSafeUpdates(
        Model $entity,
        ?string $npi,
        ?string $fax,
        string $status,
        int $score,
        string $notes
    ): array {
        $updates = [
            'fax_lookup_status' => $status,
            'fax_match_score' => $score,
            'fax_lookup_notes' => $notes,
            'fax_last_checked_at' => now(),
        ];

        // 1. Safe NPI update (validate 10 digits)
        if (!empty($npi) && preg_match('/^\d{10}$/', $npi)) {
            $updates['npi'] = $npi;
        }

        // 2. Safe Fax update rules:
        // Rule A: Never overwrite a manually entered or verified fax
        if ($entity->fax_source === 'manual' || $entity->fax_verified_at !== null) {
            unset($updates['fax_lookup_status']);
            return $updates;
        }

        // Rule B: If incoming NPPES fax is null, do NOT overwrite an existing non-null fax
        if (empty($fax)) {
            return $updates;
        }

        // Rule C: If existing fax is non-null and differs from incoming NPPES fax, mark for review / ambiguous
        if (!empty($entity->fax) && $entity->fax !== $fax) {
            $updates['fax_lookup_status'] = 'ambiguous';
            $updates['fax_lookup_notes'] = "Existing fax ({$entity->fax}) differs from newly discovered NPPES fax ({$fax}); preserved existing";
            return $updates;
        }

        // Safe to set NPPES fax
        $updates['fax'] = $fax;
        $updates['fax_source'] = 'nppes';

        return $updates;
    }
}
