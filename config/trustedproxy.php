<?php

$configuredProxies = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('TRUSTED_PROXIES', ''))
)));

return [
    // Empty by default: forwarded headers are ignored unless deployment
    // provides an explicit proxy IP/CIDR allowlist.
    'proxies' => $configuredProxies,
];
