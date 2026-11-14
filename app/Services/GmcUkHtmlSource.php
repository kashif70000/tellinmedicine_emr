<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use config\services;

class GmcUkHtmlSource implements GmcUkRegistrationSource
{
    public function find(string $gmcNumber): ?array
    {
        $number = $this->normalizeNumeric($gmcNumber);
        if ($number === '') {
            return [];
        }

        $url = $this->profileUrl($number);

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
        $profileNumber = $this->elementText($xpath, '//*[@id="gmcNumberId"]');
        $fullName = $this->elementText($xpath, '//*[@id="registrantNameId"]');
        if ($profileNumber === '' || $fullName === '') {
            Log::warning('GMC register profile page did not contain registrant details.', [
                'status' => $response->status(),
                'content_type' => $response->header('Content-Type'),
                'cf_ray' => $response->header('CF-RAY'),
                'body_length' => strlen($html),
            ]);

            return null;
        }

        $normalizedProfileNumber = $this->normalizeNumeric($profileNumber);
        if ($normalizedProfileNumber !== $number) {
            Log::info('GMC register profile number did not match the requested number.', [
                'cf_ray' => $response->header('CF-RAY'),
            ]);

            return [];
        }

        $statusText = $this->elementText($xpath, '//*[@data-simpletooltip-content-id="registration-status"]');
        $record = [
            'full_name' => $fullName,
            'profile_url' => $url,
            'gmc_number' => $normalizedProfileNumber,
            'registration_status' => $this->extractRegistrationStatus($statusText),
            'licence_status' => $this->extractLicenceStatus($statusText),
            'source_url' => $url,
        ];

        Log::info('GMC register HTML response was parsed.', [
            'status' => $response->status(),
            'content_type' => $response->header('Content-Type'),
            'cf_ray' => $response->header('CF-RAY'),
            'profile_candidate_count' => 1,
            'number_match_count' => 1,
            'body_length' => strlen($html),
        ]);

        return [$record];
    }

    public function sourceName(): string
    {
        return 'GMC Register Search';
    }

    private function profileUrl(string $gmcNumber): string
    {
        return 'https://www.gmc-uk.org/registrants/' . urlencode($gmcNumber);
    }

    private function elementText(DOMXPath $xpath, string $query): string
    {
        $nodes = $xpath->query($query);
        $text = $nodes && $nodes->length > 0
            ? trim((string) $nodes->item(0)?->textContent)
            : '';

        return preg_replace('/\s+/', ' ', $text) ?? $text;
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

    private function normalizeNumeric(string $value): string
    {
        return preg_replace('/\D+/', '', trim($value)) ?? '';
    }
}
