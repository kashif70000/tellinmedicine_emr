<?php

namespace App\Services;

use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use config\services;

class GmcUkHtmlSource implements GmcUkRegistrationSource
{
    public function find(string $fullName, ?string $gmcNumber = null): ?array
    {
        $name = trim($fullName);
        if ($name === '') {
            return [];
        }

        $url = $this->searchUrl($name);

        try {
              $response = Http::withHeaders([
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/jxl,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Encoding' => 'gzip, deflate, br, zstd',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Cookie' => '' . config('services.gmc_uk.cookie', ''),

            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36',

            'Sec-CH-UA' => '"Google Chrome";v="155", "Chromium";v="155", "Not(A:Brand";v="24"',
            'Sec-CH-UA-Arch' => '"x86"',
            'Sec-CH-UA-Bitness' => '"64"',
            'Sec-CH-UA-Full-Version' => '"155.0.8059.85"',
            'Sec-CH-UA-Full-Version-List' => '"Google Chrome";v="155.0.8059.85", "Chromium";v="155.0.8059.85", "Not(A:Brand";v="24.0.0.0"',
            'Sec-CH-UA-Mobile' => '?0',
            'Sec-CH-UA-Model' => '""',
            'Sec-CH-UA-Platform' => '"Windows"',
            'Sec-CH-UA-Platform-Version' => '"10.0.0"',

            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'same-origin',

            'Upgrade-Insecure-Requests' => '1',
            'Priority' => 'u=0, i',
        ])
                ->connectTimeout(5)
                ->timeout(12)
                ->get($url);
        } catch (Throwable $exception) {
            Log::warning('GMC register request could not be completed.', [
                'exception' => $exception::class,
            ]);

            return null;
        }

        if ($response->failed()) {
            $body = strtolower((string) $response->body());
            $responseHint = str_contains($body, 'cloudflare')
                ? 'cloudflare_page'
                : (str_contains($body, 'access denied') ? 'access_denied_page' : 'other_error_page');

            Log::warning('GMC register request returned an unsuccessful response.', [
                'status' => $response->status(),
                'server' => $response->header('Server'),
                'content_type' => $response->header('Content-Type'),
                'cf_ray' => $response->header('CF-RAY'),
                'response_hint' => $responseHint,
            ]);

            return null;
        }

        $html = (string) $response->body();
        if (trim($html) === '') {
            Log::warning('GMC register returned an empty successful response.', [
                'status' => $response->status(),
                'content_type' => $response->header('Content-Type'),
                'cf_ray' => $response->header('CF-RAY'),
            ]);

            return null;
        }

        if (preg_match('/your search (?:hasn.t|has not) returned any results/i', html_entity_decode(strip_tags($html)))) {
            Log::info('GMC register search returned no results.', [
                'status' => $response->status(),
                'cf_ray' => $response->header('CF-RAY'),
            ]);

            return [];
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        if (! $loaded) {
            Log::warning('GMC register response could not be parsed as HTML.', [
                'status' => $response->status(),
                'content_type' => $response->header('Content-Type'),
                'cf_ray' => $response->header('CF-RAY'),
                'body_length' => strlen($html),
            ]);

            return null;
        }

        $xpath = new DOMXPath($doc);
        $nodes = $xpath->query('//a[@href]');
        if (! $nodes || $nodes->count() === 0) {
            Log::warning('GMC register HTML contained no links for result extraction.', [
                'status' => $response->status(),
                'content_type' => $response->header('Content-Type'),
                'cf_ray' => $response->header('CF-RAY'),
                'body_length' => strlen($html),
            ]);

            return null;
        }

        $candidates = [];
        foreach ($nodes as $node) {
            $href = trim((string) $node->getAttribute('href'));
            if (! $this->isTrustedProfileUrl($href)) {
                continue;
            }

            $containerText = $this->containerText($node);
            if ($containerText === '') {
                continue;
            }

            $record = [
                'full_name' => $this->extractName($containerText, $node),
                'profile_url' => $this->normalizeProfileUrl($href),
                'gmc_number' => $this->extractGmcNumberFromProfileUrl($href),
                'registration_status' => $this->extractRegistrationStatus($containerText),
                'licence_status' => $this->extractLicenceStatus($containerText),
                'source_url' => $this->searchUrl($name),
                'raw_text' => $containerText,
            ];

            if ($record['full_name'] === '' && $record['gmc_number'] === '' && $record['profile_url'] === '') {
                continue;
            }

            $candidates[] = $record;
        }

        if ($candidates === []) {
            Log::warning('GMC register HTML contained no extractable official profile records.', [
                'status' => $response->status(),
                'content_type' => $response->header('Content-Type'),
                'cf_ray' => $response->header('CF-RAY'),
                'link_count' => $nodes->count(),
                'body_length' => strlen($html),
            ]);
        }

        $nameCandidates = $this->filterByName($candidates, $name);
        Log::info('GMC register HTML response was parsed.', [
            'status' => $response->status(),
            'content_type' => $response->header('Content-Type'),
            'cf_ray' => $response->header('CF-RAY'),
            'link_count' => $nodes->count(),
            'profile_candidate_count' => count($candidates),
            'name_match_count' => count($nameCandidates),
            'body_length' => strlen($html),
        ]);

        if ($candidates === []) {
            return null;
        }

        return $nameCandidates;
    }

    public function sourceName(): string
    {
        return 'GMC Register Search';
    }

    private function searchUrl(string $fullName): string
    {
        return 'https://www.gmc-uk.org/registrants?text=' . urlencode(trim($fullName)) . '&allProfessions=true';
    }

    private function isTrustedProfileUrl(string $href): bool
    {
        $href = trim($href);
        if ($href === '') {
            return false;
        }

        $normalized = strtolower($href);
        if (str_starts_with($normalized, 'javascript:') || str_starts_with($normalized, '#')) {
            return false;
        }

        $url = $this->normalizeProfileUrl($href);
        if ($url === '') {
            return false;
        }

        return preg_match('/^https?:\/\/(?:www\.)?gmc-uk\.org\/registrants\/\d{7}\/?(?:[?#].*)?$/i', $url) === 1;
    }

    private function normalizeProfileUrl(string $href): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $href)) {
            return $href;
        }

        if (preg_match('/^\/\//', $href)) {
            return 'https:' . $href;
        }

        if (str_starts_with($href, '/')) {
            return 'https://www.gmc-uk.org' . $href;
        }

        return '';
    }

    private function containerText(DOMNode $node): string
    {
        $parts = [];
        $current = $node;
        $limit = 0;

        while ($current && $limit < 8) {
            $text = trim((string) $current->textContent);
            if ($text !== '') {
                $parts[] = preg_replace('/\s+/', ' ', $text) ?? $text;
            }
            $current = $current->parentNode;
            $limit++;
        }

        return trim(implode(' ', $parts));
    }

    private function extractName(string $text, DOMNode $node): string
    {
        $nodeText = trim((string) $node->textContent);
        if ($nodeText !== '') {
            $text = $nodeText;
        }

        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = preg_replace('/\b(?:GMC|Registration|No\.?|Number)\b.*$/i', '', $text);
        $text = trim($text, " \t\n\r|,-");

        if ($text === '') {
            return '';
        }

        if (preg_match('/([A-Z][A-Za-z\'\.-]+(?:\s+[A-Z][A-Za-z\'\.-]+)+)/', $text, $matches)) {
            return trim($matches[1]);
        }

        return $text;
    }

    private function extractGmcNumber(string $text): string
    {
        if (preg_match('/(?:GMC|registration(?:\s+No\.?|\s+no\.?|\s+number)?|gmc\s*no\.?|gmc\s*number)[^\d]{0,20}(\d{5,})/i', $text, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/\b(\d{5,})\b/', $text, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    private function extractGmcNumberFromProfileUrl(string $href): string
    {
        return preg_match('~/registrants/(\d{7})/?(?:[?#].*)?$~i', $href, $matches)
            ? $matches[1]
            : '';
    }

    private function extractRegistrationStatus(string $text): ?string
    {
        $normalizedText = strtolower($text);
        foreach (['not registered', 'full registration', 'provisional', 'registered', 'removed', 'suspended', 'inactive', 'limited', 'temporary'] as $status) {
            if (str_contains($normalizedText, $status)) {
                return ucfirst($status);
            }
        }

        return null;
    }

    private function extractLicenceStatus(string $text): ?string
    {
        $normalizedText = strtolower($text);
        foreach (['no licence to practise', 'not licensed', 'no licence', 'licence suspended', 'licence revoked'] as $status) {
            if (str_contains($normalizedText, $status)) {
                return ucfirst($status);
            }
        }

        foreach (['licence to practise', 'license to practise', 'licence to practice', 'license to practice'] as $status) {
            if (str_contains($normalizedText, $status)) {
                return 'Licensed to practise';
            }
        }

        foreach (['inactive', 'active', 'expired', 'suspended', 'removed from register', 'removed', 'licensed'] as $status) {
            if (str_contains($normalizedText, $status)) {
                return ucfirst($status);
            }
        }

        return null;
    }

    private function filterByName(array $candidates, string $name): array
    {
        if ($candidates === []) {
            return [];
        }

        return array_values(array_filter($candidates, function ($candidate) use ($name) {
            return $this->namesMatch($name, (string) ($candidate['full_name'] ?? ''));
        }));
    }

    private function namesMatch(string $submittedName, string $officialName): bool
    {
        $submittedParts = $this->nameParts($submittedName);
        $officialParts = $this->nameParts($officialName);

        return $submittedParts !== [] && array_diff($submittedParts, $officialParts) === [];
    }

    private function nameParts(string $value): array
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
        $parts = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique($parts ?: []));
    }

    private function normalizeName(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9]/', '', $value);

        return $value;
    }

    private function normalizeNumeric(string $value): string
    {
        return preg_replace('/\D+/', '', trim($value)) ?? '';
    }
}
