<?php

namespace App\Services\Fax;

use App\Models\Pharmacy;
use Illuminate\Database\Eloquent\Builder;

class PharmacyFaxEnrichmentService
{
    public function __construct(
        protected HealthcareEntityFaxEnrichmentService $coreService
    ) {}

    /**
     * Run enrichment over pharmacies matching criteria.
     *
     * @param array{city?: ?string, state?: ?string, limit?: ?int, only_missing?: bool, retry_not_found?: bool, dry_run?: bool} $options
     * @return array{stats: array<string, int>, results: array<int, array<string, mixed>>}
     */
    public function enrich(array $options = []): array
    {
        $query = Pharmacy::query();

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

        $pharmacies = $query->orderBy('id')->get();

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

        foreach ($pharmacies as $pharmacy) {
            $stats['checked']++;

            $res = $this->coreService->enrichEntity($pharmacy, $dryRun);
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
                    if (!empty($pharmacy->fax)) {
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
