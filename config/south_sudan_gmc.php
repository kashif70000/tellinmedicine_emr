<?php

return [
    'registry_url' => env('SOUTH_SUDAN_GMC_REGISTRY_URL', 'https://ssgmc.gov.ss/online/registry/0'),
    'search_url' => env('SOUTH_SUDAN_GMC_SEARCH_URL', 'https://ssgmc.gov.ss/online/registry_search'),
    'connect_timeout' => (int) env('SOUTH_SUDAN_GMC_CONNECT_TIMEOUT', 10),
    'timeout' => (int) env('SOUTH_SUDAN_GMC_TIMEOUT', 20),
];