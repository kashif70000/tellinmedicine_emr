<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Subtitle Language
    |--------------------------------------------------------------------------
    |
    | Used as fallback when an appointment has no preferred subtitle language
    | stored or when an invalid language code is encountered.
    |
    */
    'default' => 'ur-PK',

    /*
    |--------------------------------------------------------------------------
    | Supported Subtitle & Translation Languages
    |--------------------------------------------------------------------------
    |
    | Single source of truth for all supported subtitle languages in the system.
    | Adding a new language here automatically updates booking dropdowns,
    | validation, backend Agora STT translation targets, and frontend RTL/LTR UI.
    |
    */
    'languages' => [

        'en-US' => [
            'name'      => 'English',
            'direction' => 'ltr',
        ],

        'ur-PK' => [
            'name'      => 'Urdu',
            'direction' => 'rtl',
        ],

        'ar-SA' => [
            'name'      => 'Arabic',
            'direction' => 'rtl',
        ],

        'es-ES' => [
            'name'      => 'Spanish',
            'direction' => 'ltr',
        ],

        'fr-FR' => [
            'name'      => 'French',
            'direction' => 'ltr',
        ],

        'de-DE' => [
            'name'      => 'German',
            'direction' => 'ltr',
        ],

        'hi-IN' => [
            'name'      => 'Hindi',
            'direction' => 'ltr',
        ],

    ],

];
