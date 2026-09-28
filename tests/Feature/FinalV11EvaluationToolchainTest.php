<?php

use Tests\Support\ChatFixtureAudit;
use Tests\Support\FinalV11ContractException;
use Tests\Support\FinalV11DatasetContract;
use Tests\Support\FinalV11Evaluation;
use Tests\Support\FinalV11FactVerifier;
use Tests\Support\FinalV11IdentityValidator;
use Tests\Support\FinalV11Metrics;
use Tests\Support\FinalV11OfflineScorer;
use Tests\Support\FinalV11PerfectMirror;
use Tests\Support\FinalV11RawCapture;

/** @return array<string, mixed> */
function v11ToolchainDataset(): array
{
    static $dataset;

    return $dataset ??= json_decode(
        (string) file_get_contents(base_path('docs/evaluation/v11-independent/v11-final-audited-dataset.json')),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
}

/** @return array<string, mixed> */
function v11ToolchainContract(): array
{
    static $contract;

    return $contract ??= FinalV11DatasetContract::parse(v11ToolchainDataset());
}

/** @return array<string, mixed> */
function v11FactContract(): array
{
    static $contract;

    return $contract ??= json_decode(
        (string) file_get_contents(base_path('docs/evaluation/v11-independent/v11-fact-verification-contract.json')),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
}

/** @return array<string, mixed> */
function v11MirrorCapture(): array
{
    return FinalV11PerfectMirror::capture(v11ToolchainContract(), v11FactContract());
}

/** @param array<string, mixed> $capture @return array<string, mixed> */
function v11MetricsFor(array $capture): array
{
    $serialized = FinalV11RawCapture::serialize($capture);
    $deserialized = FinalV11RawCapture::deserialize($serialized);

    return FinalV11Metrics::calculate(FinalV11OfflineScorer::score(
        v11ToolchainContract(),
        $deserialized,
        v11FactContract()
    ));
}

/** @param callable(array<string, mixed>): bool $predicate */
function v11PrimaryIndex(callable $predicate): int
{
    foreach (v11ToolchainContract()['primary_cases'] as $index => $record) {
        if ($predicate($record)) {
            return $index;
        }
    }

    throw new RuntimeException('No matching Final V11 primary record.');
}

it('verifies frozen inputs and parses every Final V11 structure without executing the candidate', function () {
    expect(hash_file('sha256', base_path('docs/evaluation/v11-independent/v11-final-audited-dataset.json')))
        ->toBe('94bc6d3930057f7584c80baac66df1f05f98133b94fb733229fe22b49768f3cb')
        ->and(hash_file('sha256', base_path('docs/evaluation/v11-independent/v11-r3-fresh-audit-report.md')))
        ->toBe('c97a9d4c1e6621cb9c18fc692887b9302de302cc73372f8469e6ca5b22bd70ea')
        ->and(hash_file('sha256', base_path('docs/evaluation/v11-independent/v11-final-manifest.json')))
        ->toBe('f2ccc7420918cd12957877e305e7a99eecf08b274cd0b3fa4ef63dbba9e35f4e')
        ->and(ChatFixtureAudit::runtimeHash())
        ->toBe('f04745463eb1958f0ce3c0a9c6719ad74c74355feb6f68e1adff077531a8dacf')
        ->and(v11ToolchainContract()['counts'])->toBe([
            'primary' => 160,
            'multi_turn_scenarios' => 20,
            'scenario_turns' => 40,
            'multi_intent_cases' => 20,
            'multi_intent_branches' => 40,
            'entity_objects' => 240,
        ]);
})->group('v11-toolchain');

it('keeps both machine-readable contracts synchronized with frozen gold', function () {
    $evaluation = json_decode(
        (string) file_get_contents(base_path('docs/evaluation/v11-independent/v11-evaluation-contract.json')),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    $requiredMetricFields = [
        'name', 'eligible_denominator', 'gold_fields', 'prediction_fields', 'comparison_rule',
        'null_semantics', 'multi_intent_semantics', 'scenario_semantics', 'failure_behavior',
    ];

    expect($evaluation['metrics'])->toHaveCount(26)
        ->and(collect($evaluation['metrics'])->every(
            fn (array $metric): bool => array_diff($requiredMetricFields, array_keys($metric)) === []
        ))->toBeTrue()
        ->and(v11FactContract())->toBe(FinalV11FactVerifier::buildContract(v11ToolchainDataset()))
        ->and(v11FactContract()['claim_population']['eligible'])->toBe(115)
        ->and(v11FactContract()['scenario_claim_population']['eligible'])->toBe(15);
})->group('v11-toolchain');

it('scores the complete serialized perfect gold mirror perfectly', function () {
    $metrics = v11MetricsFor(v11MirrorCapture());

    expect($metrics['counts'])->toBe([
        'primary' => 160,
        'multi_turn_scenarios' => 20,
        'scenario_turns' => 40,
        'multi_intent_cases' => 20,
        'multi_intent_branches' => 40,
        'entity_objects' => 240,
    ])->and($metrics['intent']['accuracy'])->toBe(['correct' => 160, 'total' => 160, 'value' => 1.0])
        ->and($metrics['intent']['macro_precision'])->toBe(1.0)
        ->and($metrics['intent']['macro_recall'])->toBe(1.0)
        ->and($metrics['intent']['macro_f1'])->toBe(1.0)
        ->and($metrics['handler_accuracy']['value'])->toBe(1.0)
        ->and($metrics['terminal_accuracy']['value'])->toBe(1.0)
        ->and($metrics['business_outcome_accuracy'])->toBe(['correct' => 160, 'total' => 160, 'value' => 1.0])
        ->and($metrics['joint_entity_frame_accuracy'])->toBe(['correct' => 240, 'total' => 240, 'value' => 1.0])
        ->and($metrics['required_evidence_domain_accuracy'])->toBe(['correct' => 145, 'total' => 145, 'value' => 1.0])
        ->and($metrics['evidence_eligibility'])->toBe(['correct' => 200, 'total' => 200, 'value' => 1.0])
        ->and($metrics['claim_support_accuracy'])->toBe(['correct' => 115, 'total' => 115, 'value' => 1.0])
        ->and($metrics['missing_evidence_safety'])->toBe(['correct' => 17, 'total' => 17, 'value' => 1.0])
        ->and($metrics['follow_up']['scenario_success'])->toBe(['correct' => 20, 'total' => 20, 'value' => 1.0])
        ->and($metrics['follow_up']['turn_entity_accuracy'])->toBe(['correct' => 40, 'total' => 40, 'value' => 1.0])
        ->and($metrics['follow_up']['scenario_claim_support'])->toBe(['correct' => 15, 'total' => 15, 'value' => 1.0])
        ->and($metrics['multi_intent']['branch_business_outcome_accuracy'])->toBe(['correct' => 40, 'total' => 40, 'value' => 1.0])
        ->and($metrics['multi_intent']['completeness'])->toBe(['correct' => 20, 'total' => 20, 'value' => 1.0])
        ->and($metrics['multi_intent']['whole_request_completion'])->toBe(['correct' => 20, 'total' => 20, 'value' => 1.0])
        ->and($metrics['wrong_topic_authority_count'])->toBe(0)
        ->and($metrics['unsupported_policy_hallucination_count'])->toBe(0)
        ->and($metrics['unsafe_execution_count'])->toBe(0)
        ->and($metrics['wrong_entity_unsafe_action_count'])->toBe(0);

    foreach ($metrics['entity_slots'] as $slot) {
        expect($slot['f1'])->toBe(1.0);
    }
    foreach ($metrics['denominator_inventory'] as $entry) {
        expect($entry)->toHaveKeys(['eligible', 'excluded', 'exclusion_reason', 'zero_denominator']);
    }
})->group('v11-toolchain');

it('detects every required controlled negative mutation', function () {
    $baseline = v11MetricsFor(v11MirrorCapture());

    $assertLower = function (Closure $mutate, string $path) use ($baseline): void {
        $capture = v11MirrorCapture();
        $mutate($capture);
        expect((float) data_get(v11MetricsFor($capture), $path))->toBeLessThan((float) data_get($baseline, $path));
    };

    $assertLower(fn (array &$c) => $c['primary_cases'][0]['prediction']['intent'] = 'unsupported_ood', 'intent.accuracy.value');
    $assertLower(fn (array &$c) => $c['primary_cases'][0]['prediction']['handler'] = 'OOD_BOUNDARY', 'handler_accuracy.value');
    $assertLower(fn (array &$c) => $c['primary_cases'][0]['prediction']['business_outcome_category'] = 'NO_MATCH', 'business_outcome_accuracy.value');
    $assertLower(fn (array &$c) => $c['primary_cases'][0]['prediction']['terminal'] = 'DENIED', 'terminal_accuracy.value');

    foreach (['canonical_product', 'quantity', 'unit', 'account_target', 'requested_mutation_value', 'order_reference'] as $slot) {
        $index = v11PrimaryIndex(fn (array $record): bool => $record['gold']['entities'][$slot] !== null);
        $assertLower(function (array &$capture) use ($index, $slot): void {
            $capture['primary_cases'][$index]['prediction']['entities'][$slot] = $slot === 'quantity' ? 999999 : 'WRONG_VALUE';
        }, 'entity_slots.'.$slot.'.accuracy.value');
    }

    $scenarioTurn = collect(v11ToolchainContract()['multi_turn_scenarios'])->flatMap(function (array $scenario): array {
        return array_map(fn (array $turn): array => ['scenario_id' => $scenario['scenario_id'], ...$turn], $scenario['turns']);
    })->first(fn (array $turn): bool => $turn['gold']['entities']['context_reference'] !== null);
    $scenarioCaptureIndex = collect(v11MirrorCapture()['scenario_turns'])->search(fn (array $turn): bool => $turn['scenario_id'] === $scenarioTurn['scenario_id'] && $turn['turn'] === $scenarioTurn['turn']
    );
    $assertLower(function (array &$capture) use ($scenarioCaptureIndex): void {
        $capture['scenario_turns'][$scenarioCaptureIndex]['prediction']['entities']['context_reference'] = 'WRONG_CONTEXT';
    }, 'follow_up.turn_entity_accuracy.value');

    $multiIndex = v11PrimaryIndex(fn (array $record): bool => $record['branches'] !== []);
    $assertLower(fn (array &$c) => array_pop($c['primary_cases'][$multiIndex]['branches']), 'multi_intent.completeness.value');
    $assertLower(fn (array &$c) => $c['primary_cases'][$multiIndex]['branches'][0]['prediction']['intent'] = 'unsupported_ood', 'multi_intent.branch_intent_accuracy.value');
    $assertLower(fn (array &$c) => $c['primary_cases'][$multiIndex]['branches'][0]['prediction']['terminal'] = 'DENIED', 'multi_intent.branch_terminal_accuracy.value');

    $domainIndex = v11PrimaryIndex(fn (array $record): bool => $record['gold']['required_evidence_domain'] !== null);
    $assertLower(fn (array &$c) => $c['primary_cases'][$domainIndex]['prediction']['required_evidence_domain'] = 'WRONG_DOMAIN', 'required_evidence_domain_accuracy.value');

    $claimIndex = v11PrimaryIndex(fn (array $record): bool => $record['gold']['minimum_facts_required'] !== []);
    $assertLower(function (array &$capture) use ($claimIndex): void {
        $capture['primary_cases'][$claimIndex]['prediction']['accepted_source_ids'] = ['policy-index-vi'];
        $capture['primary_cases'][$claimIndex]['prediction']['accepted_source_versions'] = ['policy-index-vi' => 1];
    }, 'evidence_eligibility.value');
    $assertLower(fn (array &$c) => $c['primary_cases'][$claimIndex]['prediction']['claim_evidence'] = [], 'claim_support_accuracy.value');
    $assertLower(function (array &$capture) use ($claimIndex): void {
        $capture['primary_cases'][$claimIndex]['prediction']['claim_evidence'][] = [
            'claim_id' => 'sha256:'.str_repeat('f', 64),
            'source_ids' => $capture['primary_cases'][$claimIndex]['prediction']['accepted_source_ids'],
        ];
    }, 'claim_support_accuracy.value');

    $securityIndex = v11PrimaryIndex(fn (array $record): bool => $record['gold']['security_decision'] !== 'NOT_APPLICABLE');
    $assertLower(fn (array &$c) => $c['primary_cases'][$securityIndex]['prediction']['security_decision'] = 'WRONG_SECURITY', 'security_decision_accuracy.value');

    $capture = v11MirrorCapture();
    $capture['primary_cases'][$securityIndex]['prediction']['unsafe_execution'] = true;
    $unsafe = v11MetricsFor($capture);
    expect($unsafe['unsafe_execution_count'])->toBe(1)
        ->and($unsafe['business_outcome_accuracy']['value'])->toBeLessThan(1.0);

    $capture = v11MirrorCapture();
    $capture['primary_cases'][$securityIndex]['prediction']['wrong_entity_unsafe_action'] = true;
    expect(v11MetricsFor($capture)['wrong_entity_unsafe_action_count'])->toBe(1);
})->group('v11-toolchain');

it('rejects claim-support negative classes A through E and accepts F', function () {
    $contract = v11FactContract();
    $datasetContract = v11ToolchainContract();
    $index = v11PrimaryIndex(fn (array $record): bool => count($record['gold']['allowed_evidence_sources']) === 2);
    $record = $datasetContract['primary_cases'][$index];
    $gold = $record['gold'];
    $good = FinalV11PerfectMirror::prediction($record['case_id'], $gold, $contract);

    $a = $good;
    $a['claim_evidence'][array_key_last($a['claim_evidence'])]['source_ids'] = ['product-category-authority-v11'];
    expect(FinalV11FactVerifier::verify($record['case_id'], $gold, $a, $contract)['claim_support'])->toBeFalse();

    $b = $good;
    $b['accepted_source_ids'] = ['policy-index-vi'];
    $b['accepted_source_versions'] = ['policy-index-vi' => 1];
    expect(FinalV11FactVerifier::verify($record['case_id'], $gold, $b, $contract))
        ->claim_support->toBeFalse()
        ->wrong_topic_authority->toBeTrue();

    $policyIndex = v11PrimaryIndex(fn (array $item): bool => $item['gold']['required_evidence_domain'] === 'account');
    $policyRecord = $datasetContract['primary_cases'][$policyIndex];
    $c = FinalV11PerfectMirror::prediction($policyRecord['case_id'], $policyRecord['gold'], $contract);
    $c['accepted_source_ids'] = ['policy-index-vi'];
    $c['accepted_source_versions'] = ['policy-index-vi' => 1];
    expect(FinalV11FactVerifier::verify($policyRecord['case_id'], $policyRecord['gold'], $c, $contract)['claim_support'])->toBeFalse();

    $d = $good;
    $d['accepted_source_ids'] = [];
    $d['accepted_source_versions'] = [];
    expect(FinalV11FactVerifier::verify($record['case_id'], $gold, $d, $contract)['claim_support'])->toBeFalse();

    $e = $good;
    $e['claim_evidence'] = [];
    expect(FinalV11FactVerifier::verify($record['case_id'], $gold, $e, $contract)['claim_support'])->toBeFalse();

    expect(FinalV11FactVerifier::verify($record['case_id'], $gold, $good, $contract)['claim_support'])->toBeTrue();
})->group('v11-toolchain');

it('preserves strict JSONL and UTF-8 behavior for all regression classes', function () {
    $capture = v11MirrorCapture();
    $capture['metadata']['utf8_probe'] = "Tiếng Việt | English | mixed 🙂 | byte-85 \u{0085} | escaped\nnewline";
    $capture['metadata']['zero'] = 0;
    $capture['metadata']['false'] = false;
    $capture['metadata']['empty_string'] = '';
    $capture['metadata']['empty_array'] = [];
    $lf = FinalV11RawCapture::serialize($capture);
    $crlf = str_replace("\n", "\r\n", $lf);

    foreach ([$lf, rtrim($lf, "\n"), $crlf, rtrim($crlf, "\r\n")] as $serialized) {
        $roundTrip = FinalV11RawCapture::deserialize($serialized);
        expect($roundTrip['metadata']['utf8_probe'])->toBe($capture['metadata']['utf8_probe'])
            ->and($roundTrip['metadata']['zero'])->toBe(0)
            ->and($roundTrip['metadata']['false'])->toBeFalse()
            ->and($roundTrip['metadata']['empty_string'])->toBe('')
            ->and($roundTrip['metadata']['empty_array'])->toBe([])
            ->and($roundTrip['primary_cases'])->toHaveCount(160)
            ->and($roundTrip['scenario_turns'])->toHaveCount(40);
    }

    $invalid = substr($lf, 0, 10)."\xC3\x28".substr($lf, 10);
    expect(fn () => FinalV11RawCapture::deserialize($invalid))
        ->toThrow(FinalV11ContractException::class, 'Invalid UTF-8');
})->group('v11-toolchain');

it('fails closed on null empty scalar object and missing structural types', function () {
    expect(FinalV11Evaluation::entityEquals(0, false))->toBeFalse()
        ->and(FinalV11Evaluation::entityEquals('', null))->toBeFalse()
        ->and(FinalV11Evaluation::slotResult(null, null)['status'])->toBe('not_applicable')
        ->and(FinalV11Evaluation::slotResult(null, '')['status'])->toBe('spurious');

    $mutations = [
        fn (array &$capture) => $capture['primary_cases'][0]['prediction']['entities'] = 'scalar',
        fn (array &$capture) => $capture['primary_cases'][0]['prediction']['accepted_source_ids'] = 'source',
        fn (array &$capture) => $capture['primary_cases'][0]['prediction']['claim_evidence'] = ['claim_id' => 'not-a-list'],
        fn (array &$capture) => $capture['primary_cases'][0]['prediction']['unsafe_execution'] = 0,
        function (array &$capture): void {
            unset($capture['primary_cases'][0]['prediction']['entities']['unit']);
        },
    ];
    foreach ($mutations as $mutate) {
        $capture = v11MirrorCapture();
        $mutate($capture);
        expect(fn () => v11MetricsFor($capture))->toThrow(FinalV11ContractException::class);
    }
})->group('v11-toolchain');

it('scores one serialized artifact byte-deterministically with explicit zero-denominator nulls', function () {
    $capture = FinalV11RawCapture::deserialize(FinalV11RawCapture::serialize(v11MirrorCapture()));
    $first = FinalV11OfflineScorer::score(v11ToolchainContract(), $capture, v11FactContract());
    $second = FinalV11OfflineScorer::score(v11ToolchainContract(), $capture, v11FactContract());
    $firstMetrics = FinalV11Metrics::calculate($first);
    $secondMetrics = FinalV11Metrics::calculate($second);

    expect(FinalV11Evaluation::stableJson($second))->toBe(FinalV11Evaluation::stableJson($first))
        ->and(FinalV11Evaluation::stableJson($secondMetrics))->toBe(FinalV11Evaluation::stableJson($firstMetrics));

    $emptyScored = ['primary_cases' => [], 'multi_turn_scenarios' => []];
    $empty = FinalV11Metrics::calculate($emptyScored);
    expect($empty['intent']['accuracy'])->toBe(['correct' => 0, 'total' => 0, 'value' => null])
        ->and($empty['handler_accuracy']['value'])->toBeNull();
})->group('v11-toolchain');

it('keeps scoring offline and validates identities only through a supplied manifest', function () {
    $supportFiles = [
        base_path('tests/Support/FinalV11Evaluation.php'),
        base_path('tests/Support/FinalV11FactVerifier.php'),
        base_path('tests/Support/FinalV11OfflineScorer.php'),
        base_path('tests/Support/FinalV11Metrics.php'),
        base_path('tests/Support/FinalV11RawCapture.php'),
    ];
    $source = implode("\n", array_map(fn (string $path): string => (string) file_get_contents($path), $supportFiles));
    expect($source)->not->toContain('Http::', 'postJson(', 'Qdrant', 'ChatIntentRouter', 'DB::', 'curl_', 'now(')
        ->and($source)->not->toContain('eb7ea21cad08cb80a4463f63735d514f40019b1bd804f15a3db13de9bc71eaa0')
        ->and($source)->not->toContain('d54a66b96a4ae27bd482afc156ad5e03955524ee04fb590ddd697b2d1c8106aa');

    $identity = [
        'candidate_hash' => str_repeat('1', 64),
        'dataset_hash' => str_repeat('2', 64),
        'evaluator_hash' => str_repeat('3', 64),
        'scorer_hash' => str_repeat('4', 64),
    ];
    expect(fn () => FinalV11IdentityValidator::validate($identity, ['execution_identity' => $identity]))
        ->not->toThrow(Throwable::class);
    $changed = $identity;
    $changed['dataset_hash'] = str_repeat('5', 64);
    expect(fn () => FinalV11IdentityValidator::validate($changed, ['execution_identity' => $identity]))
        ->toThrow(FinalV11ContractException::class, 'dataset_hash');
})->group('v11-toolchain');

it('recomputes the frozen evaluator scorer and toolchain manifest identities exactly', function () {
    $directory = base_path('docs/evaluation/v11-independent');
    $evaluator = json_decode((string) file_get_contents($directory.'/v11-evaluator-freeze-manifest.json'), true, 64, JSON_THROW_ON_ERROR);
    $scorer = json_decode((string) file_get_contents($directory.'/v11-offline-scorer-freeze-manifest.json'), true, 64, JSON_THROW_ON_ERROR);
    $toolchain = json_decode((string) file_get_contents($directory.'/v11-evaluation-toolchain-manifest.json'), true, 64, JSON_THROW_ON_ERROR);
    $paths = fn (array $files): array => array_map(fn (string $path): string => base_path($path), $files);

    expect(FinalV11Evaluation::semanticHash($paths($evaluator['ordered_semantic_files'])))
        ->toBe($evaluator['semantic_identity'])
        ->and(FinalV11Evaluation::semanticHash($paths($scorer['ordered_semantic_files'])))
        ->toBe($scorer['semantic_identity'])
        ->and($toolchain['execution_identity']['evaluator_hash'])->toBe($evaluator['semantic_identity'])
        ->and($toolchain['execution_identity']['scorer_hash'])->toBe($scorer['semantic_identity'])
        ->and(hash_file('sha256', $directory.'/v11-evaluator-freeze-manifest.json'))
        ->toBe($toolchain['freeze_manifests']['evaluator']['sha256'])
        ->and(hash_file('sha256', $directory.'/v11-offline-scorer-freeze-manifest.json'))
        ->toBe($toolchain['freeze_manifests']['offline_scorer']['sha256'])
        ->and(hash_file('sha256', $directory.'/v11-evaluation-contract.json'))
        ->toBe($toolchain['frozen_contracts']['evaluation_contract']['sha256'])
        ->and(hash_file('sha256', $directory.'/v11-fact-verification-contract.json'))
        ->toBe($toolchain['frozen_contracts']['fact_verification_contract']['sha256'])
        ->and($toolchain['execution_policy']['candidate_requests'])->toBe(0)
        ->and($toolchain['execution_policy']['candidate_executed'])->toBeFalse()
        ->and($toolchain['freeze_status'])->toBe('FROZEN');

    expect(fn () => FinalV11IdentityValidator::validate(
        $toolchain['execution_identity'],
        $toolchain
    ))->not->toThrow(Throwable::class);
})->group('v11-toolchain');
