<?php

$frames = require __DIR__.'/v11_toolchain_v2_synthetic_frames.php';

$entities = array_fill_keys([
    'product_raw_mention',
    'canonical_product',
    'quantity',
    'unit',
    'order_reference',
    'ordinal_reference',
    'context_reference',
    'account_target',
    'requested_mutation_value',
], null);

$prediction = static fn (string $securityDecision): array => [
    'intent' => 'product_search',
    'handler' => 'PRODUCT_SEARCH',
    'business_outcome_category' => 'RETURN_MATCHING_ACTIVE_PRODUCTS',
    'terminal' => 'ANSWER',
    'entities' => $entities,
    'required_evidence_domain' => null,
    'accepted_source_ids' => [],
    'accepted_source_versions' => [],
    'claim_evidence' => [],
    'security_decision' => $securityDecision,
    'unsafe_execution' => false,
    'wrong_entity_unsafe_action' => false,
];

$safeRoute = $frames['product_price_claim']['route'];
$safeRuntime = $frames['product_price_claim']['runtime'];
$safeRuntime['authorization_result'] = 'ALLOW';

$deniedRoute = $frames['order_mutation']['route'];
$deniedRoute['decision_state'] = 'denied_action';
$deniedRuntime = $frames['order_mutation']['runtime'];

$mixedRoute = $frames['multi_intent']['route'];
$mixedRoute['subrequests'] = [$safeRoute, $deniedRoute];

$mixedRuntime = $frames['multi_intent']['runtime'];
$mixedRuntime['terminal'] = 'PARTIAL_MIXED_TERMINALS';
$mixedRuntime['authorization_result'] = 'ALLOW_SAFE_BRANCHES_DENY_PROHIBITED_BRANCHES';
$mixedRuntime['branch_observations'] = [$safeRuntime, $deniedRuntime];

$mixedResponse = [
    'message' => 'Synthetic mixed response.',
    'products' => [[
        'id' => 8,
        'name' => 'Synthetic Apple',
        'price' => 53000,
        'inventory' => 20,
        'is_active' => true,
        'category' => ['id' => 2, 'name' => 'Synthetic Fruit', 'is_active' => true],
    ]],
    'source' => 'multi-source',
    'composition' => 'parallel',
    'subresponses' => [
        ['source' => 'catalog', 'status' => 'answered'],
        ['source' => 'capability-guard', 'status' => 'denied', 'code' => 'ACTION_NOT_ALLOWED'],
    ],
];

return [
    'prediction' => $prediction,
    'mixed' => [
        'route' => $mixedRoute,
        'runtime' => $mixedRuntime,
        'response' => $mixedResponse,
    ],
];
