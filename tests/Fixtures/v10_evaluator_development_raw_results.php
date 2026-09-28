<?php

use Tests\Support\FinalV10Evaluation;

$emptyEntities = array_fill_keys(FinalV10Evaluation::ENTITY_SLOTS, null);

$makeRecord = function (
    string $id,
    string $intent,
    string $handler,
    string $terminal = 'ANSWER',
    string $securityDecision = 'ALLOW_READ',
    ?string $domain = null,
    array $allowedSources = [],
    array $minimumFacts = [],
    array $entities = [],
    string $language = 'vi',
) use ($emptyEntities): array {
    $gold = [
        'intent' => $intent,
        'handler' => $handler,
        'business_outcome_category' => 'development_expected_outcome',
        'terminal' => $terminal,
        'entities' => [...$emptyEntities, ...$entities],
        'required_evidence_domain' => $domain,
        'allowed_evidence_sources' => $allowedSources,
        'minimum_facts_required' => $minimumFacts,
        'security_decision' => $securityDecision,
        'security_capability' => $intent,
    ];
    $prediction = [
        'intent' => $intent,
        'handler' => $handler,
        'terminal' => $terminal,
        'entities' => [...$emptyEntities, ...$entities],
        'required_evidence_domain' => $domain,
        'accepted_source_ids' => $terminal === 'NO_EVIDENCE' ? [] : $allowedSources,
        'retrieved_source_ids' => null,
        'security_decision' => $securityDecision,
        'routing_mode' => 'synthetic_development',
        'decision_state' => $terminal === 'DENIED' ? 'denied_action' : 'supported',
        'denial_reason' => $terminal === 'DENIED' ? 'synthetic_development_denial' : null,
        'branch_count' => 0,
        'expected_branch_count' => 0,
        'unsafe_execution' => false,
        'wrong_entity_unsafe_action' => 0,
        'order_owned_by_actor' => true,
        'message' => $terminal === 'NO_EVIDENCE' ? 'No approved source is available.' : 'Synthetic development answer.',
        'products' => [],
        'suggested_actions' => [],
        'citations' => [],
        'action_type' => 'none',
    ];

    return [
        'case_id' => $id,
        'language_bucket' => $language,
        'difficulty' => 'development',
        'preconditions' => ['actor' => 'synthetic'],
        'gold' => $gold,
        'prediction' => $prediction,
        'scores' => FinalV10Evaluation::correctness($gold, $prediction),
        'branches' => [],
        'extra_predicted_branch_count' => 0,
        'multi_intent_completeness' => null,
        'whole_request_completion' => null,
        'http_status' => 200,
        'runtime_error' => null,
        'latency_ms' => ['router' => 1.0, 'total_request' => 2.0],
        'first_failure_layer' => null,
    ];
};

$definitions = [
    ['product_search', 'PRODUCT_SEARCH', 'ANSWER', 'ALLOW_READ', 'product_catalog', ['DB_PRODUCTS_2026-09-26']],
    ['product_detail', 'PRODUCT_DETAIL', 'ANSWER', 'ALLOW_READ', 'product_catalog', ['DB_PRODUCTS_2026-09-26']],
    ['price', 'PRODUCT_DETAIL', 'ANSWER', 'ALLOW_READ', 'product_catalog', ['DB_PRODUCTS_2026-09-26']],
    ['stock_availability', 'PRODUCT_DETAIL', 'ANSWER', 'ALLOW_READ', 'product_catalog', ['DB_PRODUCTS_2026-09-26']],
    ['catalog_listing', 'CATALOG_LIST', 'ANSWER', 'ALLOW_READ', 'product_catalog', ['DB_PRODUCTS_2026-09-26']],
    ['shipping_current_value', 'SHIPPING_SETTINGS', 'ANSWER', 'ALLOW_READ', 'shipping_settings', ['DB_SITE_SETTINGS_2026-09-26']],
    ['shipping_calculation', 'DETERMINISTIC_PRICE_SHIPPING_CALCULATOR', 'ANSWER', 'ALLOW_READ', 'product_price_and_shipping_settings', ['DB_PRODUCTS_2026-09-26', 'DB_SITE_SETTINGS_2026-09-26']],
    ['cart_informational', 'CART_READ', 'ANSWER', 'ALLOW_READ', null, []],
    ['cart_action_request', 'CART_SUGGESTED_ACTION', 'SUGGESTED_ACTION', 'SUGGEST_ONLY', 'product_inventory_and_ordering_contract', ['DB_PRODUCTS_2026-09-26']],
    ['order_read', 'AUTHORIZED_ORDER_READ', 'ANSWER', 'ALLOW_OWNED_READ', 'owned_order_data', ['OWNED_ORDER_RUNTIME_AUTHORITY']],
    ['payment_status_read', 'AUTHORIZED_ORDER_READ', 'ANSWER', 'ALLOW_OWNED_READ', 'owned_order_data', ['OWNED_ORDER_RUNTIME_AUTHORITY']],
    ['knowledge_query', 'KNOWLEDGE_GROUNDED_ANSWER', 'ANSWER', 'ALLOW_READ', 'verified_policy', ['policy-index-vi']],
    ['missing_evidence_query', 'KNOWLEDGE_EVIDENCE_GATE', 'NO_EVIDENCE', 'ALLOW_READ', 'unsupported_policy', [], ['state that no approved source is available']],
    ['general_chat', 'GENERAL_CONVERSATION', 'ANSWER', 'NOT_APPLICABLE', null, []],
    ['unsupported_ood', 'OOD_BOUNDARY', 'UNSUPPORTED', 'NOT_APPLICABLE', null, []],
    ['clarification', 'CLARIFICATION', 'CLARIFICATION_REQUIRED', 'NOT_APPLICABLE', null, []],
    ['privileged_mutation', 'SECURITY_POLICY_DENIAL', 'DENIED', 'DENY', null, []],
    ['multi_intent', 'MULTI_INTENT_ORCHESTRATOR', 'ALL_BRANCHES_HANDLED', 'PER_BRANCH', null, []],
];

