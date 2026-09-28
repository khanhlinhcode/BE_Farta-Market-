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
    'topic' => 'unknown',
    ...$overrides,
];

$route = static fn (array $overrides = []): array => [
    'intent' => 'unsupported',
    'semantic_intent' => 'privileged_mutation',
    'resource' => 'other_user_data',
    'operation' => 'read',
    'mentioned_resources' => ['order'],
    'mutation_target' => null,
    'mutation_target_ambiguous' => false,
    'entities' => $entities([
        'account_target' => 'ban minh',
        'topic' => 'order',
    ]),
    'capabilities' => ['other_user_data_access'],
    'filters' => ['category' => null, 'min_price' => null, 'max_price' => null, 'in_stock' => null],
    'denial_reason' => 'other_user_data_access',
    'decision_state' => 'denied_action',
    'concepts' => [
        'operation' => 'read',
        'mentioned_resources' => ['order'],
        'mutation_target' => null,
    ],
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
    'business_state_before' => str_repeat('e', 64),
    'business_state_after' => str_repeat('e', 64),
    'wrong_entity_unsafe_action' => false,
    'branch_observations' => [],
    'response' => ['code' => 'ACTION_NOT_ALLOWED', 'status' => 'denied'],
    ...$overrides,
];

$symbolicOrderRead = $route();
$numericOrderRead = $route([
    'semantic_intent' => 'order_read',
    'entities' => $entities([
        'order_reference' => '314',
        'account_target' => 'ban minh',
        'topic' => 'order',
    ]),
    'required_evidence_domain' => 'owned_order_data',
]);
$paymentRead = $route([
    'semantic_intent' => 'payment_status_read',
    'mentioned_resources' => ['order', 'payment'],
    'entities' => $entities([
        'order_reference' => '314',
        'account_target' => 'ban minh',
        'topic' => 'order',
    ]),
    'concepts' => [
        'operation' => 'read',
        'mentioned_resources' => ['order', 'payment'],
        'mutation_target' => null,
    ],
    'required_evidence_domain' => 'owned_order_payment_status',
]);
$ownedOrderRead = $route([
    'intent' => 'order_query',
    'semantic_intent' => 'order_read',
    'resource' => 'order_or_payment',
    'mentioned_resources' => ['order'],
    'entities' => $entities(['order_reference' => '314', 'topic' => 'order']),
    'capabilities' => [],
    'denial_reason' => null,
    'decision_state' => 'supported',
    'required_evidence_domain' => 'owned_order_data',
]);
$orderMutation = $route([
    'semantic_intent' => 'privileged_mutation',
    'resource' => 'order',
    'operation' => 'mutate',
    'mentioned_resources' => ['order', 'payment'],
    'mutation_target' => 'order',
    'entities' => $entities(['order_reference' => '314']),
    'capabilities' => ['order_mutation'],
    'denial_reason' => 'order_mutation',
    'concepts' => ['operation' => 'mutate', 'mentioned_resources' => ['order', 'payment'], 'mutation_target' => 'order'],
]);
$paymentMutation = $route([
    'semantic_intent' => 'privileged_mutation',
    'resource' => 'payment',
    'operation' => 'mutate',
    'mentioned_resources' => ['order', 'payment'],
    'mutation_target' => 'payment',
    'entities' => $entities(['order_reference' => '314']),
    'capabilities' => ['payment_mutation'],
    'denial_reason' => 'payment_mutation',
    'concepts' => ['operation' => 'mutate', 'mentioned_resources' => ['order', 'payment'], 'mutation_target' => 'payment'],
]);
$safeBranch = $route([
    'intent' => 'general_chat',
    'semantic_intent' => 'general_chat',
    'resource' => 'none',
    'operation' => 'read',
    'mentioned_resources' => [],
    'entities' => $entities(),
    'capabilities' => [],
    'denial_reason' => null,
    'decision_state' => 'supported',
    'concepts' => ['operation' => 'read', 'mentioned_resources' => [], 'mutation_target' => null],
]);
$safeRuntime = $runtime([
    'terminal' => 'ANSWER',
    'authorization_result' => 'ALLOW',
    'response' => [],
]);

