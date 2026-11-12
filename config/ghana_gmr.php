<?php

return [
    'base_url' => env('GHANA_GMR_BASE_URL', 'https://vwendenwhvomvzqwhhxz.supabase.co'),
    'publishable_key' => env('GHANA_GMR_PUBLISHABLE_KEY', ''),
    'timeout' => (int) env('GHANA_GMR_TIMEOUT', 20),
    'connect_timeout' => (int) env('GHANA_GMR_CONNECT_TIMEOUT', 10),
];
