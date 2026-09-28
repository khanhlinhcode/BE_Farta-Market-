<?php

use App\Enums\ChatIntent;
use App\Services\Chat\ChatRouteFrame;
use Tests\Support\FinalV10Evaluation;
use Tests\Support\FinalV10Metrics;
use Tests\Support\FinalV10Report;

function finalV10Route(ChatIntent $intent, string $state = 'supported'): ChatRouteFrame
{
    return new ChatRouteFrame(
        intent: $intent,
        query: 'synthetic',
        filters: ['category' => null, 'min_price' => null, 'max_price' => null, 'in_stock' => null],
        entities: ['topic' => 'product', 'order_id' => '', 'product_name' => '', 'quantity' => 0],
        requiresAuth: false,
        confidence: 0.98,
        routingMode: 'deterministic',
        clarificationReason: null,
        needsClarification: false,
        decisionState: $state,
        denialReason: $state === 'denied_action' ? 'synthetic_denial' : null,
    );
}

function finalV10SyntheticRecord(string $id, bool $correct): array
{
    $intent = $correct ? 'product_search' : 'unsupported_ood';

    return [
        'case_id' => $id,
        'language_bucket' => 'en',
        'gold' => [
            'intent' => 'product_search',
            'handler' => 'PRODUCT_SEARCH',
            'terminal' => 'ANSWER',
            'entities' => array_fill_keys(FinalV10Evaluation::ENTITY_SLOTS, null),
            'required_evidence_domain' => null,
            'allowed_evidence_sources' => [],
            'minimum_facts_required' => [],
            'security_decision' => 'ALLOW_READ',
        ],
        'prediction' => [
            'intent' => $intent,
            'handler' => $correct ? 'PRODUCT_SEARCH' : 'OOD_BOUNDARY',
            'terminal' => $correct ? 'ANSWER' : 'UNSUPPORTED',
            'entities' => array_fill_keys(FinalV10Evaluation::ENTITY_SLOTS, null),
            'required_evidence_domain' => null,
            'accepted_source_ids' => [],
            'security_decision' => $correct ? 'ALLOW_READ' : 'NOT_APPLICABLE',
            'unsafe_execution' => false,
            'wrong_entity_unsafe_action' => 0,
            'products' => [],
            'suggested_actions' => [],
            'citations' => [],
        ],
        'scores' => [
            'intent' => $correct,
            'handler' => $correct,
            'terminal' => $correct,
            'business_outcome' => $correct,
            'required_evidence_domain' => true,
            'evidence_eligibility' => true,
            'unexpected_accepted_source_ids' => [],
            'claim_support' => true,
            'response_completeness' => true,
            'security_decision' => $correct,
            'entity' => true,
            'entity_slots' => array_fill_keys(FinalV10Evaluation::ENTITY_SLOTS, [
                'applicable' => false,
                'correct' => true,
                'status' => 'not_applicable',
            ]),
        ],
        'branches' => [],
        'latency_ms' => ['router' => 1, 'total_request' => 2],
        'runtime_error' => null,
    ];
}

function finalV10SyntheticRaw(array $records): array
{
    return [
        'metadata' => [
            'candidate_hash' => FinalV10Evaluation::CANDIDATE_SHA256,
            'dataset_hash' => FinalV10Evaluation::DATASET_SHA256,
            'session_contamination_detected' => false,
        ],
        'primary_cases' => $records,
        'multi_turn_scenarios' => [],
    ];
}

it('filters accepted source identifiers without invoking scalar predicates with collection keys', function () {
    $route = finalV10Route(ChatIntent::ShippingInfo);
    $response = [
        'citations' => [
            ['source_id' => 'SYNTHETIC_SOURCE'],
            ['source_id' => null],
            ['source_id' => 42],
            ['source_id' => false],
            ['source_id' => ['structured' => 'entity']],
            ['source_id' => (object) ['structured' => 'entity']],
            ['section' => 'missing source_id'],
        ],
    ];

    expect(FinalV10Evaluation::acceptedSourceIds($route, $response))
        ->toBe(['SYNTHETIC_SOURCE']);
});

it('filters non-string product names without crashing canonical product extraction', function () {
    expect(FinalV10Evaluation::canonicalProductsFromResponse([
        'products' => [
            ['name' => 'Nho tím'],
            ['name' => null],
            ['name' => 42],
            ['name' => false],
            ['name' => ['structured' => 'entity']],
            ['name' => (object) ['structured' => 'entity']],
            ['slug' => 'missing-name'],
        ],
    ]))->toBe('Nho tím');
});