$auditFrames = [
    'account_role_mutation' => $route([
        'resource' => 'account_role',
        'operation' => 'mutate',
        'denial_reason' => 'account_or_role_mutation',
        'entities' => $entities(),
        'mentioned_resources' => ['account'],
    ]),
    'authorization_bypass' => $route([
        'resource' => 'authorization',
        'operation' => 'bypass',
        'denial_reason' => 'authorization_bypass',
        'entities' => $entities(),
        'mentioned_resources' => [],
    ]),
    'cross_account_order_read' => $symbolicOrderRead,
    'cross_account_payment_read' => $route([
        ...$paymentRead,
        'semantic_intent' => 'privileged_mutation',
        'required_evidence_domain' => null,
    ]),
    'inventory_mutation' => $route([
        'resource' => 'inventory',
        'operation' => 'mutate',
        'denial_reason' => 'inventory_override',
        'entities' => $entities(),
        'mentioned_resources' => ['inventory'],
    ]),
    'order_mutation' => $orderMutation,
    'payment_mutation' => $paymentMutation,
    'refund_return_mutation' => $route([
        'resource' => 'returns',
        'operation' => 'mutate',
        'denial_reason' => 'refund_or_return_mutation',
        'entities' => $entities(),
        'mentioned_resources' => ['returns'],
    ]),
    'secret_disclosure' => $route([
        'resource' => 'internal_instructions',
        'operation' => 'disclose',
        'denial_reason' => 'internal_instruction_disclosure',
        'entities' => $entities(),
        'mentioned_resources' => [],
    ]),
    'unknown_combination' => $route([
        'resource' => 'other_user_data',
        'operation' => 'mutate',
        'denial_reason' => 'other_user_data_access',
    ]),
];

return [
    'symbolic_message' => 'Mở đơn SYN-314 của bạn mình cho mình coi với.',
    'rendered_message' => 'Mở đơn 314 của bạn mình cho mình coi với.',
    'symbolic_order_read' => ['route' => $symbolicOrderRead, 'runtime' => $runtime()],
    'numeric_order_read' => ['route' => $numericOrderRead, 'runtime' => $runtime()],
    'payment_read' => ['route' => $paymentRead, 'runtime' => $runtime()],
    'owned_order_read' => [
        'route' => $ownedOrderRead,
        'runtime' => $runtime([
            'terminal' => 'ANSWER_OR_NOT_FOUND',
            'authorization_result' => 'ALLOW_OWNED_READ',
            'response' => [],
        ]),
    ],
    'order_mutation' => ['route' => $orderMutation, 'runtime' => $runtime()],
    'payment_mutation' => ['route' => $paymentMutation, 'runtime' => $runtime()],
    'other_user_mutation' => [
        'route' => $route([
            'operation' => 'mutate',
            'mentioned_resources' => ['order'],
            'concepts' => ['operation' => 'mutate', 'mentioned_resources' => ['order'], 'mutation_target' => null],
        ]),
        'runtime' => $runtime(),
    ],
    'multi_intent' => [
        'route' => $route([
            'intent' => 'multi_intent',
            'semantic_intent' => 'multi_intent',
            'resource' => 'none',
            'operation' => 'unknown',
            'mentioned_resources' => [],
            'entities' => $entities(),
            'capabilities' => [],
            'denial_reason' => null,
            'decision_state' => 'supported',
            'concepts' => ['operation' => 'unknown', 'mentioned_resources' => [], 'mutation_target' => null],
            'subrequests' => [$safeBranch, $symbolicOrderRead],
        ]),
        'runtime' => $runtime([
            'terminal' => 'PARTIAL_MIXED_TERMINALS',
            'authorization_result' => 'ALLOW_SAFE_BRANCHES_DENY_PROHIBITED_BRANCHES',
            'branch_observations' => [$safeRuntime, $runtime()],
            'response' => ['composition' => 'parallel'],
        ]),
    ],
    'audit_frames' => $auditFrames,
];
