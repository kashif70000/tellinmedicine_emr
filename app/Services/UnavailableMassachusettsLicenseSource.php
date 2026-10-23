<?php

namespace App\Services;

use App\Services\MassachusettsLicenseSource;

/** Fallback used when no automated source is configured. */
class UnavailableMassachusettsLicenseSource implements MassachusettsLicenseSource
{
    public function find(string $licenseType, string $licenseNumber): ?array
    {
        return null;
    }

    public function sourceName(): string
    {
        return 'Massachusetts Board of Registration in Medicine';
    }
}