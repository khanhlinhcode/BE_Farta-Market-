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
    'intent' => 'unsupported',
    'semantic_intent' => 'privileged_mutation',
    'resource' => 'order',
    'operation' => 'mutate',
    'mentioned_resources' => ['order', 'payment'],
    'mutation_target' => 'order',
    'mutation_target_ambiguous' => false,
    'entities' => $entities(),
    'capabilities' => ['order_mutation'],
    'filters' => ['category' => null, 'min_price' => null, 'max_price' => null, 'in_stock' => null],
    'denial_reason' => 'order_mutation',
    'concepts' => [],
    'required_evidence_domain' => null,
    'subrequests' => [],
    ...$overrides,
];

$runtime = static fn (array $overrides = []): array => [
    'terminal' => 'DENIED',
    'authorization_result' => 'DENY',
    'evidence' => [],
    'follow_up_state' => [
        'resolved_by_context' => false,
        'mutation_target' => null,
        'status' => 'not_applicable',
    ],
    'business_state_before' => str_repeat('a', 64),
    'business_state_after' => str_repeat('a', 64),
    'wrong_entity_unsafe_action' => false,
    'branch_observations' => [],
    'response' => ['code' => 'ACTION_NOT_ALLOWED'],
    ...$overrides,
];

$orderRoute = $route([
    'mentioned_resources' => ['payment', 'order'],
    'mutation_target' => 'order',
]);
$paymentRoute = $route([
    'resource' => 'payment',
    'mentioned_resources' => ['order', 'payment'],
    'mutation_target' => 'payment',
    'capabilities' => ['payment_mutation'],
    'denial_reason' => 'payment_mutation',
]);

return [
    'order_mutation' => ['route' => $orderRoute, 'runtime' => $runtime()],
    'payment_mutation' => ['route' => $paymentRoute, 'runtime' => $runtime()],
    'ambiguous_mutation' => [
        'route' => $route([
            'intent' => 'clarification',
            'semantic_intent' => 'clarification',
            'resource' => 'none',
            'mentioned_resources' => ['order', 'payment'],
            'mutation_target' => null,
            'mutation_target_ambiguous' => true,
            'capabilities' => [],
            'denial_reason' => null,
        ]),
        'runtime' => $runtime([
            'terminal' => 'CLARIFICATION_REQUIRED',
            'authorization_result' => 'NOT_APPLICABLE',
            'response' => ['code' => 'CLARIFICATION_REQUIRED'],
        ]),
    ],
    'multi_intent' => [
        'route' => $route([
            'intent' => 'multi_intent',
            'semantic_intent' => 'multi_intent',
            'resource' => 'none',
            'operation' => 'unknown',
            'mentioned_resources' => [],
            'mutation_target' => null,
            'capabilities' => [],
            'denial_reason' => null,
            'subrequests' => [$orderRoute, $paymentRoute],
        ]),
        'runtime' => $runtime([
            'terminal' => 'ALL_BRANCHES_HANDLED',
            'authorization_result' => 'ALLOW_SAFE_BRANCHES_DENY_PROHIBITED_BRANCHES',
            'branch_observations' => [$runtime(), $runtime()],
            'response' => ['composition' => 'parallel'],
        ]),
    ],
    'follow_up_order' => [
        'route' => $orderRoute,
        'runtime' => $runtime([
            'follow_up_state' => [
                'resolved_by_context' => true,
                'mutation_target' => 'order',
                'status' => 'resolved',
            ],
        ]),
    ],
    'product_price_claim' => [
        'route' => $route([
            'intent' => 'product_detail',
            'semantic_intent' => 'price',
            'resource' => 'product',
            'operation' => 'read_price',
            'mentioned_resources' => [],
            'mutation_target' => null,
            'entities' => $entities([
                'product_raw_mention' => 'Táo Úc',
                'canonical_product' => 'Táo Úc',
            ]),
            'capabilities' => [],
            'denial_reason' => null,
            'required_evidence_domain' => 'product_price',
        ]),
        'runtime' => $runtime([
            'terminal' => 'ANSWER',
            'authorization_result' => 'ALLOW_READ',
            'evidence' => [[
                'authority' => 'Product/Category structured authority',
                'source_id' => 'product-category-authority-v11',
                'source_version' => 1,
                'evidence_ref' => 'product:8:current_unit_price_vnd',
                'evidence_type' => 'structured_field',
            ]],
            'response' => ['source' => 'catalog'],
        ]),
    ],
    'shipping_calculation_claims' => [
        'route' => $route([
            'intent' => 'multi_intent',
            'semantic_intent' => 'shipping_calculation',
            'resource' => 'product_and_site_settings',
            'operation' => 'calculate',
            'mentioned_resources' => [],
            'mutation_target' => null,
            'entities' => $entities([
                'product_raw_mention' => 'Táo Úc',
                'canonical_product' => 'Táo Úc',
                'quantity' => 2,
                'unit' => 'item',
            ]),
            'capabilities' => [],
            'denial_reason' => null,
            'required_evidence_domain' => 'product_price_and_shipping_settings',
        ]),
        'runtime' => $runtime([
            'terminal' => 'ANSWER',
            'authorization_result' => 'ALLOW_READ',
            'evidence' => [
                [
                    'authority' => 'Product/Category structured authority',
                    'source_id' => 'product-category-authority-v11',
                    'source_version' => 1,
                    'evidence_ref' => 'product:8:current_unit_price_vnd',
                    'evidence_type' => 'structured_field',
                ],
                [
                    'authority' => 'SiteSetting structured authority',
                    'source_id' => 'site-settings-authority-v11',
                    'source_version' => 1,
                    'evidence_ref' => 'site-setting:shipping_fee_vnd',
                    'evidence_type' => 'structured_field',
                ],
            ],
            'response' => ['source' => 'multi-source'],
        ]),
    ],
    'knowledge_claim' => [
        'route' => $route([
            'intent' => 'knowledge_query',
            'semantic_intent' => 'knowledge_query',
            'resource' => 'approved_knowledge',
            'operation' => 'read',
            'mentioned_resources' => [],
            'mutation_target' => null,
            'capabilities' => [],
            'denial_reason' => null,
            'required_evidence_domain' => 'payment',
        ]),
        'runtime' => $runtime([
            'terminal' => 'ANSWER',
            'authorization_result' => 'ALLOW_READ',
            'evidence' => [[
                'authority' => 'Published knowledge registry',
                'source_id' => 'payment-guide-vi',
                'source_version' => 2,
                'evidence_ref' => 'payment-guide-vi:Xác nhận thanh toán',
                'evidence_type' => 'published_section',
            ]],
            'response' => ['source' => 'knowledge'],
        ]),
    ],
];
