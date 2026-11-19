<?php

namespace App\Services;

interface GmcUkRegistrationSource
{
    /**
     * Return candidate GMC doctor records matching a registration number.
     * Each candidate should include at least: full_name, gmc_number, profile_url, registration_status, licence_status.
     */
    public function find(string $gmcNumber): ?array;

    public function sourceName(): string;
}
