<?php

namespace Tests\Support;

final class FinalV10DatasetContract
{
    public const SCHEMA_VERSION = 'farta-v10.3.0';

    private const PRIMARY_REQUIRED = [
        'case_id', 'utterance', 'language_bucket', 'gold_intent', 'expected_handler',
        'expected_business_outcome_category', 'expected_terminal_state', 'entity_gold',
        'required_evidence_domain', 'allowed_evidence_sources', 'grounding_gold',
        'security_capability_expectation', 'preconditions', 'difficulty',
        'deterministic_gold', 'multi_intent_branches', 'notes',
    ];

    private const BRANCH_REQUIRED = [
        'branch_id', 'intent', 'operation', 'entity_gold', 'expected_handler',
        'required_evidence_domain', 'allowed_evidence_sources', 'expected_terminal_state',
        'expected_business_outcome_category', 'security_expectation',
        'minimum_facts_required', 'grounding_gold',
    ];

    private const SCENARIO_REQUIRED = [
        'scenario_id', 'language_bucket', 'scenario_type', 'preconditions', 'turns',
        'final_expected_intent', 'final_canonical_entity', 'expected_handler',
        'expected_terminal_state', 'required_evidence_domain', 'allowed_evidence_sources',
        'security_capability_expectation', 'notes',
    ];

    private const TURN_REQUIRED = [
        'turn', 'role', 'utterance', 'expected_intent', 'expected_handler',
        'expected_terminal_state', 'entity_gold', 'expected_context_after_turn',
    ];

    private const PRIMARY_INTENTS = [
        'cart_action_request', 'cart_informational', 'catalog_listing', 'clarification',
        'general_chat', 'knowledge_query', 'missing_evidence_query', 'multi_intent',
        'order_read', 'payment_status_read', 'price', 'privileged_mutation',
        'product_detail', 'product_search', 'shipping_calculation',
        'shipping_current_value', 'stock_availability', 'unsupported_ood',
    ];

    private const BRANCH_INTENTS = [
        'cart_action_request', 'catalog_listing', 'knowledge_query', 'missing_evidence_query',
        'order_read', 'payment_status_read', 'price', 'privileged_mutation',
        'product_detail', 'shipping_calculation', 'shipping_current_value',
        'stock_availability', 'store_contact', 'unsupported_ood',
    ];

    private const TURN_INTENTS = [
        'cart_action_request', 'clarification', 'price', 'product_detail',
        'product_search', 'shipping_calculation', 'stock_availability',
    ];

    private const HANDLERS = [
        'AUTHORIZED_ORDER_READ', 'AUTHORIZED_PAYMENT_STATUS_READ', 'CART_SUGGESTED_ACTION',
        'CATALOG_LIST', 'CLARIFICATION', 'DETERMINISTIC_PRICE_SHIPPING_CALCULATOR',
        'GENERAL_CONVERSATION', 'KNOWLEDGE_EVIDENCE_GATE', 'KNOWLEDGE_GROUNDED_ANSWER',
        'MULTI_INTENT_ORCHESTRATOR', 'OOD_BOUNDARY', 'PRODUCT_DETAIL', 'PRODUCT_PRICE',
        'PRODUCT_SEARCH', 'PRODUCT_STOCK', 'SECURITY_POLICY_DENIAL', 'SHIPPING_SETTINGS',
        'STORE_SETTINGS',
    ];

    private const TERMINALS = [
        'ANSWER', 'NO_RESULTS', 'SUGGESTED_ACTION', 'UNAVAILABLE', 'AUTH_REQUIRED',
        'DENIED', 'ANSWER_OR_NOT_FOUND', 'NO_EVIDENCE', 'UNSUPPORTED',
        'CLARIFICATION_REQUIRED', 'ALL_BRANCHES_HANDLED', 'PARTIAL_MIXED_TERMINALS',
    ];

    private const LANGUAGE_BUCKETS = [
        'vi_formal', 'vi_conversational', 'vi_no_diacritics', 'en', 'mixed',
    ];

    private const DIFFICULTIES = [
        'edge_case', 'moderately_ambiguous', 'normal_paraphrase', 'security_ood', 'straightforward',
    ];

    private const ACTORS = [
        'anonymous', 'anonymous_or_authenticated', 'authenticated_non_owner',
        'authenticated_owner', 'authenticated_verified_customer',
    ];

    private const SECURITY_DECISIONS = [
        'ALLOW_OWNED_READ', 'ALLOW_READ', 'ALLOW_SAFE_BRANCHES_DENY_PROHIBITED_BRANCHES',
        'DENY', 'DENY_CROSS_ACCOUNT', 'NOT_APPLICABLE', 'PER_BRANCH', 'REQUIRE_AUTH',
        'SUGGEST_ONLY',
    ];

    private const BRANCH_SECURITY_EXPECTATIONS = ['ALLOW', 'DENY', 'SUGGEST_ONLY'];

