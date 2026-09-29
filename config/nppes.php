<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CMS NPPES / NPI Registry API Configuration
    |--------------------------------------------------------------------------
    |
    | Public REST API configuration for CMS National Plan and Provider
    | Enumeration System (NPPES). No API key is required.
    |
    */

    'base_url' => env('NPPES_BASE_URL', 'https://npiregistry.cms.hhs.gov/api/'),
    'api_version' => env('NPPES_API_VERSION', '2.1'),
    'timeout' => (int) env('NPPES_TIMEOUT_SECONDS', 12),
    'request_delay_ms' => (int) env('NPPES_REQUEST_DELAY_MS', 250),
    'cache_ttl_minutes' => (int) env('NPPES_CACHE_TTL_MINUTES', 60),
    'max_retries' => (int) env('NPPES_MAX_RETRIES', 2),

    /*
    |--------------------------------------------------------------------------
    | Enrichment Data Source (bulk or api)
    |--------------------------------------------------------------------------
    |
    | 'bulk' uses the local CMS NPPES bulk dataset directory table.
    | 'api' queries the public CMS NPPES REST API directly.
    |
    */
    'source' => env('NPPES_SOURCE', 'bulk'),

    /*
    |--------------------------------------------------------------------------
    | Bulk Import & Local Directory Configuration
    |--------------------------------------------------------------------------
    */
    'bulk_batch_size' => (int) env('NPPES_BULK_BATCH_SIZE', 1000),
    'bulk_cache_ttl' => (int) env('NPPES_BULK_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Pharmacy Provider Taxonomy Codes (NUCC Health Care Provider Taxonomy)
    |--------------------------------------------------------------------------
    */
    'pharmacy_taxonomy_codes' => [
        '333600000X' => 'Pharmacy',
        '3336C0002X' => 'Clinic Pharmacy',
        '3336C0003X' => 'Community/Retail Pharmacy',
        '3336C0004X' => 'Compounding Pharmacy',
        '3336H0001X' => 'Home Infusion Therapy Pharmacy',
        '3336I0012X' => 'Institutional Pharmacy',
        '3336L0003X' => 'Long Term Care Pharmacy',
        '3336M0002X' => 'Mail Order Pharmacy',
        '3336M0003X' => 'Managed Care Organization Pharmacy',
        '3336N0007X' => 'Nuclear Pharmacy',
        '3336S0011X' => 'Specialty Pharmacy',
    ],

    /*
    |--------------------------------------------------------------------------
    | Clinical Laboratory Taxonomy Codes (NUCC Health Care Provider Taxonomy)
    |--------------------------------------------------------------------------
    |
    | Strictly clinical/diagnostic testing provider organizations. Excludes
    | research, dental, veterinary, industrial, and environmental testing.
    |
    */
    'laboratory_taxonomy_codes' => [
        '291U00000X' => 'Clinical Medical Laboratory',
        '261QL0400X' => 'Clinic/Center - Clinical Medical Laboratory',
        '293D00000X' => 'Physiological Laboratory',
    ],
];
