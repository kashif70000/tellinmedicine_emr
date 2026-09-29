<?php

namespace App\Services\Fax;

class FaxMatchService
{
    public const MIN_ACCEPTANCE_SCORE = 80;

    /**
     * Score a single local entity against an NPPES candidate.
     *
     * @param array<string, mixed> $local [name, street_address, city, state, postal_code, phone]
     * @param array<string, mixed> $candidate [npi, organization_name, other_names, practice_address, mailing_address]
     * @return array{score: int, breakdown: array<string, int>, reasons: array<int, string>, practice_fax: ?string, practice_phone: ?string, npi: string}
     */
    public function scoreCandidate(array $local, array $candidate): array
    {
        $breakdown = [
            'street_address' => 0,
            'postal_code' => 0,
            'name' => 0,
            'phone' => 0,
            'city' => 0,
        ];
        $reasons = [];

        $practiceAddr = $candidate['practice_address'] ?? [];
        $candidateStreet = $practiceAddr['address_1'] ?? '';
        $candidateCity = $practiceAddr['city'] ?? '';
        $candidateState = $practiceAddr['state'] ?? '';
        $candidateZip = $practiceAddr['postal_code'] ?? '';
        $candidatePhone = $practiceAddr['telephone_number'] ?? '';
        $candidateFax = $practiceAddr['fax_number'] ?? null;

        // If practice address has no fax, check if practice is identical to mailing
        if (!$candidateFax && !empty($candidate['mailing_address']['fax_number'])) {
            // Only allow mailing fax if the mailing address matches the local practice address
            if ($this->addressesMatch($local['street_address'] ?? '', $candidate['mailing_address']['address_1'] ?? '')) {
                $candidateFax = $candidate['mailing_address']['fax_number'];
            }
        }

        // 1. Street Address Match (+40 max)
        $streetScore = $this->calculateStreetScore($local['street_address'] ?? '', $candidateStreet);
        $breakdown['street_address'] = $streetScore;
        if ($streetScore >= 35) {
            $reasons[] = 'Exact or strong street address match';
        } elseif ($streetScore > 0) {
            $reasons[] = 'Partial street address match';
        }

        // 2. Postal Code Match (+20 max)
        $zipScore = $this->calculateZipScore($local['postal_code'] ?? '', $candidateZip);
        $breakdown['postal_code'] = $zipScore;
        if ($zipScore === 20) {
            $reasons[] = 'Exact 5-digit ZIP code match';
        }

        // 3. Organization / Brand Name Match (+20 max)
        $nameScore = $this->calculateNameScore(
            $local['name'] ?? '',
            $candidate['organization_name'] ?? '',
            $candidate['other_names'] ?? []
        );
        $breakdown['name'] = $nameScore;
        if ($nameScore >= 18) {
            $reasons[] = 'Strong organization brand match';
        } elseif ($nameScore > 0) {
            $reasons[] = 'Partial organization name match';
        }

        // 4. Phone Match (+15 max)
        $phoneScore = $this->calculatePhoneScore($local['phone'] ?? '', $candidatePhone);
        $breakdown['phone'] = $phoneScore;
        if ($phoneScore === 15) {
            $reasons[] = 'Exact telephone number match';
        }

        // 5. City Match (+5 max)
        $cityScore = $this->calculateCityScore($local['city'] ?? '', $candidateCity);
        $breakdown['city'] = $cityScore;
        if ($cityScore === 5) {
            $reasons[] = 'City match';
        }

        $totalScore = min(100, array_sum($breakdown));

        // Safety Guard for Chains (e.g. CVS, Walgreens, Quest Diagnostics, Labcorp):
        // For large chains with many branches, street address must match strongly (score >= 35)
        // to prevent false positives from assigning another branch's fax number.
        if ($this->isMajorChain($local['name'] ?? '') && $streetScore < 35) {
            $totalScore = min($totalScore, 65); // Cap below acceptance threshold
            $reasons[] = 'Chain branch street address did not match; capped score to prevent branch collision';
        }

        return [
            'score' => $totalScore,
            'breakdown' => $breakdown,
            'reasons' => $reasons,
            'practice_fax' => $this->normalizeFaxNumber($candidateFax),
            'practice_phone' => $this->normalizePhoneNumber($candidatePhone),
            'npi' => (string) ($candidate['npi'] ?? ''),
            'candidate_org' => $candidate['organization_name'] ?? '',
            'candidate_address' => trim("{$candidateStreet}, {$candidateCity}, {$candidateState} {$candidateZip}"),
        ];
    }