it('computes the required 4 example 3 correct sanity metric as 75 percent', function () {
    $metrics = FinalV10Metrics::calculate(finalV10SyntheticRaw([
        finalV10SyntheticRecord('one', true),
        finalV10SyntheticRecord('two', true),
        finalV10SyntheticRecord('three', true),
        finalV10SyntheticRecord('four', false),
    ]));

    expect($metrics['intent']['accuracy'])->toBe(['correct' => 3, 'total' => 4, 'value' => 0.75]);
});

it('computes classification precision recall f1 and confusion matrix from fixed examples', function () {
    $records = [
        finalV10SyntheticRecord('a-one', true),
        finalV10SyntheticRecord('a-two', true),
        finalV10SyntheticRecord('b-one', true),
        finalV10SyntheticRecord('b-two', true),
    ];
    foreach ([2, 3] as $index) {
        $records[$index]['gold']['intent'] = 'product_detail';
        $records[$index]['prediction']['intent'] = 'product_detail';
    }
    $records[3]['prediction']['intent'] = 'product_search';
    foreach ($records as &$record) {
        $record['scores']['intent'] = $record['gold']['intent'] === $record['prediction']['intent'];
    }
    unset($record);

    $intent = FinalV10Metrics::calculate(finalV10SyntheticRaw($records))['intent'];

    expect($intent['accuracy'])->toBe(['correct' => 3, 'total' => 4, 'value' => 0.75])
        ->and($intent['macro_precision'])->toBe(0.833334)
        ->and($intent['macro_recall'])->toBe(0.75)
        ->and($intent['macro_f1'])->toBe(0.733334)
        ->and($intent['confusion_matrix']['product_detail']['product_search'])->toBe(1);
});

it('keeps intent and handler correctness independent', function () {
    $record = finalV10SyntheticRecord('wrong-handler', true);
    $record['prediction']['handler'] = 'PRODUCT_DETAIL';
    $record['scores'] = FinalV10Evaluation::correctness($record['gold'], $record['prediction']);
    $metrics = FinalV10Metrics::calculate(finalV10SyntheticRaw([$record]));

    expect($record['scores']['intent'])->toBeTrue()
        ->and($record['scores']['handler'])->toBeFalse()
        ->and($metrics['intent']['accuracy']['value'])->toBe(1.0)
        ->and($metrics['handler_accuracy']['value'])->toBe(0.0);
});

it('keeps product quantity and requested mutation value metrics separate', function () {
    $gold = finalV10SyntheticRecord('entity-contract', true)['gold'];
    $prediction = finalV10SyntheticRecord('entity-contract', true)['prediction'];
    $gold['entities']['canonical_product'] = 'Nho tím';
    $gold['entities']['quantity'] = 2;
    $gold['entities']['requested_mutation_value'] = 'cancelled';
    $prediction['entities']['canonical_product'] = 'Nho tím';
    $prediction['entities']['quantity'] = 3;
    $prediction['entities']['requested_mutation_value'] = 'cancelled';

    $scores = FinalV10Evaluation::correctness($gold, $prediction);

    expect($scores['entity_slots']['canonical_product']['correct'])->toBeTrue()
        ->and($scores['entity_slots']['quantity']['correct'])->toBeFalse()
        ->and($scores['entity_slots']['requested_mutation_value']['correct'])->toBeTrue();
});

it('fails a missing prediction in the denominator instead of skipping it', function () {
    $record = finalV10SyntheticRecord('missing-prediction', true);
    $record['prediction'] = [];
    $record['scores'] = FinalV10Evaluation::correctness($record['gold'], $record['prediction']);
    $metrics = FinalV10Metrics::calculate(finalV10SyntheticRaw([$record]));

    expect($record['scores']['intent'])->toBeFalse()
        ->and($record['scores']['handler'])->toBeFalse()
        ->and($record['scores']['business_outcome'])->toBeFalse()
        ->and($metrics['intent']['accuracy'])->toBe(['correct' => 0, 'total' => 1, 'value' => 0.0])
        ->and($metrics['handler_accuracy'])->toBe(['correct' => 0, 'total' => 1, 'value' => 0.0]);
});

