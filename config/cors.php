<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The student PWA is hosted separately (e.g. Vercel) and calls this API
    | cross-origin with an Authorization Bearer token plus the custom
    | X-Device-Fingerprint header. Allowed origins come from the
    | CORS_ALLOWED_ORIGINS env var (comma-separated) so they can be changed
    | per environment without code changes.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Content-Type',
        'Authorization',
        'X-Device-Fingerprint',
        'X-Requested-With',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
