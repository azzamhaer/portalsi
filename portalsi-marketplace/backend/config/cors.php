<?php

$frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
$extraOrigins = array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', ''))));
$extraOriginPatterns = array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGIN_PATTERNS', ''))));
$localOriginPatterns = env('APP_ENV') === 'production' ? [] : [
    '#^https?://localhost(:\d+)?$#',
    '#^https?://127\.0\.0\.1(:\d+)?$#',
    '#^https?://\[::1\](:\d+)?$#',
    '#^https?://10\.\d{1,3}\.\d{1,3}\.\d{1,3}(:\d+)?$#',
    '#^https?://172\.(1[6-9]|2\d|3[0-1])\.\d{1,3}\.\d{1,3}(:\d+)?$#',
    '#^https?://192\.168\.\d{1,3}\.\d{1,3}(:\d+)?$#',
];

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_unique(array_filter([
        $frontendUrl,
        'https://marketplace.portalsi.com',
        'https://www.marketplace.portalsi.com',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        ...$extraOrigins,
    ]))),
    'allowed_origins_patterns' => array_values(array_unique(array_filter([
        ...$localOriginPatterns,
        ...$extraOriginPatterns,
    ]))),
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
