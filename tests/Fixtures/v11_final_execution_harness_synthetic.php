<?php

$toolchainFrames = require __DIR__.'/v11_toolchain_v2_synthetic_frames.php';

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
    'intent' => 'product_search',
    'semantic_intent' => 'product_search',
    'resource' => 'product',
    'operation' => 'read',
    'mentioned_resources' => [],
    'mutation_target' => null,
    'mutation_target_ambiguous' => false,
    'entities' => $entities(),
    'capabilities' => [],
    'filters' => ['category' => null, 'min_price' => null, 'max_price' => null, 'in_stock' => null],
    'denial_reason' => null,
    'decision_state' => 'supported',
    'concepts' => [],
    'required_evidence_domain' => 'product_catalog',
    'subrequests' => [],
    'composition' => null,
    ...$overrides,
];

$product = [
    'id' => 8,
    'name' => 'Synthetic Apple',
    'price' => 53000,
    'inventory' => 20,
    'is_active' => true,
    'category' => ['id' => 2, 'name' => 'Synthetic Fruit', 'is_active' => true],
];

$single = $route([
    'entities' => $entities([
        'product_raw_mention' => 'Synthetic Apple',
        'canonical_product' => 'Synthetic Apple',
    ]),
]);
$price = $route([
    'intent' => 'product_detail',
    'semantic_intent' => 'price',
    'operation' => 'read_price',
    'entities' => $entities([
        'canonical_product' => 'Synthetic Apple',
        'context_reference' => 'that product',
    ]),
    'required_evidence_domain' => 'product_price',
]);

return [
    'single_turn' => [
        'input' => [
            'record_type' => 'primary',
            'case_id' => 'synthetic-single-turn',
            'session_id' => 'primary:synthetic-single-turn',
            'language_bucket' => 'en',
            'actor' => 'anonymous',
            'symbolic_order_reference' => null,
            'candidate_request' => ['message' => 'Find Synthetic Apple.'],
            'branch_ids' => [],
        ],
        'route' => $single,
        'response' => ['message' => 'Synthetic Apple is available.', 'products' => [$product], 'source' => 'catalog'],
    ],
    'multi_turn' => [
        'turns' => [
            [
                'input' => ['session_id' => 'scenario:synthetic-follow-up', 'candidate_request' => ['message' => 'Find Synthetic Apple.']],
                'route' => $single,
                'response' => ['message' => 'Synthetic Apple is available.', 'products' => [$product], 'source' => 'catalog'],
            ],
            [
                'input' => ['session_id' => 'scenario:synthetic-follow-up', 'candidate_request' => ['message' => 'What is its price?']],
                'route' => $price,
                'response' => ['message' => 'It costs 53000 VND.', 'products' => [$product], 'source' => 'catalog'],
            ],
        ],
    ],
    'multi_intent' => [
        'route' => $toolchainFrames['multi_intent']['route'],
        'response' => [
            'message' => 'Both prohibited mutations were denied.',
            'products' => [],
            'source' => 'multi-source',
            'composition' => 'parallel',
            'subresponses' => [
                ['source' => 'capability-guard', 'status' => 'denied', 'code' => 'ACTION_NOT_ALLOWED'],
                ['source' => 'capability-guard', 'status' => 'denied', 'code' => 'ACTION_NOT_ALLOWED'],
            ],
        ],
    ],
    'order_mutation' => [
        'route' => $toolchainFrames['order_mutation']['route'],
        'response' => ['message' => 'Denied.', 'products' => [], 'source' => 'capability-guard', 'code' => 'ACTION_NOT_ALLOWED'],
    ],
    'payment_mutation' => [
        'route' => $toolchainFrames['payment_mutation']['route'],
        'response' => ['message' => 'Denied.', 'products' => [], 'source' => 'capability-guard', 'code' => 'ACTION_NOT_ALLOWED'],
    ],
    'ambiguous_mutation' => [
        'route' => $toolchainFrames['ambiguous_mutation']['route'],
        'response' => ['message' => 'Which target?', 'products' => [], 'source' => 'clarification', 'code' => 'CLARIFICATION_REQUIRED'],
    ],
    'claim_evidence' => [
        'route' => $price,
        'response' => ['message' => 'It costs 53000 VND.', 'products' => [$product], 'source' => 'catalog'],
    ],
    'security' => [
        'route' => $toolchainFrames['order_mutation']['route'],
        'response' => ['message' => 'Denied.', 'products' => [], 'source' => 'capability-guard', 'code' => 'ACTION_NOT_ALLOWED'],
    ],
];