    /**
     * Evaluate all NPPES candidates and pick the best safe match.
     *
     * @param array<string, mixed> $local
     * @param array<int, array<string, mixed>> $candidates
     * @return array{match: ?array, status: string, notes: string, score: int}
     */
    public function findBestMatch(array $local, array $candidates): array
    {
        if (empty($candidates)) {
            return [
                'match' => null,
                'status' => 'not_found',
                'notes' => 'No NPPES organization candidates found for given search criteria',
                'score' => 0,
            ];
        }

        $scoredList = [];
        foreach ($candidates as $candidate) {
            $evaluation = $this->scoreCandidate($local, $candidate);
            $scoredList[] = $evaluation;
        }

        // Sort descending by score
        usort($scoredList, fn($a, $b) => $b['score'] <=> $a['score']);

        $top = $scoredList[0];

        // Check if top score meets minimum threshold
        if ($top['score'] < self::MIN_ACCEPTANCE_SCORE) {
            return [
                'match' => null,
                'status' => 'not_found',
                'notes' => "Top candidate scored {$top['score']}/100, which is below the safe acceptance threshold of " . self::MIN_ACCEPTANCE_SCORE,
                'score' => $top['score'],
            ];
        }

        // Ambiguity Check: If multiple candidates scored >= 80 with different NPIs and scores within 5 points
        if (count($scoredList) > 1) {
            $second = $scoredList[1];
            if ($second['score'] >= self::MIN_ACCEPTANCE_SCORE && ($top['score'] - $second['score']) <= 5 && $top['npi'] !== $second['npi']) {
                return [
                    'match' => null,
                    'status' => 'ambiguous',
                    'notes' => "Multiple high-scoring NPPES candidates found with close scores ({$top['score']} vs {$second['score']})",
                    'score' => $top['score'],
                ];
            }
        }

        // We have a strong confident match!
        $status = !empty($top['practice_fax']) ? 'found' : 'no_fax';
        $notes = implode('; ', $top['reasons']);

        return [
            'match' => $top,
            'status' => $status,
            'notes' => $notes,
            'score' => $top['score'],
        ];
    }

    /**
     * Calculate street address match score (0-40).
     */
    public function calculateStreetScore(?string $localStreet, ?string $nppesStreet): int
    {
        if (empty($localStreet) || empty($nppesStreet)) {
            return 0;
        }

        $normLocal = $this->normalizeStreetAddress($localStreet);
        $normNppes = $this->normalizeStreetAddress($nppesStreet);

        // Exact normalized match
        if ($normLocal === $normNppes) {
            return 40;
        }

        // Extract street numbers
        preg_match('/^(\d+)/', $normLocal, $localNumMatch);
        preg_match('/^(\d+)/', $normNppes, $nppesNumMatch);

        $localNum = $localNumMatch[1] ?? null;
        $nppesNum = $nppesNumMatch[1] ?? null;

        // Street number must match if both present
        if ($localNum && $nppesNum) {
            if ($localNum !== $nppesNum) {
                return 0; // Completely different street numbers -> reject
            }

            // Same street number -> compare remaining street name
            $localRest = trim(substr($normLocal, strlen($localNum)));
            $nppesRest = trim(substr($normNppes, strlen($nppesNum)));

            if ($localRest === $nppesRest) {
                return 40;
            }

            // Strip ste/fl/unit suffixes from rest for comparison
            $localBase = preg_replace('/(ste|fl)\w+$/', '', $localRest);
            $nppesBase = preg_replace('/(ste|fl)\w+$/', '', $nppesRest);

            if (!empty($localBase) && $localBase === $nppesBase) {
                return 40;
            }

            if (str_contains($localRest, $nppesRest) || str_contains($nppesRest, $localRest) ||
                (!empty($localBase) && !empty($nppesBase) && (str_contains($localBase, $nppesBase) || str_contains($nppesBase, $localBase)))) {
                return 38;
            }
        }

        // Substring / partial match
        if (str_contains($normLocal, $normNppes) || str_contains($normNppes, $normLocal)) {
            return 25;
        }

        return 0;
    }

