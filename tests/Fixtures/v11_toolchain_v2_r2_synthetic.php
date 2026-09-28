<?php

$entities = static fn (array $overrides = []): array => [
    'product_raw_mention' => null,
    'canonical_product' => null,
    'quantity' => null,
    'unit' => null,
    'order_reference' => null,
    'ordinal_reference' => null,
    'context_reference' => null,
    'account_target' => null,
    'requested_mutation_value' => null,
    ...$overrides,
];

$route = static fn (array $overrides = []): array => [
    'intent' => 'product_detail',
    'semantic_intent' => 'price',
    'resource' => 'product',
    'operation' => 'read_price',
    'mentioned_resources' => [],
    'mutation_target' => null,
    'mutation_target_ambiguous' => false,
    'entities' => $entities(),
    'capabilities' => [],
    'filters' => ['category' => null, 'min_price' => null, 'max_price' => null, 'in_stock' => null],
    'denial_reason' => null,
    'concepts' => [],
    'required_evidence_domain' => 'product_price',
    'subrequests' => [],
    ...$overrides,
];

$runtime = static fn (array $overrides = []): array => [
    'terminal' => 'ANSWER',
    'authorization_result' => 'ALLOW',
    'evidence' => [],
    'follow_up_state' => [
        'resolved_by_context' => false,
        'mutation_target' => null,
        'status' => 'not_applicable',
    ],
    'business_state_before' => str_repeat('d', 64),
    'business_state_after' => str_repeat('d', 64),
    'wrong_entity_unsafe_action' => false,
    'branch_observations' => [],
    'response' => [],
    ...$overrides,
];

$priceRoute = $route([
    'entities' => $entities([
        'product_raw_mention' => 'Synthetic Milk',
        'canonical_product' => 'Synthetic Milk',
        'quantity' => 3,
        'unit' => 'box',
    ]),
]);
$shippingRoute = $route([
    'intent' => 'shipping_info',
    'semantic_intent' => 'shipping_current_value',
    'resource' => 'site_settings',
    'operation' => 'read',
    'entities' => $entities(),
    'required_evidence_domain' => 'shipping',
]);
$priceRuntime = $runtime([
    'evidence' => [[
        'authority' => 'Product/Category structured authority',
        'source_id' => 'product-category-authority-v11',
        'source_version' => 1,
        'evidence_ref' => 'product:6:current_unit_price_vnd',
        'evidence_type' => 'structured_field',
    ]],
]);
$shippingRuntime = $runtime([
    'evidence' => [[
        'authority' => 'SiteSetting structured authority',
        'source_id' => 'site-settings-authority-v11',
        'source_version' => 1,
        'evidence_ref' => 'site-setting:shipping_fee_vnd',
        'evidence_type' => 'structured_field',
    ]],
]);
$parentRoute = $route([
    'intent' => 'multi_intent',
    'semantic_intent' => 'shipping_calculation',
    'resource' => 'product_and_site_settings',
    'operation' => 'calculate',
    'entities' => $entities([
        'product_raw_mention' => 'Synthetic Milk',
        'canonical_product' => 'Synthetic Milk',
        'quantity' => 3,
        'unit' => 'box',
    ]),
    'required_evidence_domain' => 'product_price_and_shipping_settings',
    'subrequests' => [$priceRoute, $shippingRoute],
]);
$parentRuntime = $runtime([
    'authorization_result' => 'PER_BRANCH',
    'evidence' => [...$priceRuntime['evidence'], ...$shippingRuntime['evidence']],
    'branch_observations' => [$priceRuntime, $shippingRuntime],
]);

return [
    'nested_shipping_alias' => [
        'route' => $parentRoute,
        'runtime' => $parentRuntime,
        'response' => [
            'message' => 'Synthetic delivered total.',
            'products' => [[
                'id' => 6,
                'name' => 'Synthetic Milk',
                'price' => 32000,
                'inventory' => 20,
                'is_active' => true,
                'category' => ['id' => 4, 'name' => 'Synthetic Dairy', 'is_active' => true],
            ]],
            'source' => 'multi-source',
            'composition' => 'shipping_eligibility',
            'subresponses' => [
                ['source' => 'catalog', 'status' => 'verified'],
                ['source' => 'site-settings', 'status' => 'verified'],
            ],
        ],
    ],
    'unknown_domain' => [
        'route' => $route([
            'intent' => 'knowledge_query',
            'semantic_intent' => 'knowledge_query',
            'resource' => 'approved_knowledge',
            'operation' => 'read',
            'required_evidence_domain' => 'storage',
        ]),
        'runtime' => $runtime(['authorization_result' => 'ALLOW_READ']),
    ],
];