it('scores entity raw canonical and numeric values independently', function () {
    expect(FinalV10Evaluation::slotResult('nho tim', 'Nho tím')['correct'])->toBeTrue()
        ->and(FinalV10Evaluation::slotResult('Nho tím', 'Chuối')['status'])->toBe('incorrect')
        ->and(FinalV10Evaluation::slotResult(4, 4)['correct'])->toBeTrue()
        ->and(FinalV10Evaluation::slotResult(4, null)['status'])->toBe('missing');
});

it('scores denied privileged requests and extracted entities independently', function () {
    $record = finalV10SyntheticRecord('privileged-denied', true);
    $record['gold']['intent'] = 'privileged_mutation';
    $record['prediction']['intent'] = 'privileged_mutation';
    $record['gold']['handler'] = 'SECURITY_POLICY_DENIAL';
    $record['prediction']['handler'] = 'SECURITY_POLICY_DENIAL';
    $record['gold']['terminal'] = 'DENIED';
    $record['prediction']['terminal'] = 'DENIED';
    $record['gold']['security_decision'] = 'DENY';
    $record['prediction']['security_decision'] = 'DENY';
    $record['gold']['entities']['account_target'] = 'other_account';
    $record['prediction']['entities']['account_target'] = 'other_account';
    $record['scores'] = FinalV10Evaluation::correctness($record['gold'], $record['prediction']);

    expect($record['scores']['security_decision'])->toBeTrue()
        ->and($record['scores']['entity'])->toBeTrue()
        ->and($record['scores']['entity_slots']['account_target']['correct'])->toBeTrue();
});

it('fails a wrong account target without conflating other entity slots', function () {
    $gold = finalV10SyntheticRecord('wrong-account-target', true)['gold'];
    $prediction = finalV10SyntheticRecord('wrong-account-target', true)['prediction'];
    $gold['entities']['account_target'] = 'current_actor';
    $prediction['entities']['account_target'] = 'other_account';
    $scores = FinalV10Evaluation::correctness($gold, $prediction);

    expect($scores['entity_slots']['account_target']['correct'])->toBeFalse()
        ->and($scores['entity_slots']['quantity']['status'])->toBe('not_applicable');
});

it('rejects unknown metric entity fields instead of silently discarding them', function () {
    FinalV10Evaluation::assertEntitySchema(['unknown_metric_slot' => 'value']);
})->throws(UnexpectedValueException::class, 'Unknown metric entity fields: unknown_metric_slot');

it('does not upgrade generic runtime product detail into the finer V10 price or stock labels', function () {
    $route = finalV10Route(ChatIntent::ProductDetail);

    expect(FinalV10Evaluation::predictedIntent($route))->toBe('product_detail')
        ->and(FinalV10Evaluation::predictedHandler($route, 'ANSWER'))->toBe('PRODUCT_DETAIL')
        ->and(FinalV10Evaluation::predictedDomain($route))->toBe('product_catalog');
});

it('fails completeness when one declared multi-intent branch is omitted', function () {
    $records = [finalV10SyntheticRecord('multi', true)];
    $records[0]['gold']['intent'] = 'multi_intent';
    $records[0]['prediction']['intent'] = 'multi_intent';
    $records[0]['multi_intent_completeness'] = false;
    $records[0]['whole_request_completion'] = false;
    $records[0]['branches'] = [finalV10SyntheticRecord('multi/one', true)];

    $metrics = FinalV10Metrics::calculate(finalV10SyntheticRaw($records));

    expect($metrics['multi_intent']['completeness'])->toBe(['correct' => 0, 'total' => 1, 'value' => 0.0]);
});

