<?php

namespace Tests\Feature;

use App\Services\GmcUkHtmlSource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GmcUkHtmlSourceTest extends TestCase
{
    public function test_gmc_no_results_page_returns_no_records_instead_of_navigation_links(): void
    {
        Http::fake([
            'www.gmc-uk.org/registrants*' => Http::response(
                '<html><body><a href="/privacy-and-cookies/cookies">Cookies we use</a><a href="/">Search our registers</a><p>Your search hasn\'t returned any results.</p></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
            ),
        ]);

        $records = (new GmcUkHtmlSource())->find('1234567');

        $this->assertSame([], $records);
    }

    public function test_exact_gmc_number_profile_is_parsed(): void
    {
        Http::fake([
            'www.gmc-uk.org/registrants*' => Http::response(
                $this->profileHtml('1234567', 'Alice Smith', 'Registered with a licence to practise'),
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
            ),
        ]);

        $records = (new GmcUkHtmlSource())->find('1234567');

        $this->assertCount(1, $records);
        $this->assertSame('Alice Smith', $records[0]['full_name']);
        $this->assertSame('1234567', $records[0]['gmc_number']);
        $this->assertSame('https://www.gmc-uk.org/registrants/1234567', $records[0]['profile_url']);
        $this->assertArrayNotHasKey('raw_text', $records[0]);
        $this->assertSame('Registered', $records[0]['registration_status']);
        $this->assertSame('Licensed to practise', $records[0]['licence_status']);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/registrants/1234567'));
    }

    public function test_profile_with_a_different_gmc_number_is_not_returned(): void
    {
        Http::fake([
            'www.gmc-uk.org/registrants*' => Http::response(
                $this->profileHtml('1234567', 'Alice Smith', 'Registered with a licence to practise'),
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
            ),
        ]);

        $records = (new GmcUkHtmlSource())->find('7654321');

        $this->assertSame([], $records);
    }

    public function test_profile_is_found_by_number_without_a_name_search(): void
    {
        Http::fake([
            'www.gmc-uk.org/registrants*' => Http::response(
                $this->profileHtml('1234567', 'Martin Ivo Metombe Ngomba', 'Registered with a licence to practise'),
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
            ),
        ]);

        $records = (new GmcUkHtmlSource())->find('1234567');

        $this->assertCount(1, $records);
        $this->assertSame('Martin Ivo Metombe Ngomba', $records[0]['full_name']);
    }

    public function test_missing_profile_details_are_unavailable(): void
    {
        Http::fake([
            'www.gmc-uk.org/registrants*' => Http::response(
                '<html><body><h1>Registrant details</h1></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
            ),
        ]);

        $records = (new GmcUkHtmlSource())->find('1234567');

        $this->assertNull($records);
    }

    public function test_unrecognized_successful_page_is_unavailable_instead_of_a_definitive_no_match(): void
    {
        Http::fake([
            'www.gmc-uk.org/registrants*' => Http::response(
                '<html><body><a href="/privacy-and-cookies/cookies">Cookies we use</a><p>Search the register</p></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
            ),
        ]);

        $records = (new GmcUkHtmlSource())->find('1234567');

        $this->assertNull($records);
    }

    private function profileHtml(string $number, string $name, string $status): string
    {
        return '<html><body>'
            . '<h1 id="registrantNameId">' . e($name) . '</h1>'
            . '<span id="gmcNumberId">' . e($number) . '</span>'
            . '<div data-simpletooltip-content-id="registration-status">' . e($status) . '</div>'
            . '</body></html>';
    }
}