    /**
     * Calculate ZIP code match score (0-20).
     */
    public function calculateZipScore(?string $localZip, ?string $nppesZip): int
    {
        if (empty($localZip) || empty($nppesZip)) {
            return 0;
        }

        $baseLocal = $this->extractBaseZip($localZip);
        $baseNppes = $this->extractBaseZip($nppesZip);

        if ($baseLocal && $baseNppes && $baseLocal === $baseNppes) {
            return 20;
        }

        return 0;
    }

    /**
     * Calculate organization / brand name score (0-20).
     */
    public function calculateNameScore(?string $localName, ?string $nppesName, array $otherNames = []): int
    {
        if (empty($localName) || empty($nppesName)) {
            return 0;
        }

        $normLocal = $this->normalizeOrgName($localName);
        $normNppes = $this->normalizeOrgName($nppesName);

        if ($normLocal === $normNppes) {
            return 20;
        }

        // Check if recognized brand matches
        $brandLocal = $this->extractCoreBrand($localName);
        $brandNppes = $this->extractCoreBrand($nppesName);

        if ($brandLocal && $brandNppes && $brandLocal === $brandNppes) {
            return 18;
        }

        // Check other names / DBAs
        foreach ($otherNames as $on) {
            $normOn = $this->normalizeOrgName($on);
            if ($normLocal === $normOn) {
                return 20;
            }
            $brandOn = $this->extractCoreBrand($on);
            if ($brandLocal && $brandOn && $brandLocal === $brandOn) {
                return 18;
            }
        }

        // Substring match
        if (str_contains($normLocal, $normNppes) || str_contains($normNppes, $normLocal)) {
            return 15;
        }

        return 0;
    }

    /**
     * Calculate phone match score (0-15).
     */
    public function calculatePhoneScore(?string $localPhone, ?string $nppesPhone): int
    {
        if (empty($localPhone) || empty($nppesPhone)) {
            return 0;
        }

        $digitsLocal = $this->extractTenDigitPhone($localPhone);
        $digitsNppes = $this->extractTenDigitPhone($nppesPhone);

        if ($digitsLocal && $digitsNppes && $digitsLocal === $digitsNppes) {
            return 15;
        }

        return 0;
    }

    /**
     * Calculate city match score (0-5).
     */
    public function calculateCityScore(?string $localCity, ?string $nppesCity): int
    {
        if (empty($localCity) || empty($nppesCity)) {
            return 0;
        }

        $cleanLocal = strtolower(preg_replace('/[^a-z0-9]/', '', $localCity));
        $cleanNppes = strtolower(preg_replace('/[^a-z0-9]/', '', $nppesCity));

        if ($cleanLocal === $cleanNppes || str_contains($cleanLocal, $cleanNppes) || str_contains($cleanNppes, $cleanLocal)) {
            return 5;
        }

        return 0;
    }