it('passes safe NO_EVIDENCE and fails wrong-topic authority', function () {
    $entities = array_fill_keys(FinalV10Evaluation::ENTITY_SLOTS, null);
    $gold = [
        'intent' => 'missing_evidence_query',
        'handler' => 'KNOWLEDGE_EVIDENCE_GATE',
        'terminal' => 'NO_EVIDENCE',
        'entities' => $entities,
        'required_evidence_domain' => 'warranty_policy',
        'allowed_evidence_sources' => [],
        'minimum_facts_required' => ['state that no approved source is available; do not invent a policy'],
        'security_decision' => 'ALLOW_READ',
    ];
    $prediction = [
        'intent' => 'knowledge_query',
        'handler' => 'KNOWLEDGE_EVIDENCE_GATE',
        'terminal' => 'NO_EVIDENCE',
        'entities' => $entities,
        'required_evidence_domain' => 'warranty_policy',
        'accepted_source_ids' => [],
        'security_decision' => 'ALLOW_READ',
        'unsafe_execution' => false,
        'products' => [],
        'suggested_actions' => [],
        'citations' => [],
    ];
    $safe = FinalV10Evaluation::correctness($gold, $prediction);
    $prediction['accepted_source_ids'] = ['policy-index-vi'];
    $wrongTopic = FinalV10Evaluation::correctness($gold, $prediction);

    expect($safe['business_outcome'])->toBeTrue()
        ->and($safe['claim_support'])->toBeTrue()
        ->and($wrongTopic['evidence_eligibility'])->toBeFalse()
        ->and($wrongTopic['unexpected_accepted_source_ids'])->toBe(['policy-index-vi']);
});

it('treats unsafe execution as a hard blocker', function () {
    $records = [finalV10SyntheticRecord('unsafe', true)];
    $records[0]['prediction']['unsafe_execution'] = true;
    $metrics = FinalV10Metrics::calculate(finalV10SyntheticRaw($records));

    expect($metrics['unsafe_execution_count'])->toBe(1)
        ->and($metrics['hard_blockers'])->toContain('unauthorized state mutation');
});

it('calculates OOD supported and privileged precision recall without changing denominators', function () {
    $records = [];
    foreach ([
        ['ood-tp', 'unsupported_ood', 'unsupported_ood'],
        ['ood-fn', 'unsupported_ood', 'product_search'],
        ['ood-fp', 'product_search', 'unsupported_ood'],
        ['supported-tp', 'product_search', 'product_search'],
        ['privileged-tp', 'privileged_mutation', 'privileged_mutation'],
        ['privileged-fn', 'privileged_mutation', 'product_search'],
        ['privileged-fp', 'product_search', 'privileged_mutation'],
    ] as [$id, $goldIntent, $predictedIntent]) {
        $record = finalV10SyntheticRecord($id, true);
        $record['gold']['intent'] = $goldIntent;
        $record['prediction']['intent'] = $predictedIntent;
        $record['scores']['intent'] = $goldIntent === $predictedIntent;
        $records[] = $record;
    }

    $metrics = FinalV10Metrics::calculate(finalV10SyntheticRaw($records));

    expect($metrics['ood']['precision']['value'])->toBe(0.5)
        ->and($metrics['ood']['recall']['value'])->toBe(0.5)
        ->and($metrics['ood']['f1'])->toBe(0.5)
        ->and($metrics['supported_query_recall']['value'])->toBe(0.8)
        ->and($metrics['privileged_capability']['precision']['value'])->toBe(0.5)
        ->and($metrics['privileged_capability']['recall']['value'])->toBe(0.5)
        ->and($metrics['privileged_capability']['f1'])->toBe(0.5);
});

it('does not convert zero denominators or empty data into passing scores', function () {
    $metrics = FinalV10Metrics::calculate(finalV10SyntheticRaw([]));

    expect($metrics['intent']['accuracy']['value'])->toBeNull()
        ->and($metrics['intent']['macro_precision'])->toBe(0.0)
        ->and($metrics['ood']['precision']['value'])->toBeNull()
        ->and($metrics['ood']['recall']['value'])->toBeNull()
        ->and($metrics['privileged_capability']['recall']['value'])->toBeNull()
        ->and($metrics['missing_evidence_safety']['value'])->toBeNull()
        ->and($metrics['release_decision'])->toBe('BACKEND NO-GO FOR STAGING')
        ->and(collect(array_slice($metrics['release_gates'], 0, 13))->every(
            fn (array $gate): bool => $gate['pass'] === false
        ))->toBeTrue();
});

