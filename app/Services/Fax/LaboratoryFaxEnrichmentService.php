<?php

namespace App\Services\Fax;

use App\Models\Laboratory;
use Illuminate\Database\Eloquent\Builder;

class LaboratoryFaxEnrichmentService
{
    public function __construct(
        protected HealthcareEntityFaxEnrichmentService $coreService
    ) {}

    /**
     * Run enrichment over laboratories matching criteria.
     *
     * @param array{city?: ?string, state?: ?string, limit?: ?int, only_missing?: bool, retry_not_found?: bool, dry_run?: bool} $options
     * @return array{stats: array<string, int>, results: array<int, array<string, mixed>>}
     */
    public function enrich(array $options = []): array
    {
        $query = Laboratory::query();

        if (!empty($options['city'])) {
            $query->where('city', 'LIKE', '%' . trim($options['city']) . '%');
        }

        if (!empty($options['state'])) {
            $query->where('state', strtoupper(trim($options['state'])));
        }

        if (!empty($options['only_missing'])) {
            $query->where(function (Builder $q) {
                $q->whereNull('fax_lookup_status')
                  ->orWhere('fax_lookup_status', 'pending');
            });
        } elseif (empty($options['retry_not_found'])) {
            $query->where(function (Builder $q) {
                $q->whereNull('fax_lookup_status')
                  ->orWhereIn('fax_lookup_status', ['pending', 'lookup_failed']);
            });
        }

        if (!empty($options['limit']) && (int) $options['limit'] > 0) {
            $query->limit((int) $options['limit']);
        }

        $laboratories = $query->orderBy('id')->get();

        $stats = [
            'checked' => 0,
            'matched' => 0,
            'fax_found' => 0,
            'no_fax' => 0,
            'not_found' => 0,
            'ambiguous' => 0,
            'already_enriched' => 0,
            'failed' => 0,
        ];

        $results = [];
        $dryRun = !empty($options['dry_run']);

        foreach ($laboratories as $lab) {
            $stats['checked']++;

            $res = $this->coreService->enrichEntity($lab, $dryRun);
            $results[] = $res;

            switch ($res['status']) {
                case 'found':
                    $stats['matched']++;
                    $stats['fax_found']++;
                    break;
                case 'no_fax':
                    $stats['matched']++;
                    $stats['no_fax']++;
                    break;
                case 'not_found':
                    $stats['not_found']++;
                    break;
                case 'ambiguous':
                    $stats['ambiguous']++;
                    break;
                case 'lookup_failed':
                    $stats['failed']++;
                    break;
                default:
                    if (!empty($lab->fax)) {
                        $stats['already_enriched']++;
                    }
                    break;
            }
        }

        return [
            'stats' => $stats,
            'results' => $results,
        ];
    }
}