    /**
     * Normalize street address for comparison.
     */
    public function normalizeStreetAddress(string $address): string
    {
        $addr = strtolower($address);

        // Normalize directionals
        $addr = preg_replace('/\bnorth\b/', 'n', $addr);
        $addr = preg_replace('/\bsouth\b/', 's', $addr);
        $addr = preg_replace('/\beast\b/', 'e', $addr);
        $addr = preg_replace('/\bwest\b/', 'w', $addr);

        // Normalize street types
        $addr = preg_replace('/\b(street|st\.)\b/', 'st', $addr);
        $addr = preg_replace('/\b(avenue|ave\.)\b/', 'ave', $addr);
        $addr = preg_replace('/\b(road|rd\.)\b/', 'rd', $addr);
        $addr = preg_replace('/\b(boulevard|blvd\.)\b/', 'blvd', $addr);
        $addr = preg_replace('/\b(drive|dr\.)\b/', 'dr', $addr);
        $addr = preg_replace('/\b(lane|ln\.)\b/', 'ln', $addr);
        $addr = preg_replace('/\b(highway|hwy\.)\b/', 'hwy', $addr);

        // Normalize unit/suite/floor/building
        $addr = preg_replace('/#\s*(\w+)/', 'ste$1', $addr);
        $addr = preg_replace('/\b(suite|ste\.|unit|apt|apartment)\s*(\w+)\b/', 'ste$2', $addr);
        $addr = preg_replace('/\b(floor|fl\.)\s*(\w+)\b/', 'fl$2', $addr);

        return preg_replace('/[^a-z0-9]/', '', $addr);
    }

    /**
     * Normalize organization name (lowercase, remove legal suffixes like LLC, Inc, Corp).
     */
    public function normalizeOrgName(string $name): string
    {
        $clean = strtolower($name);
        $clean = preg_replace('/\b(llc|inc|corp|corporation|co|ltd|pllc|pc|pharma|pharmacy|pharmacies|laboratory|laboratories|diagnostic|diagnostics)\b/i', '', $clean);
        $clean = preg_replace('/#\d+/', '', $clean);

        return preg_replace('/[^a-z0-9]/', '', $clean);
    }

    /**
     * Extract core brand keyword.
     */
    public function extractCoreBrand(string $name): string
    {
        $lower = strtolower($name);

        if (str_contains($lower, 'cvs')) return 'cvs';
        if (str_contains($lower, 'walgreens') || str_contains($lower, 'walgreen')) return 'walgreens';
        if (str_contains($lower, 'quest')) return 'quest';
        if (str_contains($lower, 'labcorp') || str_contains($lower, 'laboratory corporation')) return 'labcorp';
        if (str_contains($lower, 'walmart')) return 'walmart';
        if (str_contains($lower, 'stop & shop') || str_contains($lower, 'stop and shop')) return 'stopandshop';
        if (str_contains($lower, 'shaw')) return 'shaws';
        if (str_contains($lower, 'rite aid')) return 'riteaid';

        return $this->normalizeOrgName($name);
    }

    /**
     * Check if entity is a major multi-location national chain.
     */
    public function isMajorChain(string $name): bool
    {
        $lower = strtolower($name);
        $chains = ['cvs', 'walgreens', 'quest', 'labcorp', 'walmart', 'stop & shop', 'rite aid', 'shaw'];

        foreach ($chains as $c) {
            if (str_contains($lower, $c)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize phone/fax to E.164 (+1XXXXXXXXXX) format.
     */
    public function normalizeFaxNumber(?string $rawFax): ?string
    {
        if (empty($rawFax)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $rawFax);

        // US standard 10-digit number
        if (strlen($digits) === 10) {
            return '+1' . $digits;
        }

        // 11 digits starting with 1
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return '+' . $digits;
        }

        // Invalid or malformed
        if (strlen($digits) < 7 || strlen($digits) > 15) {
            return null;
        }

        return '+' . $digits;
    }

    /**
     * Normalize phone number to canonical E.164 format.
     */
    public function normalizePhoneNumber(?string $rawPhone): ?string
    {
        return $this->normalizeFaxNumber($rawPhone);
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
        if (strlen($digits) >= 5) {
            return substr($digits, 0, 5);
        }

        return null;
    }

    /**
     * Extract 10-digit phone digits.
     */
    public function extractTenDigitPhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        return (strlen($digits) === 10) ? $digits : null;
    }

    /**
     * Check if two addresses match.
     */
    public function addressesMatch(?string $addr1, ?string $addr2): bool
    {
        if (empty($addr1) || empty($addr2)) {
            return false;
        }

        return $this->normalizeStreetAddress($addr1) === $this->normalizeStreetAddress($addr2);
    }
}
