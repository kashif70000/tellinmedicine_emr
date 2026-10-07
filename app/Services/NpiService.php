<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class NpiService
{
    public function lookup(string $npi): array
    {
        $response = Http::timeout(15)
            ->get('https://npiregistry.cms.hhs.gov/api/', [
                'version' => '2.1',
                'number' => $npi,
            ]);

        if ($response->failed()) {
            throw new Exception('Unable to connect to NPPES.');
        }

        $data = $response->json();

        if (($data['result_count'] ?? 0) === 0) {
            throw new Exception('No provider found for this NPI.');
        }

        return $data['results'][0];
    }
}