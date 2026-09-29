<?php

namespace App\Services\Contracts;

interface PharmacyScraperInterface
{
    /**
     * Search and scrape pharmacy listings for the specified city and state.
     *
     * @param string $city
     * @param string $state
     * @param int|null $limit
     * @return array<int, array<string, mixed>>
     */
    public function search(string $city, string $state, ?int $limit = null): array;
}
