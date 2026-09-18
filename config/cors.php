<?php

$configuredOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
)));

$developmentOrigins = [
    'http://localhost:5173',
    'http://localhost:5174',
    'http://127.0.0.1:5173',
    'http://127.0.0.1:5174',
];

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Production intentionally has no fallback. A missing allowlist fails closed.
    'allowed_origins' => $configuredOrigins !== []
        ? $configuredOrigins
        : (env('APP_ENV', 'production') === 'production' ? [] : $developmentOrigins),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Content-Type',
        'X-XSRF-TOKEN',
        'X-Requested-With',
        'X-Idempotency-Key',
        'X-Analytics-Token',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
