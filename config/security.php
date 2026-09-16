<?php

return [
    // Report-only first so production CSP can be enforced after browser verification.
    'csp_report_only' => env(
        'SECURITY_CSP_REPORT_ONLY',
        "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'"
    ),

    'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
];