    private const SECURITY_CAPABILITIES = [
        'approved_business_knowledge', 'auth_bypass', 'contextual_product_read',
        'deterministic_public_calculation', 'domain_boundary', 'evidence_gated_business_knowledge',
        'evidence_gated_product_knowledge', 'inventory_mutation', 'multi_intent_branch_isolation',
        'no_direct_cart_mutation', 'order_state_mutation', 'other_user_data',
        'payment_state_mutation', 'public_product_catalog', 'public_store_settings',
        'read_only_order_access', 'read_only_payment_status', 'role_change',
        'safe_disambiguation', 'safe_social_conversation', 'secret_disclosure',
    ];

    private const OPERATIONS = [
        'answer_approved_knowledge', 'answer_policy', 'answer_weather', 'bypass_verification',
        'calculate_total', 'compare_quantity_to_inventory', 'cross_account_payment_read',
        'disclose_hidden_prompt', 'financial_advice', 'list_catalog', 'list_category',
        'mutate_inventory', 'mutate_order_state', 'mutate_payment_state',
        'read_current_inventory', 'read_current_price', 'read_free_shipping_threshold',
        'read_owned_order', 'read_owned_payment_status', 'read_product_detail',
        'read_shipping_fee', 'read_store_address', 'suggest_add_to_cart', 'write_code',
    ];

    private const SCENARIO_TYPES = [
        'ambiguous_reference', 'deictic_that', 'ellipsis', 'expired_context',
        'ordinal_first', 'ordinal_last', 'ordinal_second', 'plural_reference',
        'singular_reference',
    ];

    private const MUTATION_STRING_VALUES = ['+100', 'admin', 'cancelled', 'delivered', 'paid'];

    private const EVIDENCE_DOMAINS = [
        'account', 'bulk_order_policy', 'cold_chain_policy', 'delivery_sla',
        'express_delivery_policy', 'food_safety_certification',
        'fulfilment_compensation_policy', 'loyalty_policy', 'opened_food_return_policy',
        'ordering', 'orders', 'organic_certification', 'owned_order_data',
        'owned_order_payment_status', 'packaging_return_policy', 'payment', 'policy',
        'price_match_policy', 'privacy_policy', 'product_catalog', 'product_inventory',
        'product_inventory_and_ordering_contract', 'product_price',
        'product_price_and_shipping_settings', 'product_usage_suitability', 'refund_policy',
        'returns_policy', 'shipping_settings', 'store_contact_settings', 'subscription_policy',
        'supplier_provenance', 'traceability_policy', 'warranty_policy',
    ];

    private const BUSINESS_OUTCOMES = [
        'ANSWER_CART_PROCESS_FROM_APPROVED_GUIDE', 'ANSWER_FROM_APPROVED_KNOWLEDGE',
        'ASK_TARGETED_CLARIFYING_QUESTION', 'BRIEF_SOCIAL_RESPONSE_OR_CAPABILITY_GUIDANCE',
        'CLARIFY_PRICE_RANGE', 'COMPARE_REQUESTED_QUANTITY_TO_STOCK', 'DENY_AUTH_BYPASS',
        'DENY_CHATBOT_ORDER_MUTATION', 'DENY_CROSS_ACCOUNT_READ', 'DENY_INVENTORY_MUTATION',
        'DENY_PAYMENT_STATE_MUTATION', 'DENY_PROHIBITED_CAPABILITY', 'DENY_SECRET_DISCLOSURE',
        'DO_NOT_ASSERT_UNSOURCED_POLICY', 'DO_NOT_ASSERT_UNSOURCED_PRODUCT_CLAIM',
        'DO_NOT_FORCE_INTO_COMMERCE_INTENT', 'LIST_OWN_ORDERS', 'NO_MATCH',
        'PROCESS_EVERY_BRANCH_WITH_EXPLICIT_TERMINAL_STATE', 'READ_OWN_ORDER',
        'READ_OWN_ORDER_OR_NOT_FOUND', 'READ_OWN_PAYMENT_STATUS', 'REJECT_QUANTITY_ABOVE_CURRENT_STOCK',
        'REQUIRE_AUTHENTICATION', 'RETURN_ACTIVE_CATALOG', 'RETURN_ACTIVE_CATEGORY_PRODUCTS',
        'RETURN_ADD_TO_CART_SUGGESTION_WITH_CONFIRMATION', 'RETURN_CURRENT_AVAILABILITY',
        'RETURN_CURRENT_SHIPPING_RULE', 'RETURN_CURRENT_UNIT_PRICE',
        'RETURN_DETERMINISTIC_SUBTOTAL_SHIPPING_TOTAL', 'RETURN_GROUNDED_PRODUCT_DETAILS',
        'RETURN_MATCHING_ACTIVE_PRODUCTS', 'address_vi=213 Trương Định, Nghệ An',
        'available=true', 'free_shipping_threshold_vnd=200000', 'price_vnd=53000',
        'shipping_fee_vnd=20000', 'subtotal=180000; shipping=20000; total=200000',
        'subtotal=212000; shipping=0; total=212000',
        'subtotal=240000; shipping=0; total=240000',
        'subtotal=64000; shipping=20000; total=84000',
        'subtotal=89000; shipping=20000; total=109000',
    ];

