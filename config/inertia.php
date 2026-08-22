<?php

use App\Http\Middleware\HandleInertiaRequests;

return [

    /*
    |--------------------------------------------------------------------------
    | Page Paths
    |--------------------------------------------------------------------------
    |
    | The directories where Inertia looks for page components. This project
    | uses a lowercase "pages" directory — the package default ("Pages")
    | breaks page resolution on case-sensitive filesystems (Linux CI).
    |
    */

    'middleware' => HandleInertiaRequests::class,

    'page_paths' => [
        resource_path('js/pages'),
    ],

    'page_extensions' => ['js', 'jsx', 'ts', 'tsx', 'vue'],

    'testing' => [

        'ensure_pages_exist' => true,

        'page_paths' => [
            resource_path('js/pages'),
        ],

        'page_extensions' => ['js', 'jsx', 'ts', 'tsx', 'vue'],

    ],

];
