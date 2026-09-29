<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Most templating systems load templates from disk. Here you may specify
    | an array of paths that should be checked for your views. Of course
    | the usual Laravel 'resources/views' path has already been registered
    | for you.
    |
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | This option determines where all the compiled Blade templates will be
    | stored for your application. Typically, this is within the storage
    | directory. However, as usual, you are free to change this value.
    |
    */

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        realpath(storage_path('framework/views'))
    ),

    /*
    |--------------------------------------------------------------------------
    | View Namespaces
    |--------------------------------------------------------------------------
    |
    | This option provides a mechanism for you to specify custom view
    | namespaces. These namespaces can be used to reference views in your
    | application. For example, this allows you to create mail views without
    | having to require the full path to the view.
    |
    */

    'namespaces' => [
        'mail' => resource_path('views/emails'),
    ],

];
