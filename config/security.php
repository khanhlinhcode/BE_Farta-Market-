<?php

$defaultCsp = "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; script-src 'self' https://challenges.cloudflare.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: https://res.cloudinary.com; connect-src 'self' https://api.cloudinary.com https://challenges.cloudflare.com; font-src 'self'; frame-src https://challenges.cloudflare.com";
$configuredCsp = trim((string) env('SECURITY_CSP', ''));

return [
    'csp_enforced' => $configuredCsp !== '' ? $configuredCsp : $defaultCsp,

    // Local/testing keeps report-only to avoid blocking developer tooling.
    'csp_report_only' => env(
        'SECURITY_CSP_REPORT_ONLY',
        $defaultCsp
    ),

    'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),

    'rate_limits' => [
        'api_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 120),
        'api_ip_per_minute' => (int) env('API_IP_RATE_LIMIT_PER_MINUTE', 600),
        'api_global_per_minute' => (int) env('API_GLOBAL_RATE_LIMIT_PER_MINUTE', 2000),
        'expensive_read_per_minute' => (int) env('EXPENSIVE_READ_RATE_LIMIT_PER_MINUTE', 30),
        'expensive_read_ip_per_minute' => (int) env('EXPENSIVE_READ_IP_RATE_LIMIT_PER_MINUTE', 120),
        'expensive_read_global_per_minute' => (int) env('EXPENSIVE_READ_GLOBAL_RATE_LIMIT_PER_MINUTE', 600),
        'admin_export_per_minute' => (int) env('ADMIN_EXPORT_RATE_LIMIT_PER_MINUTE', 5),
    ],
];
