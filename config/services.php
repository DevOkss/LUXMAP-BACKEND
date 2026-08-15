<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'institution' => [
        'endpoint' => env('INSTITUTION_API_URL'),
    ],

    'qr_key' => env('QR_ENCRYPTION_KEY'),

    'pwa' => [
        // Public origin where the student PWA is served (used by the /app
        // landing page). Changeable per environment.
        'url' => env('PWA_URL', 'http://192.168.1.113:9000'),
    ],

    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@example.com'),
    ],

    'paymongo' => [
        // Base URL for the PayMongo API (v2 is required for Checkout Sessions).
        'api_url' => env('PAYMONGO_API_URL', 'https://api.paymongo.com/v2'),
        // Per-organization receiving accounts (secret + webhook secret keyed by
        // the organization code, e.g. PAYMONGO_SECRET_KEY_SSC).
        'secret_key' => env('PAYMONGO_SECRET_KEY'),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
        // Where PayMongo redirects the student after a checkout.
        'success_url' => env('PAYMONGO_SUCCESS_URL'),
        'cancel_url' => env('PAYMONGO_CANCEL_URL'),
    ],

];
