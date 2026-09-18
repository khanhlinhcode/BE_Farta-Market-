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
];
