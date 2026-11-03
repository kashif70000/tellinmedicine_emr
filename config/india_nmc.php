<?php

return [
    'license_verification_url' => env(
        'INDIA_NMC_LICENSE_VERIFICATION_URL',
        'https://nmc.org.in/indian-medical-register/search'
    ),
    'timeout' => (int) env('INDIA_NMC_LICENSE_VERIFICATION_TIMEOUT', 15),
];