    /** @return array<string, mixed> */
    public static function load(string $path): array
    {
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return self::parse($decoded);
    }

    /** @param mixed $dataset @return array<string, mixed> */
    public static function parse(mixed $dataset): array
    {
        self::object($dataset, '$');
        self::enum($dataset['schema_version'] ?? null, [self::SCHEMA_VERSION], '$.schema_version');
        self::list($dataset['primary_cases'] ?? null, '$.primary_cases');
        self::list($dataset['multi_turn_scenarios'] ?? null, '$.multi_turn_scenarios');
        self::list($dataset['source_registry'] ?? null, '$.source_registry');

        $sourceVersions = self::validateSourceRegistry($dataset['source_registry']);
        self::validateDeclaredSchema($dataset['schema_contract'] ?? null);

        $primary = [];
        $caseIds = [];
        $branchCount = 0;
        $multiIntentCount = 0;
        foreach ($dataset['primary_cases'] as $index => $record) {
            $path = '$.primary_cases['.$index.']';
            self::object($record, $path);
            self::required($record, self::PRIMARY_REQUIRED, $path);
            $caseId = self::nonEmptyString($record['case_id'], $path.'.case_id');
            self::uniqueId($caseId, $caseIds, $path.'.case_id');
            self::nonEmptyString($record['utterance'], $path.'.utterance');
            self::enum($record['language_bucket'], self::LANGUAGE_BUCKETS, $path.'.language_bucket');
            self::enum($record['gold_intent'], self::PRIMARY_INTENTS, $path.'.gold_intent');
            self::enum($record['expected_handler'], self::HANDLERS, $path.'.expected_handler');
            self::enum($record['expected_business_outcome_category'], self::BUSINESS_OUTCOMES, $path.'.expected_business_outcome_category');
            self::enum($record['expected_terminal_state'], self::TERMINALS, $path.'.expected_terminal_state');
            self::entity($record['entity_gold'], $path.'.entity_gold');
            self::domain($record['required_evidence_domain'], $path.'.required_evidence_domain');
            self::sources($record['allowed_evidence_sources'], $sourceVersions, $path.'.allowed_evidence_sources');
            self::grounding($record['grounding_gold'], $record, $sourceVersions, $path.'.grounding_gold');
            self::securityCapability($record['security_capability_expectation'], $path.'.security_capability_expectation');
            self::preconditions($record['preconditions'], $path.'.preconditions', true);
            self::enum($record['difficulty'], self::DIFFICULTIES, $path.'.difficulty');
            self::deterministicGold($record['deterministic_gold'], $path.'.deterministic_gold');
            self::nullableString($record['notes'], $path.'.notes');
            self::sourceVersions($record['allowed_evidence_source_versions'] ?? null, $record['allowed_evidence_sources'], $sourceVersions, $path.'.allowed_evidence_source_versions', true);
            self::list($record['multi_intent_branches'], $path.'.multi_intent_branches');

            if ($record['gold_intent'] === 'multi_intent') {
                if ($record['multi_intent_branches'] === []) {
                    self::fail($path.'.multi_intent_branches', 'must not be empty for multi_intent');
                }
                $multiIntentCount++;
            } elseif ($record['multi_intent_branches'] !== []) {
                self::fail($path.'.multi_intent_branches', 'must be empty unless gold_intent is multi_intent');
            }

            $branches = [];
            $branchIds = [];
            foreach ($record['multi_intent_branches'] as $branchIndex => $branch) {
                $branchPath = $path.'.multi_intent_branches['.$branchIndex.']';
                $branches[] = self::parseBranch($branch, $branchPath, $sourceVersions, $branchIds);
                $branchCount++;
            }
            $primary[] = [
                'case_id' => $caseId,
                'language_bucket' => $record['language_bucket'],
                'difficulty' => $record['difficulty'],
                'preconditions' => $record['preconditions'],
                'gold' => self::primaryGold($record),
                'branches' => $branches,
            ];
        }

        $scenarios = [];
        $scenarioIds = [];
        $turnCount = 0;
        foreach ($dataset['multi_turn_scenarios'] as $index => $scenario) {
            $path = '$.multi_turn_scenarios['.$index.']';
            self::object($scenario, $path);
            self::required($scenario, self::SCENARIO_REQUIRED, $path);
            $scenarioId = self::nonEmptyString($scenario['scenario_id'], $path.'.scenario_id');
            self::uniqueId($scenarioId, $scenarioIds, $path.'.scenario_id');
            self::enum($scenario['language_bucket'], self::LANGUAGE_BUCKETS, $path.'.language_bucket');
            self::enum($scenario['scenario_type'], self::SCENARIO_TYPES, $path.'.scenario_type');
            self::preconditions($scenario['preconditions'], $path.'.preconditions', false);
            self::enum($scenario['final_expected_intent'], self::TURN_INTENTS, $path.'.final_expected_intent');
            self::entityValue($scenario['final_canonical_entity'], 'canonical_product', $path.'.final_canonical_entity');
            self::enum($scenario['expected_handler'], self::HANDLERS, $path.'.expected_handler');
            self::enum($scenario['expected_terminal_state'], self::TERMINALS, $path.'.expected_terminal_state');
            self::domain($scenario['required_evidence_domain'], $path.'.required_evidence_domain');
            self::sources($scenario['allowed_evidence_sources'], $sourceVersions, $path.'.allowed_evidence_sources');
            self::securityCapability($scenario['security_capability_expectation'], $path.'.security_capability_expectation');
            self::nullableString($scenario['notes'], $path.'.notes');
            self::sourceVersions($scenario['allowed_evidence_source_versions'] ?? null, $scenario['allowed_evidence_sources'], $sourceVersions, $path.'.allowed_evidence_source_versions', true);
            if (array_key_exists('grounding_gold', $scenario)) {
                self::grounding($scenario['grounding_gold'], $scenario, $sourceVersions, $path.'.grounding_gold');
            }
            self::list($scenario['turns'], $path.'.turns');
            if (count($scenario['turns']) !== 2) {
                self::fail($path.'.turns', 'must contain exactly the observed two-turn contract');
            }

            $turns = [];
            foreach ($scenario['turns'] as $turnIndex => $turn) {
                $turns[] = self::parseTurn($turn, $path.'.turns['.$turnIndex.']', $turnIndex + 1);
                $turnCount++;
            }
            $scenarios[] = [
                'scenario_id' => $scenarioId,
                'language_bucket' => $scenario['language_bucket'],
                'scenario_type' => $scenario['scenario_type'],
                'preconditions' => $scenario['preconditions'],
                'turns' => $turns,
                'final_expected_intent' => $scenario['final_expected_intent'],
                'final_canonical_entity' => $scenario['final_canonical_entity'],
                'expected_handler' => $scenario['expected_handler'],
                'expected_terminal_state' => $scenario['expected_terminal_state'],
            ];
        }

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'counts' => [
                'primary' => count($primary),
                'multi_turn_scenarios' => count($scenarios),
                'scenario_turns' => $turnCount,
                'multi_intent_cases' => $multiIntentCount,
                'multi_intent_branches' => $branchCount,
            ],
            'primary_cases' => $primary,
            'multi_turn_scenarios' => $scenarios,
        ];
    }

    /** @param array<string, mixed> $record @return array<string, mixed> */
    public static function primaryGold(array $record): array
    {
        return [
            'intent' => $record['gold_intent'],
            'handler' => $record['expected_handler'],
            'business_outcome_category' => $record['expected_business_outcome_category'],
            'terminal' => $record['expected_terminal_state'],
            'entities' => $record['entity_gold'],
            'required_evidence_domain' => $record['required_evidence_domain'],
            'allowed_evidence_sources' => $record['allowed_evidence_sources'],
            'minimum_facts_required' => $record['grounding_gold']['minimum_facts_required'],
            'security_decision' => $record['security_capability_expectation']['decision'],
            'security_capability' => $record['security_capability_expectation']['capability'],
        ];
    }

    /** @param array<string, mixed> $record @return array<string, mixed> */
    public static function branchGold(array $record, string $path = '$.branch'): array
    {
        $security = self::branchSecurityExpectation($record['security_expectation'] ?? null, $path.'.security_expectation');

        return [
            'intent' => $record['intent'],
            'handler' => $record['expected_handler'],
            'business_outcome_category' => $record['expected_business_outcome_category'],
            'terminal' => $record['expected_terminal_state'],
            'entities' => $record['entity_gold'],
            'required_evidence_domain' => $record['required_evidence_domain'],
            'allowed_evidence_sources' => $record['allowed_evidence_sources'],
            'minimum_facts_required' => $record['minimum_facts_required'],
            'security_decision' => $security,
            'security_capability' => null,
        ];
    }

    public static function branchSecurityExpectation(mixed $value, string $path = '$.security_expectation'): string
    {
        self::enum($value, self::BRANCH_SECURITY_EXPECTATIONS, $path);

        return $value;
    }

    /** @param array<string, mixed> $dataset @return array<string, mixed> */
    public static function inventory(array $dataset): array
    {
        $groups = [
            'primary' => [$dataset['primary_cases'], self::PRIMARY_REQUIRED],
            'branch' => [[...array_merge(...array_map(fn (array $case): array => $case['multi_intent_branches'], $dataset['primary_cases']))], self::BRANCH_REQUIRED],
            'scenario' => [$dataset['multi_turn_scenarios'], self::SCENARIO_REQUIRED],
            'turn' => [[...array_merge(...array_map(fn (array $scenario): array => $scenario['turns'], $dataset['multi_turn_scenarios']))], self::TURN_REQUIRED],
        ];
        $fields = [];
        foreach ($groups as $scope => [$records, $required]) {
            $names = [];
            foreach ($records as $record) {
                $names = [...$names, ...array_keys($record)];
            }
            $names = array_values(array_unique($names));
            sort($names);
            foreach ($names as $name) {
                $values = [];
                $present = 0;
                foreach ($records as $record) {
                    if (array_key_exists($name, $record)) {
                        $present++;
                        $values[] = $record[$name];
                    }
                }
                $types = array_values(array_unique(array_map(fn (mixed $value): string => self::jsonType($value, $name), $values)));
                sort($types);
                $scalarValues = array_values(array_unique(array_map(
                    fn (mixed $value): string => $value === null ? 'null' : (is_bool($value) ? ($value ? 'true' : 'false') : (string) $value),
                    array_filter($values, fn (mixed $value): bool => $value === null || is_scalar($value))
                )));
                sort($scalarValues);
                $fields[] = [
                    'field' => $name,
                    'scope' => $scope,
                    'locations_present' => $present,
                    'records_in_scope' => count($records),
                    'required' => in_array($name, $required, true),
                    'nullable' => in_array('null', $types, true),
                    'observed_json_types' => $types,
                    'observed_scalar_values' => count($scalarValues) <= 100 ? $scalarValues : [],
                ];
            }
        }

        foreach (['primary', 'branch', 'turn'] as $scope) {
            $records = match ($scope) {
                'primary' => $dataset['primary_cases'],
                'branch' => array_merge(...array_map(fn (array $case): array => $case['multi_intent_branches'], $dataset['primary_cases'])),
                'turn' => array_merge(...array_map(fn (array $scenario): array => $scenario['turns'], $dataset['multi_turn_scenarios'])),
            };
            foreach (FinalV10Evaluation::ENTITY_SLOTS as $slot) {
                $values = array_map(fn (array $record): mixed => $record['entity_gold'][$slot], $records);
                $types = array_values(array_unique(array_map(fn (mixed $value): string => self::jsonType($value, $slot), $values)));
                sort($types);
                $scalarValues = array_values(array_unique(array_map(
                    fn (mixed $value): string => $value === null ? 'null' : (string) $value,
                    array_filter($values, fn (mixed $value): bool => $value === null || is_scalar($value))
                )));
                sort($scalarValues);
                $fields[] = [
                    'field' => 'entity_gold.'.$slot,
                    'scope' => $scope,
                    'locations_present' => count($values),
                    'records_in_scope' => count($records),
                    'required' => true,
                    'nullable' => in_array('null', $types, true),
                    'observed_json_types' => $types,
                    'observed_scalar_values' => count($scalarValues) <= 100 ? $scalarValues : [],
                ];
            }
        }

        return [
            'schema_version' => 'farta-v10-evaluator-v3-contract-inventory.1.0',
            'frozen_dataset_schema' => self::SCHEMA_VERSION,
            'counts' => [
                'primary' => count($dataset['primary_cases']),
                'scenarios' => count($dataset['multi_turn_scenarios']),
                'turns' => array_sum(array_map(fn (array $scenario): int => count($scenario['turns']), $dataset['multi_turn_scenarios'])),
                'multi_intent_cases' => count(array_filter($dataset['primary_cases'], fn (array $case): bool => $case['multi_intent_branches'] !== [])),
                'branches' => array_sum(array_map(fn (array $case): int => count($case['multi_intent_branches']), $dataset['primary_cases'])),
            ],
            'security_expectation_contract' => [
                'primary' => ['shape' => 'object', 'fields' => ['decision', 'capability']],
                'branch' => ['shape' => 'string', 'values' => self::BRANCH_SECURITY_EXPECTATIONS],
                'scenario' => ['shape' => 'object', 'fields' => ['decision', 'capability']],
            ],
            'enum_contracts' => [
                'primary_intent' => self::PRIMARY_INTENTS,
                'branch_intent' => self::BRANCH_INTENTS,
                'turn_intent' => self::TURN_INTENTS,
                'handler' => self::HANDLERS,
                'terminal' => self::TERMINALS,
                'language_bucket' => self::LANGUAGE_BUCKETS,
                'difficulty' => self::DIFFICULTIES,
                'actor' => self::ACTORS,
                'security_decision' => self::SECURITY_DECISIONS,
                'security_capability' => self::SECURITY_CAPABILITIES,
                'branch_security_expectation' => self::BRANCH_SECURITY_EXPECTATIONS,
                'operation' => self::OPERATIONS,
                'evidence_domain' => self::EVIDENCE_DOMAINS,
                'scenario_type' => self::SCENARIO_TYPES,
                'requested_mutation_string_value' => self::MUTATION_STRING_VALUES,
            ],
            'fields' => $fields,
        ];
    }

    /** @param array<string, mixed> $branch @param array<string, int|null> $sourceVersions @param array<string, true> $ids @return array<string, mixed> */
    private static function parseBranch(array $branch, string $path, array $sourceVersions, array &$ids): array
    {
        self::required($branch, self::BRANCH_REQUIRED, $path);
        $branchId = self::nonEmptyString($branch['branch_id'], $path.'.branch_id');
        self::uniqueId($branchId, $ids, $path.'.branch_id');
        self::enum($branch['intent'], self::BRANCH_INTENTS, $path.'.intent');
        self::enum($branch['operation'], self::OPERATIONS, $path.'.operation');
        self::entity($branch['entity_gold'], $path.'.entity_gold');
        self::enum($branch['expected_handler'], self::HANDLERS, $path.'.expected_handler');
        self::domain($branch['required_evidence_domain'], $path.'.required_evidence_domain');
        self::sources($branch['allowed_evidence_sources'], $sourceVersions, $path.'.allowed_evidence_sources');
        self::enum($branch['expected_terminal_state'], self::TERMINALS, $path.'.expected_terminal_state');
        self::enum($branch['expected_business_outcome_category'], self::BUSINESS_OUTCOMES, $path.'.expected_business_outcome_category');
        self::branchSecurityExpectation($branch['security_expectation'], $path.'.security_expectation');
        self::stringList($branch['minimum_facts_required'], $path.'.minimum_facts_required');
        self::grounding($branch['grounding_gold'], $branch, $sourceVersions, $path.'.grounding_gold');
        if (array_key_exists('preconditions', $branch)) {
            self::preconditions($branch['preconditions'], $path.'.preconditions', false);
        }
        self::sourceVersions($branch['allowed_evidence_source_versions'] ?? null, $branch['allowed_evidence_sources'], $sourceVersions, $path.'.allowed_evidence_source_versions', true);

        return ['branch_id' => $branchId, 'gold' => self::branchGold($branch, $path)];
    }

    /** @param array<string, mixed> $turn @return array<string, mixed> */
    private static function parseTurn(array $turn, string $path, int $expectedTurn): array
    {
        self::object($turn, $path);
        self::required($turn, self::TURN_REQUIRED, $path);
        if (! is_int($turn['turn']) || $turn['turn'] !== $expectedTurn) {
            self::fail($path.'.turn', 'must be integer '.$expectedTurn);
        }
        self::enum($turn['role'], ['user'], $path.'.role');
        self::nonEmptyString($turn['utterance'], $path.'.utterance');
        self::enum($turn['expected_intent'], self::TURN_INTENTS, $path.'.expected_intent');
        self::enum($turn['expected_handler'], self::HANDLERS, $path.'.expected_handler');
        self::enum($turn['expected_terminal_state'], self::TERMINALS, $path.'.expected_terminal_state');
        self::entity($turn['entity_gold'], $path.'.entity_gold');
        self::context($turn['expected_context_after_turn'], $path.'.expected_context_after_turn');
        if (array_key_exists('context_state_before_turn', $turn)) {
            if ($turn['context_state_before_turn'] !== null) {
                self::enum($turn['context_state_before_turn'], ['ACTIVE', 'EXPIRED'], $path.'.context_state_before_turn');
            }
        }

        return [
            'turn' => $turn['turn'],
            'utterance' => $turn['utterance'],
            'gold' => [
                'intent' => $turn['expected_intent'],
                'handler' => $turn['expected_handler'],
                'terminal' => $turn['expected_terminal_state'],
                'entities' => $turn['entity_gold'],
                'required_evidence_domain' => null,
                'allowed_evidence_sources' => [],
                'minimum_facts_required' => [],
                'security_decision' => 'ALLOW_READ',
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $registry @return array<string, int|null> */
    private static function validateSourceRegistry(array $registry): array
    {
        $versions = [];
        foreach ($registry as $index => $source) {
            $path = '$.source_registry['.$index.']';
            self::object($source, $path);
            self::required($source, ['source_id', 'authority_type'], $path);
            $id = self::nonEmptyString($source['source_id'], $path.'.source_id');
            if (array_key_exists($id, $versions)) {
                self::fail($path.'.source_id', 'duplicate source ID '.$id);
            }
            $version = $source['version'] ?? null;
            if ($version !== null && (! is_int($version) || $version < 1)) {
                self::fail($path.'.version', 'must be a positive integer or null');
            }
            $facts = $source['facts'] ?? null;
            if ($facts !== null && ! is_array($facts)) {
                self::fail($path.'.facts', 'must be array/object or null');
            }
            $versions[$id] = $version;
        }

        return $versions;
    }

    private static function validateDeclaredSchema(mixed $schema): void
    {
        self::object($schema, '$.schema_contract');
        self::stringList($schema['primary_case_required_fields'] ?? null, '$.schema_contract.primary_case_required_fields');
        self::stringList($schema['multi_intent_branch_required_fields'] ?? null, '$.schema_contract.multi_intent_branch_required_fields');
        self::stringList($schema['entity_gold_slots'] ?? null, '$.schema_contract.entity_gold_slots');
        self::stringList($schema['terminal_state_values'] ?? null, '$.schema_contract.terminal_state_values');
        if ($schema['primary_case_required_fields'] !== self::PRIMARY_REQUIRED) {
            self::fail('$.schema_contract.primary_case_required_fields', 'does not match evaluator V3 frozen contract');
        }
        if ($schema['multi_intent_branch_required_fields'] !== self::BRANCH_REQUIRED) {
            self::fail('$.schema_contract.multi_intent_branch_required_fields', 'does not match evaluator V3 frozen contract');
        }
        if ($schema['entity_gold_slots'] !== FinalV10Evaluation::ENTITY_SLOTS) {
            self::fail('$.schema_contract.entity_gold_slots', 'does not match evaluator entity slots');
        }
        if ($schema['terminal_state_values'] !== self::TERMINALS) {
            self::fail('$.schema_contract.terminal_state_values', 'does not match evaluator terminal enum');
        }
    }

    /** @param array<string, mixed> $entity */
    private static function entity(mixed $entity, string $path): void
    {
        self::object($entity, $path);
        $keys = array_keys($entity);
        sort($keys);
        $expected = FinalV10Evaluation::ENTITY_SLOTS;
        sort($expected);
        if ($keys !== $expected) {
            self::fail($path, 'must contain exactly the nine evaluator entity slots');
        }
        foreach (FinalV10Evaluation::ENTITY_SLOTS as $slot) {
            self::entityValue($entity[$slot], $slot, $path.'.'.$slot);
        }
    }

    private static function entityValue(mixed $value, string $slot, string $path): void
    {
        if ($value === null) {
            return;
        }
        if (in_array($slot, ['product_raw_mention', 'canonical_product'], true) && is_array($value)) {
            self::stringList($value, $path, false);

            return;
        }
        if ($slot === 'quantity') {
            if (! is_int($value) && ! is_float($value)) {
                self::fail($path, 'must be a number or null');
            }
            if ($value <= 0) {
                self::fail($path, 'must be greater than zero when present');
            }

            return;
        }
        if ($slot === 'ordinal_reference') {
            if (! is_int($value) || $value === 0) {
                self::fail($path, 'must be a non-zero integer or null');
            }

            return;
        }
        if ($slot === 'requested_mutation_value' && (is_int($value) || is_float($value))) {
            return;
        }
        if ($slot === 'requested_mutation_value') {
            self::enum($value, self::MUTATION_STRING_VALUES, $path);

            return;
        }
        self::nonEmptyString($value, $path);
    }

    /** @param array<string, mixed> $outer @param array<string, int|null> $sourceVersions */
    private static function grounding(mixed $grounding, array $outer, array $sourceVersions, string $path): void
    {
        self::object($grounding, $path);
        self::required($grounding, ['required_evidence_domain', 'allowed_source_ids', 'expected_terminal_state', 'minimum_facts_required'], $path);
        self::domain($grounding['required_evidence_domain'], $path.'.required_evidence_domain');
        self::sources($grounding['allowed_source_ids'], $sourceVersions, $path.'.allowed_source_ids');
        self::enum($grounding['expected_terminal_state'], self::TERMINALS, $path.'.expected_terminal_state');
        self::stringList($grounding['minimum_facts_required'], $path.'.minimum_facts_required');
        self::sourceVersions($grounding['allowed_source_versions'] ?? null, $grounding['allowed_source_ids'], $sourceVersions, $path.'.allowed_source_versions', true);
        if ($grounding['required_evidence_domain'] !== $outer['required_evidence_domain']) {
            self::fail($path.'.required_evidence_domain', 'must equal outer required_evidence_domain');
        }
        if ($grounding['allowed_source_ids'] !== $outer['allowed_evidence_sources']) {
            self::fail($path.'.allowed_source_ids', 'must equal outer allowed_evidence_sources');
        }
        if ($grounding['expected_terminal_state'] !== $outer['expected_terminal_state']) {
            self::fail($path.'.expected_terminal_state', 'must equal outer expected_terminal_state');
        }
        if (array_key_exists('minimum_facts_required', $outer) && $grounding['minimum_facts_required'] !== $outer['minimum_facts_required']) {
            self::fail($path.'.minimum_facts_required', 'must equal outer minimum_facts_required');
        }
    }

    /** @param array<string, int|null> $registry */
    private static function sources(mixed $sources, array $registry, string $path): void
    {
        self::stringList($sources, $path);
        if (count($sources) !== count(array_unique($sources))) {
            self::fail($path, 'must not contain duplicate source IDs');
        }
        foreach ($sources as $index => $source) {
            if (! array_key_exists($source, $registry)) {
                self::fail($path.'['.$index.']', 'unknown source ID '.$source);
            }
        }
    }

    /** @param array<int, string> $allowed @param array<string, int|null> $registry */
    private static function sourceVersions(mixed $versions, array $allowed, array $registry, string $path, bool $nullable): void
    {
        if ($versions === null && $nullable) {
            return;
        }
        self::object($versions, $path);
        foreach ($versions as $source => $version) {
            if (! is_string($source) || ! in_array($source, $allowed, true)) {
                self::fail($path, 'contains a source not present in allowed sources');
            }
            if (! is_int($version) || $version < 1) {
                self::fail($path.'.'.$source, 'must be a positive integer');
            }
            if (($registry[$source] ?? null) !== $version) {
                self::fail($path.'.'.$source, 'does not match source registry version');
            }
        }
    }

    private static function securityCapability(mixed $security, string $path): void
    {
        self::object($security, $path);
        self::required($security, ['decision', 'capability'], $path);
        self::enum($security['decision'], self::SECURITY_DECISIONS, $path.'.decision');
        self::enum($security['capability'], self::SECURITY_CAPABILITIES, $path.'.capability');
    }

    private static function preconditions(mixed $preconditions, string $path, bool $allowSymbol): void
    {
        self::object($preconditions, $path);
        self::enum($preconditions['actor'] ?? null, self::ACTORS, $path.'.actor');
        if (array_key_exists('symbolic_order_reference', $preconditions)) {
            if (! $allowSymbol) {
                self::fail($path.'.symbolic_order_reference', 'is not allowed in this scope');
            }
            self::nullableString($preconditions['symbolic_order_reference'], $path.'.symbolic_order_reference');
        }
        if (array_key_exists('context_ttl_state', $preconditions)) {
            self::enum($preconditions['context_ttl_state'], ['active', 'expired'], $path.'.context_ttl_state');
        }
    }

    private static function deterministicGold(mixed $gold, string $path): void
    {
        if ($gold === null) {
            return;
        }
        self::object($gold, $path);
        foreach ($gold as $key => $value) {
            if (! in_array($key, [
                'active_only', 'available', 'canonical_product', 'current_unit_price_vnd',
                'expected_canonical_matches', 'expected_result_cardinality', 'filters',
                'final_total_vnd', 'free_shipping_applies', 'quantity', 'requested_quantity',
                'shipping_fee_vnd', 'subtotal_vnd', 'unit_price_vnd', 'unresolved_filter',
            ], true)) {
                self::fail($path.'.'.$key, 'unknown deterministic gold field');
            }
            if (! is_scalar($value) && ! is_array($value) && $value !== null) {
                self::fail($path.'.'.$key, 'must be JSON scalar, array, object, or null');
            }
        }
    }

    private static function context(mixed $context, string $path): void
    {
        self::object($context, $path);
        self::required($context, ['status', 'canonical_products_in_order'], $path);
        self::enum($context['status'], ['ACTIVE', 'AMBIGUOUS', 'EXPIRED'], $path.'.status');
        self::stringList($context['canonical_products_in_order'], $path.'.canonical_products_in_order');
        if (array_key_exists('focused_product', $context)) {
            self::nullableString($context['focused_product'], $path.'.focused_product');
        }
    }

    private static function domain(mixed $value, string $path): void
    {
        if ($value !== null) {
            self::enum($value, self::EVIDENCE_DOMAINS, $path);
        }
    }

    /** @param array<string, mixed> $record @param array<int, string> $fields */
    private static function required(array $record, array $fields, string $path): void
    {
        foreach ($fields as $field) {
            if (! array_key_exists($field, $record)) {
                self::fail($path.'.'.$field, 'missing required field');
            }
        }
    }

    /** @param array<int, mixed> $values */
    private static function stringList(mixed $values, string $path, bool $allowEmpty = true): void
    {
        self::list($values, $path);
        if (! $allowEmpty && $values === []) {
            self::fail($path, 'must not be empty');
        }
        foreach ($values as $index => $value) {
            self::nonEmptyString($value, $path.'['.$index.']');
        }
    }

    private static function list(mixed $value, string $path): void
    {
        if (! is_array($value) || ! array_is_list($value)) {
            self::fail($path, 'must be a JSON array');
        }
    }

    private static function object(mixed $value, string $path): void
    {
        if (! is_array($value) || array_is_list($value)) {
            self::fail($path, 'must be a JSON object');
        }
    }

    /** @param array<int, string> $allowed */
    private static function enum(mixed $value, array $allowed, string $path): void
    {
        if (! is_string($value)) {
            self::fail($path, 'must be string; got '.get_debug_type($value));
        }
        if (! in_array($value, $allowed, true)) {
            self::fail($path, 'unknown enum value '.json_encode($value));
        }
    }

    private static function nonEmptyString(mixed $value, string $path): string
    {
        if (! is_string($value) || $value === '') {
            self::fail($path, 'must be a non-empty string; got '.get_debug_type($value));
        }

        return $value;
    }

    private static function nullableString(mixed $value, string $path): void
    {
        if ($value !== null) {
            self::nonEmptyString($value, $path);
        }
    }

    /** @param array<string, true> $ids */
    private static function uniqueId(string $id, array &$ids, string $path): void
    {
        if (isset($ids[$id])) {
            self::fail($path, 'duplicate ID '.$id);
        }
        $ids[$id] = true;
    }

    private static function jsonType(mixed $value, string $field): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_array($value)) {
            if (in_array($field, [
                'allowed_evidence_sources', 'multi_intent_branches', 'minimum_facts_required',
                'turns', 'canonical_products_in_order', 'expected_canonical_matches',
                'product_raw_mention', 'canonical_product',
            ], true)) {
                return 'array';
            }

            return array_is_list($value) ? 'array' : 'object';
        }
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value) || is_float($value)) {
            return 'number';
        }

        return 'string';
    }

    private static function fail(string $path, string $message): never
    {
        throw new FinalV10ContractException($path.': '.$message);
    }
}
