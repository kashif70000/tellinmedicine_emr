<?php

return [
    'enabled' => filter_var(env('UGANDA_LICENSE_VERIFICATION_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'ca_bundle_path' => env('UGANDA_CA_BUNDLE_PATH', env('SSL_CERT_FILE', '')),
    'issuer_ca_path' => resource_path('certificates/uganda-sectigo-r36-intermediate.pem'),
    'license_verification_url' => env(
        'UGANDA_LICENSE_VERIFICATION_URL',
        'https://ehealthlicense.go.ug/index.php/search/'
    ),
    'connect_timeout' => (int) env('UGANDA_LICENSE_VERIFICATION_CONNECT_TIMEOUT', 5),
    'timeout' => (int) env('UGANDA_LICENSE_VERIFICATION_TIMEOUT', 20),
    'retry_delay_ms' => (int) env('UGANDA_LICENSE_VERIFICATION_RETRY_DELAY_MS', 250),
    'diagnostics' => filter_var(env('UGANDA_LICENSE_VERIFICATION_DIAGNOSTICS', false), FILTER_VALIDATE_BOOLEAN),
];
