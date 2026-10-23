<?php

namespace App\Services;

interface GmcUkRegistrationSource
{
    /**
     * Return a list of candidate GMC doctor records.
     * Each candidate should include at least: full_name, gmc_number, profile_url, registration_status, licence_status.
     */
    public function find(string $fullName, ?string $gmcNumber = null): ?array;

    public function sourceName(): string;
}
