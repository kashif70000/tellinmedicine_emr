<?php

namespace App\Services;

interface MassachusettsLicenseSource
{
    /**
     * Return an authoritative normalized record, or null when unavailable.
     * Expected keys: license_number, full_name, license_type, status, issue_date, expiration_date.
     */
    public function find(string $licenseType, string $licenseNumber): ?array;

    public function sourceName(): string;
}