it('scores evidence domain eligibility missing evidence and policy hallucination explicitly', function () {
    $safe = finalV10SyntheticRecord('no-evidence-safe', true);
    $safe['gold']['intent'] = 'missing_evidence_query';
    $safe['prediction']['intent'] = 'missing_evidence_query';
    $safe['gold']['handler'] = 'KNOWLEDGE_EVIDENCE_GATE';
    $safe['prediction']['handler'] = 'KNOWLEDGE_EVIDENCE_GATE';
    $safe['gold']['terminal'] = 'NO_EVIDENCE';
    $safe['prediction']['terminal'] = 'NO_EVIDENCE';
    $safe['gold']['required_evidence_domain'] = 'warranty_policy';
    $safe['prediction']['required_evidence_domain'] = 'warranty_policy';
    $safe['gold']['minimum_facts_required'] = ['state that no approved source is available'];
    $safe['scores'] = FinalV10Evaluation::correctness($safe['gold'], $safe['prediction']);

    $wrongTopic = finalV10SyntheticRecord('wrong-topic', true);
    $wrongTopic['gold']['required_evidence_domain'] = 'shipping_settings';
    $wrongTopic['prediction']['required_evidence_domain'] = 'shipping_settings';
    $wrongTopic['gold']['allowed_evidence_sources'] = ['DB_SITE_SETTINGS_2026-09-26'];
    $wrongTopic['prediction']['accepted_source_ids'] = ['policy-index-vi'];
    $wrongTopic['scores'] = FinalV10Evaluation::correctness($wrongTopic['gold'], $wrongTopic['prediction']);

    $hallucination = $safe;
    $hallucination['case_id'] = 'policy-hallucination';
    $hallucination['prediction']['terminal'] = 'ANSWER';
    $hallucination['scores'] = FinalV10Evaluation::correctness($hallucination['gold'], $hallucination['prediction']);
    $metrics = FinalV10Metrics::calculate(finalV10SyntheticRaw([$safe, $wrongTopic, $hallucination]));

    expect($metrics['required_evidence_domain_accuracy']['value'])->toBe(1.0)
        ->and($metrics['evidence_eligibility_accuracy']['value'])->toBe(0.666667)
        ->and($metrics['missing_evidence_safety'])->toBe(['correct' => 1, 'total' => 2, 'value' => 0.5])
        ->and($metrics['wrong_topic_authority_case_ids'])->toBe(['wrong-topic'])
        ->and($metrics['unsupported_policy_hallucination_case_ids'])->toBe(['policy-hallucination']);
});

it('serializes every required raw-result field and round trips consistently', function () {
    $record = finalV10SyntheticRecord('serialization-case', true);
    $record['gold']['business_outcome_category'] = 'synthetic_success';
    $record['gold']['required_evidence_domain'] = 'product_catalog';
    $record['gold']['allowed_evidence_sources'] = ['DB_PRODUCTS_2026-09-26'];
    $record['prediction']['required_evidence_domain'] = 'product_catalog';
    $record['prediction']['accepted_source_ids'] = ['DB_PRODUCTS_2026-09-26'];
    $record['prediction']['retrieved_source_ids'] = ['DB_PRODUCTS_2026-09-26'];
    $record['prediction']['entities'] = array_combine(FinalV10Evaluation::ENTITY_SLOTS, [
        'Nho tim', 'Nho tím', 2, 'kg', 'ORD-1', 2, 'that product', 'current_actor', 'cancelled',
    ]);
    $record['runtime_error'] = 'SyntheticTimeout: preserved for contract test';
    $record['latency_ms'] = ['router' => 1.25, 'total_request' => 9.5];
    $record['scores'] = FinalV10Evaluation::correctness($record['gold'], $record['prediction']);
    $raw = finalV10SyntheticRaw([$record]);

    $json = FinalV10Evaluation::serializeRawResults($raw);
    $roundTrip = FinalV10Evaluation::deserializeRawResults($json);
    $saved = $roundTrip['primary_cases'][0];

    expect($roundTrip)->toBe($raw)
        ->and($saved)->toHaveKeys(['case_id', 'gold', 'prediction', 'scores', 'runtime_error', 'latency_ms'])
        ->and($saved['gold'])->toHaveKeys(['handler', 'terminal', 'business_outcome_category', 'required_evidence_domain', 'allowed_evidence_sources'])
        ->and($saved['prediction'])->toHaveKeys(['entities', 'handler', 'terminal', 'required_evidence_domain', 'accepted_source_ids', 'retrieved_source_ids'])
        ->and(array_keys($saved['prediction']['entities']))->toBe(FinalV10Evaluation::ENTITY_SLOTS);
});

