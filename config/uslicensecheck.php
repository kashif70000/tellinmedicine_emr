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
];

$configuredStates = array_map(
    static fn (string $state): string => strtoupper(trim($state)),
    explode(',', (string) env('USLICENSECHECK_SUPPORTED_STATES', 'OH,CT,CO,DE,TX,IL,NH,AK,WA'))
);
$supportedStates = array_values(array_intersect(array_keys($states), array_unique($configuredStates)));

return [
    'base_url' => env('USLICENSECHECK_BASE_URL', 'https://uslicensecheck.com/api/v1'),
    'api_key' => env('USLICENSECHECK_API_KEY', ''),
    'connect_timeout' => (int) env('USLICENSECHECK_CONNECT_TIMEOUT', 3),
    'timeout' => (int) env('USLICENSECHECK_TIMEOUT', 10),
    'states' => $states,
    'supported_states' => $supportedStates,
];
