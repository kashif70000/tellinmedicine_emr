<?php

return [
    'enabled' => filter_var(env('UGANDA_LICENSE_VERIFICATION_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'ca_bundle_path' => env('UGANDA_CA_BUNDLE_PATH', env('SSL_CERT_FILE', 'D:\\laragon\\etc\\ssl\\cacert.pem')),
    'license_verification_url' => env(
        'UGANDA_LICENSE_VERIFICATION_URL',
        'https://ehealthlicense.go.ug/index.php/search/'
    ),
    'connect_timeout' => (int) env('UGANDA_LICENSE_VERIFICATION_CONNECT_TIMEOUT', 10),
    'timeout' => (int) env('UGANDA_LICENSE_VERIFICATION_TIMEOUT', 20),
    'diagnostics' => filter_var(env('UGANDA_LICENSE_VERIFICATION_DIAGNOSTICS', false), FILTER_VALIDATE_BOOLEAN),
];
