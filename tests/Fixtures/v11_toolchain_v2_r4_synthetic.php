<?php

$base = require base_path('tests/Fixtures/v11_toolchain_v2_r2_synthetic.php');
$template = $base['unknown_domain'];

$withDomain = static function (string $domain) use ($template): array {
    $fixture = $template;
    $fixture['route']['required_evidence_domain'] = $domain;

    return $fixture;
};

return [
    'returns_alias' => $withDomain('returns'),
    'contact_alias' => $withDomain('contact'),
    'storage_unmapped' => $withDomain('storage'),
    'shipping_policy_unmapped' => $withDomain('shipping_policy'),
    'unknown_domain' => $withDomain('unregistered_evidence_domain'),
];
