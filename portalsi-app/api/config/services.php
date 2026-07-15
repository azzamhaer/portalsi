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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Unified Portal SI identity endpoint used by the marketplace (and meet)
    // auth bridge. Points at this same API's own auth routes so all three
    // apps share one login. Use an internal/loopback URL in production to
    // avoid a public round-trip if desired.
    'portal_si' => [
        'api_url' => env('PORTALSI_API_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/api'),
        'timeout' => (int) env('PORTALSI_API_TIMEOUT', 12),
    ],

    'marketplace' => [
        'frontend_url' => env('MARKETPLACE_FRONTEND_URL', 'https://marketplace.portalsi.com'),

        'tripay' => [
            'mode' => env('TRIPAY_MODE', 'sandbox'),
            'api_key' => env('TRIPAY_API_KEY'),
            'private_key' => env('TRIPAY_PRIVATE_KEY'),
            'merchant_code' => env('TRIPAY_MERCHANT_CODE', 'T0001'),
        ],

        'brevo' => [
            'api_key' => env('BREVO_API_KEY'),
            'sender_email' => env('BREVO_SENDER_EMAIL', 'noreply@portalsi.com'),
            'sender_name' => env('BREVO_SENDER_NAME', 'Portal SI Marketplace'),
        ],

        'rajaongkir' => [
            'mode' => env('RAJAONGKIR_MODE', 'sandbox'),
            'enabled' => env('RAJAONGKIR_ENABLED', false),
            'api_key' => env('RAJAONGKIR_API_KEY'),
            'tariff_base_url' => env('RAJAONGKIR_TARIFF_BASE_URL'),
            'order_base_url' => env('RAJAONGKIR_ORDER_BASE_URL'),
        ],
    ],

    'meet' => [
        'frontend_url' => env('MEET_FRONTEND_URL', 'https://meet.portalsi.com'),
        'livekit_ws_url' => env('LIVEKIT_WS_URL', env('NEXT_PUBLIC_LIVEKIT_URL')),
        'livekit_http_url' => env('LIVEKIT_HTTP_URL'),
        'livekit_api_key' => env('LIVEKIT_API_KEY'),
        'livekit_api_secret' => env('LIVEKIT_API_SECRET'),
        'upload_ttl_seconds' => (int) env('MEET_UPLOAD_TTL_SECONDS', 3600),
    ],

];