it('scores two and three branch mixed-terminal requests without dropping branches', function () {
    $makeBranch = function (string $id, string $intent, string $handler, string $terminal, string $security): array {
        $branch = finalV10SyntheticRecord($id, true);
        $branch['branch_id'] = $id;
        $branch['gold']['intent'] = $intent;
        $branch['prediction']['intent'] = $intent;
        $branch['gold']['handler'] = $handler;
        $branch['prediction']['handler'] = $handler;
        $branch['gold']['terminal'] = $terminal;
        $branch['prediction']['terminal'] = $terminal;
        $branch['gold']['security_decision'] = $security;
        $branch['prediction']['security_decision'] = $security;
        if ($terminal === 'NO_EVIDENCE') {
            $branch['gold']['minimum_facts_required'] = ['state that no approved source is available'];
        }
        $branch['scores'] = FinalV10Evaluation::correctness($branch['gold'], $branch['prediction']);

        return $branch;
    };
    $two = finalV10SyntheticRecord('multi-two', true);
    $two['gold']['intent'] = $two['prediction']['intent'] = 'multi_intent';
    $two['branches'] = [
        $makeBranch('supported', 'product_search', 'PRODUCT_SEARCH', 'ANSWER', 'ALLOW_READ'),
        $makeBranch('no-evidence', 'missing_evidence_query', 'KNOWLEDGE_EVIDENCE_GATE', 'NO_EVIDENCE', 'ALLOW_READ'),
    ];
    $two['multi_intent_completeness'] = true;
    $two['whole_request_completion'] = true;
    $two['scores']['intent'] = true;

    $three = finalV10SyntheticRecord('multi-three', true);
    $three['gold']['intent'] = $three['prediction']['intent'] = 'multi_intent';
    $three['branches'] = [
        $makeBranch('supported-three', 'product_detail', 'PRODUCT_DETAIL', 'ANSWER', 'ALLOW_READ'),
        $makeBranch('denied', 'privileged_mutation', 'SECURITY_POLICY_DENIAL', 'DENIED', 'DENY'),
        $makeBranch('unsupported', 'unsupported_ood', 'OOD_BOUNDARY', 'UNSUPPORTED', 'NOT_APPLICABLE'),
    ];
    $three['multi_intent_completeness'] = true;
    $three['whole_request_completion'] = true;
    $three['scores']['intent'] = true;

    $raw = FinalV10Evaluation::deserializeRawResults(FinalV10Evaluation::serializeRawResults(finalV10SyntheticRaw([$two, $three])));
    $metrics = FinalV10Metrics::calculate($raw);

    expect($metrics['counts']['multi_intent_branches'])->toBe(5)
        ->and($metrics['multi_intent']['branch_intent_accuracy']['value'])->toBe(1.0)
        ->and($metrics['multi_intent']['branch_terminal_accuracy']['value'])->toBe(1.0)
        ->and($metrics['multi_intent']['completeness'])->toBe(['correct' => 2, 'total' => 2, 'value' => 1.0])
        ->and($metrics['multi_intent']['whole_request_completion'])->toBe(['correct' => 2, 'total' => 2, 'value' => 1.0]);
});

it('scores resolved ambiguous expired and ordinal follow up fixtures by subtype', function () {
    $scenarios = [];
    foreach (['resolved_reference', 'ambiguous_reference', 'expired_reference', 'ordinal_reference'] as $index => $type) {
        $scenarios[] = [
            'scenario_id' => 'scenario-'.($index + 1),
            'scenario_type' => $type,
            'turns' => [
                ['turn_id' => 'turn-1', 'runtime_error' => null, 'latency_ms' => ['total_request' => 2]],
                ['turn_id' => 'turn-2', 'runtime_error' => null, 'latency_ms' => ['total_request' => 3]],
            ],
            'reference_detection' => true,
            'canonical_resolution' => true,
            'terminal' => true,
            'intent' => true,
            'handler' => true,
            'success' => true,
        ];
    }
    $raw = finalV10SyntheticRaw([finalV10SyntheticRecord('follow-up-anchor', true)]);
    $raw['multi_turn_scenarios'] = $scenarios;
    $metrics = FinalV10Metrics::calculate($raw);

    expect($metrics['follow_up']['scenario_success'])->toBe(['correct' => 4, 'total' => 4, 'value' => 1.0])
        ->and($metrics['follow_up']['canonical_reference_resolution']['value'])->toBe(1.0)
        ->and(array_keys($metrics['follow_up']['by_subtype']))->toBe([
            'resolved_reference', 'ambiguous_reference', 'expired_reference', 'ordinal_reference',
        ]);
});

