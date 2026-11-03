<?php

return [
    'license_verification_url' => env(
        'PMDC_LICENSE_VERIFICATION_URL',
        'https://hospitals-inspections.pmdc.pk/api/DRC/GetData'
    ),
    'timeout' => (int) env('PMDC_LICENSE_VERIFICATION_TIMEOUT', 15),
];
