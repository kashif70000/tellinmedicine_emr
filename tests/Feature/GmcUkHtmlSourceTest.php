<?php

namespace Tests\Feature;

use App\Services\GmcUkHtmlSource;
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

        $records = (new GmcUkHtmlSource())->find('Martin Ivo Metombe NGOMBA');

        $this->assertSame([], $records);
    }

    public function test_only_exact_name_matches_from_gmc_registrant_profile_links_are_returned(): void
    {
        Http::fake([
            'www.gmc-uk.org/registrants*' => Http::response(
                '<html><body><a href="/privacy-and-cookies/cookies">Alice Smith</a><div><a href="/registrants/1234567">Alice Smith</a><p>Registration status: Registered</p><p>Licence to practise: Licensed</p></div></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
            ),
        ]);

        $records = (new GmcUkHtmlSource())->find('Alice Smith');

        $this->assertCount(1, $records);
        $this->assertSame('Alice Smith', $records[0]['full_name']);
        $this->assertSame('1234567', $records[0]['gmc_number']);
        $this->assertSame('https://www.gmc-uk.org/registrants/1234567', $records[0]['profile_url']);
        $this->assertStringContainsString('Registered', $records[0]['raw_text']);
        $this->assertSame('Registered', $records[0]['registration_status']);
        $this->assertSame('Licensed to practise', $records[0]['licence_status']);
    }

    public function test_name_search_result_is_returned_for_number_comparison_even_if_submitted_number_differs(): void
    {
        Http::fake([
            'www.gmc-uk.org/registrants*' => Http::response(
                '<html><body><div><a href="/registrants/1234567">Alice Smith</a><p>Registered with a licence to practise</p><p>Profession: Doctor</p></div></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
            ),
        ]);

        $records = (new GmcUkHtmlSource())->find('Alice Smith', '7654321');

        $this->assertCount(1, $records);
        $this->assertSame('1234567', $records[0]['gmc_number']);
        $this->assertSame('Registered', $records[0]['registration_status']);
        $this->assertSame('Licensed to practise', $records[0]['licence_status']);
    }

    public function test_name_search_matches_first_and_last_name_when_profile_includes_middle_names(): void
    {
        Http::fake([
            'www.gmc-uk.org/registrants*' => Http::response(
                '<html><body><div><a href="/registrants/1234567">Martin Ivo Metombe Ngomba</a><p>Registered with a licence to practise</p></div></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
            ),
        ]);

        $records = (new GmcUkHtmlSource())->find('Martin Ngomba', '1234567');

        $this->assertCount(1, $records);
        $this->assertSame('Martin Ivo Metombe Ngomba', $records[0]['full_name']);
    }

    public function test_non_matching_gmc_profiles_are_not_returned_as_fallback_candidates(): void
    {
        Http::fake([
            'www.gmc-uk.org/registrants*' => Http::response(
                '<html><body><div><a href="/registrants/1234567">Alice Smith</a><p>Registration status: Registered</p></div></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
            ),
        ]);

        $records = (new GmcUkHtmlSource())->find('Bob Jones');

        $this->assertSame([], $records);
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

        $records = (new GmcUkHtmlSource())->find('Alice Smith');

        $this->assertNull($records);
    }
}