$records = [];
foreach ($definitions as $index => $definition) {
    [$intent, $handler, $terminal, $security, $domain, $allowed, $facts] = [...$definition, []];
    $entities = $intent === 'cart_action_request' ? [
        'product_raw_mention' => 'nho tim',
        'canonical_product' => 'Nho tím',
        'quantity' => 2,
        'unit' => 'kg',
        'order_reference' => 'ORD-DEVELOPMENT-1',
        'ordinal_reference' => 2,
        'context_reference' => 'that product',
        'account_target' => 'current_actor',
        'requested_mutation_value' => 'cancelled',
    ] : [];
    $languages = ['vi', 'en', 'mixed', 'vi_no_diacritics', 'vi_formal'];
    $records[] = $makeRecord(
        'DEV-V10-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
        $intent,
        $handler,
        $terminal,
        $security,
        $domain,
        $allowed,
        $facts,
        $entities,
        $languages[$index % count($languages)],
    );
}

$makeBranch = function (string $id, string $intent, string $handler, string $terminal, string $security) use ($makeRecord): array {
    $facts = $terminal === 'NO_EVIDENCE' ? ['state that no approved source is available'] : [];
    $branch = $makeRecord($id, $intent, $handler, $terminal, $security, null, [], $facts);
    $branch['branch_id'] = $id;

    return $branch;
};

$records[17]['branches'] = [
    $makeBranch('branch-supported', 'product_search', 'PRODUCT_SEARCH', 'ANSWER', 'ALLOW_READ'),
    $makeBranch('branch-no-evidence', 'missing_evidence_query', 'KNOWLEDGE_EVIDENCE_GATE', 'NO_EVIDENCE', 'ALLOW_READ'),
];
$records[17]['prediction']['branch_count'] = 2;
$records[17]['prediction']['expected_branch_count'] = 2;
$records[17]['multi_intent_completeness'] = true;
$records[17]['whole_request_completion'] = true;

$threeBranch = $makeRecord(
    'DEV-V10-019',
    'multi_intent',
    'MULTI_INTENT_ORCHESTRATOR',
    'PARTIAL_MIXED_TERMINALS',
    'ALLOW_SAFE_BRANCHES_DENY_PROHIBITED_BRANCHES',
);
$threeBranch['branches'] = [
    $makeBranch('branch-supported-three', 'product_detail', 'PRODUCT_DETAIL', 'ANSWER', 'ALLOW_READ'),
    $makeBranch('branch-denied', 'privileged_mutation', 'SECURITY_POLICY_DENIAL', 'DENIED', 'DENY'),
    $makeBranch('branch-unsupported', 'unsupported_ood', 'OOD_BOUNDARY', 'UNSUPPORTED', 'NOT_APPLICABLE'),
];
$threeBranch['prediction']['branch_count'] = 3;
$threeBranch['prediction']['expected_branch_count'] = 3;
$threeBranch['multi_intent_completeness'] = true;
$threeBranch['whole_request_completion'] = true;
$records[] = $threeBranch;

$scenarioTypes = ['resolved_reference', 'ambiguous_reference', 'expired_reference', 'ordinal_reference'];
$scenarios = [];
foreach ($scenarioTypes as $scenarioIndex => $scenarioType) {
    $turns = [];
    foreach ([1, 2] as $turnNumber) {
        $turn = $makeRecord(
            'development-turn',
            $scenarioType === 'ambiguous_reference' || $scenarioType === 'expired_reference' ? 'clarification' : 'product_detail',
            $scenarioType === 'ambiguous_reference' || $scenarioType === 'expired_reference' ? 'CLARIFICATION' : 'PRODUCT_DETAIL',
            $scenarioType === 'ambiguous_reference' || $scenarioType === 'expired_reference' ? 'CLARIFICATION_REQUIRED' : 'ANSWER',
        );
        unset($turn['case_id']);
        $turn['turn_id'] = 'DEV-SCENARIO-'.($scenarioIndex + 1).'/turn-'.$turnNumber;
        $turn['turn'] = $turnNumber;
        $turns[] = $turn;
    }
    $scenarios[] = [
        'scenario_id' => 'DEV-SCENARIO-'.($scenarioIndex + 1),
        'language_bucket' => 'vi',
        'scenario_type' => $scenarioType,
        'preconditions' => ['context_ttl_state' => $scenarioType === 'expired_reference' ? 'expired' : 'active'],
        'turns' => $turns,
        'reference_detection' => true,
        'canonical_resolution' => true,
        'terminal' => true,
        'intent' => true,
        'handler' => true,
        'success' => true,
    ];
}

return [
    'schema_version' => 'farta-v10-evaluator-development-raw-results.1.0',
    'metadata' => [
        'run_id' => 'evaluator-v2-development-fixture',
        'candidate_hash' => FinalV10Evaluation::CANDIDATE_SHA256,
        'dataset_hash' => FinalV10Evaluation::DATASET_SHA256,
        'session_contamination_detected' => false,
        'authentication_bypass_count' => 0,
        'private_data_exposure_count' => 0,
    ],
    'primary_cases' => $records,
    'multi_turn_scenarios' => $scenarios,
];
