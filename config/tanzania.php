<?php

return [
    'enabled' => filter_var(env('TANZANIA_LICENSE_VERIFICATION_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'base_url' => env('TANZANIA_LICENSE_VERIFICATION_URL', 'https://hprs.moh.go.tz/api/user_management/search-user/'),
    'council_uuid' => env('TANZANIA_COUNCIL_UUID', '6904e90f-5df9-4e7e-b681-a4f2044ef042'),
    'search_type' => env('TANZANIA_SEARCH_TYPE', 'mct_number'),
    'connect_timeout' => (int) env('TANZANIA_LICENSE_VERIFICATION_CONNECT_TIMEOUT', 10),
    'timeout' => (int) env('TANZANIA_LICENSE_VERIFICATION_TIMEOUT', 20),
    'council_name' => env('TANZANIA_COUNCIL_NAME', 'Medical Council of Tanganyika'),
    'council_short_name' => env('TANZANIA_COUNCIL_SHORT_NAME', 'MCT'),
];
