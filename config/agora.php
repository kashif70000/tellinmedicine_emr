<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Agora App ID
    |--------------------------------------------------------------------------
    | Found in your Agora Console → Project → App ID
    */
    'app_id' => env('AGORA_APP_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Agora App Certificate
    |--------------------------------------------------------------------------
    | Found in Agora Console → Project → App Certificate.
    | NEVER expose this in the frontend; keep it server-side only.
    */
    'app_certificate' => env('AGORA_APP_CERTIFICATE', ''),

    /*
    |--------------------------------------------------------------------------
    | Agora Customer ID & Customer Secret (REST API Credentials)
    |--------------------------------------------------------------------------
    | Found in Agora Console → Project Management → REST API Keys.
    | Required for Real-Time Speech-to-Text and Translation REST APIs.
    */
    'customer_id' => env('AGORA_CUSTOMER_ID', ''),
    'customer_secret' => env('AGORA_CUSTOMER_SECRET', ''),
];

