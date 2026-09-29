<?php

namespace App\Repositories;

use App\Models\NppesHealthcareLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class NppesDirectoryRepository
{
    /**
     * Search local NPPES directory for matching healthcare organization candidates.
     *
     * @param string $organizationName
     * @param string|null $city
     * @param string|null $state
     * @param string|null $postalCode
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function search(
        string $organizationName,
        ?string $city = null,
        ?string $state = null,
        ?string $postalCode = null,
        int $limit = 15
    ): array {
        $cleanState = $state ? strtoupper(trim($state)) : null;
        $cleanCity = $city ? trim($city) : null;
        $baseZip = $this->extractBaseZip($postalCode);
        $coreBrand = $this->extractCoreBrandName($organizationName);
        $cleanName = $this->cleanOrganizationNameForSearch($organizationName);

        $candidates = collect();

        // Strategy 1: State + Postal Code (base 5-digit ZIP) + Brand / Name
        if (!empty($cleanState) && !empty($baseZip)) {
            $query = NppesHealthcareLocation::query()
                ->where('state', $cleanState)
                ->where('postal_code', 'LIKE', $baseZip . '%');

            $this->applyNameFilter($query, $coreBrand, $cleanName);

            $results = $query->limit($limit)->get();
            if ($results->isNotEmpty()) {
                return $this->formatCandidates($results);
            }
        }

        // Strategy 2: State + City + Brand / Name
        if (!empty($cleanState) && !empty($cleanCity)) {
            $query = NppesHealthcareLocation::query()
                ->where('state', $cleanState)
                ->where('city', 'LIKE', $cleanCity . '%');

            $this->applyNameFilter($query, $coreBrand, $cleanName);

            $results = $query->limit($limit)->get();
            if ($results->isNotEmpty()) {
                return $this->formatCandidates($results);
            }
        }

        // Strategy 3: Postal Code + State (without strict name filter if brand was not found)
        if (!empty($cleanState) && !empty($baseZip)) {
            $results = NppesHealthcareLocation::query()
                ->where('state', $cleanState)
                ->where('postal_code', 'LIKE', $baseZip . '%')
                ->limit($limit)
                ->get();

            if ($results->isNotEmpty()) {
                return $this->formatCandidates($results);
            }
        }

        // Strategy 4: State + Brand keyword fallback
        if (!empty($cleanState) && !empty($coreBrand)) {
            $results = NppesHealthcareLocation::query()
                ->where('state', $cleanState)
                ->where(function (Builder $q) use ($coreBrand) {
                    $q->where('organization_name', 'LIKE', '%' . $coreBrand . '%')
                      ->orWhere('other_organization_name', 'LIKE', '%' . $coreBrand . '%');
                })
                ->limit($limit)
                ->get();

            if ($results->isNotEmpty()) {
                return $this->formatCandidates($results);
            }
        }

        return [];
    }

    /**
     * Apply name or brand filtering to an Eloquent query.
     */
    protected function applyNameFilter(Builder $query, ?string $coreBrand, ?string $cleanName): void
    {
        $query->where(function (Builder $q) use ($coreBrand, $cleanName) {
            $applied = false;

            if (!empty($coreBrand)) {
                $q->where('organization_name', 'LIKE', '%' . $coreBrand . '%')
                  ->orWhere('other_organization_name', 'LIKE', '%' . $coreBrand . '%');
                $applied = true;
            }

            if (!empty($cleanName) && $cleanName !== $coreBrand) {
                $method = $applied ? 'orWhere' : 'where';
                $q->$method('organization_name', 'LIKE', '%' . $cleanName . '%')
                  ->orWhere('other_organization_name', 'LIKE', '%' . $cleanName . '%');
            }
        });
    }

    /**
     * Format Eloquent location models into structured candidate array expected by FaxMatchService.
     *
     * @param Collection<int, NppesHealthcareLocation> $locations
     * @return array<int, array<string, mixed>>
     */
    public function formatCandidates(Collection $locations): array
    {
        $formatted = [];
        $seenNpis = [];

        foreach ($locations as $loc) {
            if (isset($seenNpis[$loc->npi])) {
                continue;
            }
            $seenNpis[$loc->npi] = true;

            $otherNames = [];
            if (!empty($loc->other_organization_name)) {
                $otherNames[] = $loc->other_organization_name;
            }

            $formatted[] = [
                'npi' => (string) $loc->npi,
                'organization_name' => $loc->organization_name ?? '',
                'other_names' => $otherNames,
                'practice_address' => [
                    'address_purpose' => 'LOCATION',
                    'address_1' => $loc->address_line_1,
                    'address_2' => $loc->address_line_2,
                    'city' => $loc->city,
                    'state' => $loc->state,
                    'postal_code' => $loc->postal_code,
                    'telephone_number' => $loc->phone,
                    'fax_number' => $loc->fax,
                ],
                'mailing_address' => null, // Practice location only
                'taxonomies' => [
                    [
                        'code' => $loc->taxonomy_code,
                        'desc' => $loc->taxonomy_description,
                        'primary' => true,
                    ],
                ],
                'enumeration_date' => $loc->enumeration_date ? $loc->enumeration_date->format('Y-m-d') : null,
                'last_updated' => $loc->last_update_date ? $loc->last_update_date->format('Y-m-d') : null,
                'raw' => $loc->toArray(),
            ];
        }

        return $formatted;
    }

    /**
     * Check if the local directory is completely empty.
     */
    public function isEmpty(): bool
    {
        return NppesHealthcareLocation::query()->doesntExist();
    }

    /**
     * Return total number of records in local directory.
     */
    public function count(): int
    {
        return NppesHealthcareLocation::query()->count();
    }

    /**
     * Extract 5-digit base ZIP code.
     */
    public function extractBaseZip(?string $postalCode): ?string
    {
        if (!$postalCode) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $postalCode);
        return (strlen($digits) >= 5) ? substr($digits, 0, 5) : null;
    }

    /**
     * Clean organization name for search.
     */
    public function cleanOrganizationNameForSearch(string $name): string
    {
        $clean = preg_replace('/#\d+/', '', $name);
        $clean = preg_replace('/\bste\s*\d+\b/i', '', $clean);
        $clean = preg_replace('/[^\w\s]/', ' ', $clean);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        return substr($clean, 0, 100);
    }

    /**
     * Extract core recognized brand keyword.
     */
    public function extractCoreBrandName(string $name): string
    {
        $lower = strtolower($name);

        if (str_contains($lower, 'cvs')) return 'CVS';
        if (str_contains($lower, 'walgreens') || str_contains($lower, 'walgreen')) return 'WALGREENS';
        if (str_contains($lower, 'quest')) return 'QUEST';
        if (str_contains($lower, 'labcorp') || str_contains($lower, 'laboratory corporation')) return 'LABORATORY CORPORATION';
        if (str_contains($lower, 'walmart')) return 'WALMART';
        if (str_contains($lower, 'stop & shop') || str_contains($lower, 'stop and shop')) return 'STOP & SHOP';
        if (str_contains($lower, 'shaw')) return "SHAW'S";
        if (str_contains($lower, 'rite aid')) return 'RITE AID';

        return $this->cleanOrganizationNameForSearch($name);
    }
}