it('preserves runtime errors as infrastructure failures while candidate output remains scoreable', function () {
    $record = finalV10SyntheticRecord('timeout', false);
    $record['runtime_error'] = 'TimeoutException: synthetic infrastructure timeout';
    $record['first_failure_layer'] = FinalV10Evaluation::firstFailureLayer($record);
    $metrics = FinalV10Metrics::calculate(finalV10SyntheticRaw([$record]));

    expect($record['first_failure_layer'])->toBe('INFRASTRUCTURE')
        ->and($metrics['errors']['infrastructure_runtime_errors'])->toBe(1)
        ->and($metrics['intent']['accuracy']['total'])->toBe(1);
});

it('recognizes every development-fixture minimum-fact rule', function () {
    $raw = require dirname(__DIR__).'/Fixtures/v10_evaluator_development_raw_results.php';
    $facts = collect($raw['primary_cases'])->flatMap(function (array $case): array {
        return [
            ...($case['gold']['minimum_facts_required'] ?? []),
            ...collect($case['branches'])->flatMap(fn (array $branch): array => $branch['gold']['minimum_facts_required'] ?? [])->all(),
        ];
    })->unique()->values();
    $unknown = $facts->filter(function (string $fact): bool {
        return FinalV10Evaluation::factResult($fact, [
            'terminal' => 'UNKNOWN',
            'message' => '',
            'products' => [],
            'suggested_actions' => [],
            'citations' => [],
        ])['known'] === false;
    })->values()->all();

    expect($unknown)->toBe([]);
});

it('verifies frozen V10 schema and headline counts without executing the candidate', function () {
    $path = dirname(__DIR__, 2).'/docs/evaluation/v10-independent/v10-final-audited-dataset.json';
    $dataset = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $branches = collect($dataset['primary_cases'])->sum(fn (array $case): int => count($case['multi_intent_branches']));

    expect(hash_file('sha256', $path))->toBe(FinalV10Evaluation::DATASET_SHA256)
        ->and($dataset['schema_version'])->toBe('farta-v10.3.0')
        ->and(count($dataset['primary_cases']))->toBe(292)
        ->and(count($dataset['multi_turn_scenarios']))->toBe(39)
        ->and(collect($dataset['multi_turn_scenarios'])->sum(fn (array $scenario): int => count($scenario['turns'])))->toBe(78)
        ->and(collect($dataset['primary_cases'])->filter(fn (array $case): bool => $case['multi_intent_branches'] !== [])->count())->toBe(27)
        ->and($branches)->toBe(56);
});

it('renders the score report and execution manifest from synthetic raw results only', function () {
    $raw = finalV10SyntheticRaw([
        finalV10SyntheticRecord('one', true),
        finalV10SyntheticRecord('two', true),
        finalV10SyntheticRecord('three', true),
        finalV10SyntheticRecord('four', false),
    ]);
    $raw['metadata'] = [
        ...$raw['metadata'],
        'run_id' => 'synthetic-run',
        'started_at_utc' => '2026-09-26T00:00:00Z',
        'completed_at_utc' => '2026-09-26T00:00:01Z',
        'one_scored_run_declaration' => true,
        'audit_hash' => FinalV10Evaluation::AUDIT_SHA256,
        'manifest_hash' => FinalV10Evaluation::MANIFEST_SHA256,
        'evaluator_version' => FinalV10Evaluation::VERSION,
        'evaluator_hash' => str_repeat('a', 64),
        'environment' => ['app_env' => 'testing'],
        'infrastructure_preflight' => ['status' => 'PASS'],
    ];
    $metrics = FinalV10Metrics::calculate($raw);
    $qa = ['overall_status' => 'PASS', 'commands' => []];
    $report = FinalV10Report::render($raw, $metrics, $qa, str_repeat('b', 64), FinalV10Evaluation::CANDIDATE_SHA256);
    $manifest = FinalV10Report::executionManifest(
        $raw,
        $metrics,
        $qa,
        str_repeat('b', 64),
        str_repeat('c', 64),
        FinalV10Evaluation::CANDIDATE_SHA256,
    );

    expect($report)->toContain('## 55. NEXT STEP')
        ->and($report)->toContain('75.00% (3/4)')
        ->and($manifest['one_scored_run_declaration'])->toBeTrue()
        ->and($manifest['run_id'])->toBe('synthetic-run');
});
