<?php

$states = [
    'MA' => 'Massachusetts',
    'OH' => 'Ohio',
    'CT' => 'Connecticut',
    'CO' => 'Colorado',
    'DE' => 'Delaware',
    'TX' => 'Texas',
    'IL' => 'Illinois',
    'NH' => 'New Hampshire',
    'AK' => 'Alaska',
    'WA' => 'Washington',
    'KS' => 'Kansas',
];

$configuredStates = array_map(
    static fn (string $state): string => strtoupper(trim($state)),
    explode(',', (string) env('USLICENSECHECK_SUPPORTED_STATES', 'OH,CT,CO,DE,TX,IL,NH,AK,WA,KS'))
);
$supportedStates = array_values(array_intersect(array_keys($states), array_unique($configuredStates)));
if (! in_array('KS', $supportedStates, true)) {
    $supportedStates[] = 'KS';
}

return [
    'base_url' => env('USLICENSECHECK_BASE_URL', 'https://uslicensecheck.com/api/v1'),
    'api_key' => env('USLICENSECHECK_API_KEY', ''),
    'connect_timeout' => (int) env('USLICENSECHECK_CONNECT_TIMEOUT', 3),
    'timeout' => (int) env('USLICENSECHECK_TIMEOUT', 10),
    'kansas_license_verification_url' => env('KANSAS_LICENSE_VERIFICATION_URL', 'https://www.kansas.gov/ssrv-ksbhada/search.html'),
    'kansas_request_cookie' => env('KANSAS_REQUEST_COOKIE', env('REQUEST_COOKIE', '')),
    'states' => $states,
    'supported_states' => $supportedStates,
];
