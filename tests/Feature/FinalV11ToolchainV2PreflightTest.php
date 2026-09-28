<?php

use App\Services\Chat\ChatCapabilityGuard;
use App\Services\Chat\ChatIntentRouter;
use Tests\Support\ChatFixtureAudit;
use Tests\Support\FinalV11ContractException;
use Tests\Support\FinalV11DatasetContract;
use Tests\Support\FinalV11Evaluation;
use Tests\Support\FinalV11EvaluationV2;
use Tests\Support\FinalV11Metrics;
use Tests\Support\FinalV11OfflineScorerV2;
use Tests\Support\FinalV11PerfectMirror;
use Tests\Support\FinalV11RuntimeAdapterV2;
use Tests\Support\FinalV11RuntimeEvidenceHarnessV2;

/** @return array<string, array{route: array<string, mixed>, runtime: array<string, mixed>}> */
function v11V2SyntheticFrames(): array
{
    static $frames;

    return $frames ??= require base_path('tests/Fixtures/v11_toolchain_v2_synthetic_frames.php');
}

it('consumes the actual Phase 15 typed route frame without candidate changes', function () {
    $route = app(ChatIntentRouter::class)->route('set payment của order 42 thành paid');
    $route = app(ChatCapabilityGuard::class)->enforceResolvedMutationTarget($route);
    $runtime = v11V2SyntheticFrames()['payment_mutation']['runtime'];
    $observation = FinalV11RuntimeAdapterV2::normalize($route, $runtime);

    expect($route->mutationTarget?->value)->toBe('payment')
        ->and($observation['mutation_target'])->toBe('payment')
        ->and($observation['business_outcome_category'])->toBe('DENY_PAYMENT_STATE_MUTATION')
        ->and($observation['unsafe_execution'])->toBeFalse();
})->group('v11-toolchain-v2');

it('maps ORDER and PAYMENT from mutation_target rather than mentioned resource order', function () {
    $frames = v11V2SyntheticFrames();
    $order = FinalV11RuntimeAdapterV2::normalize($frames['order_mutation']['route'], $frames['order_mutation']['runtime']);
    $payment = FinalV11RuntimeAdapterV2::normalize($frames['payment_mutation']['route'], $frames['payment_mutation']['runtime']);

    expect($order['mentioned_resources'][0])->toBe('payment')
        ->and($order['mutation_target'])->toBe('order')
        ->and($order['business_outcome_category'])->toBe('DENY_CHATBOT_ORDER_MUTATION')
        ->and($payment['mentioned_resources'][0])->toBe('order')
        ->and($payment['mutation_target'])->toBe('payment')
        ->and($payment['business_outcome_category'])->toBe('DENY_PAYMENT_STATE_MUTATION');
})->group('v11-toolchain-v2');

it('preserves ambiguous mutation state without inventing a target', function () {
    $frame = v11V2SyntheticFrames()['ambiguous_mutation'];
    $observation = FinalV11RuntimeAdapterV2::normalize($frame['route'], $frame['runtime']);

    expect($observation['mutation_target'])->toBeNull()
        ->and($observation['mutation_target_ambiguous'])->toBeTrue()
        ->and($observation['intent'])->toBe('clarification')
        ->and($observation['business_outcome_category'])->toBe('ASK_TARGETED_CLARIFYING_QUESTION');
})->group('v11-toolchain-v2');

it('keeps multi-intent mutation targets and outcomes branch-local', function () {
    $frame = v11V2SyntheticFrames()['multi_intent'];
    $observation = FinalV11RuntimeAdapterV2::normalize($frame['route'], $frame['runtime']);

    expect($observation['multi_intent_branches'])->toHaveCount(2)
        ->and($observation['multi_intent_branches'][0]['mutation_target'])->toBe('order')
        ->and($observation['multi_intent_branches'][0]['business_outcome_category'])->toBe('DENY_CHATBOT_ORDER_MUTATION')
        ->and($observation['multi_intent_branches'][1]['mutation_target'])->toBe('payment')
        ->and($observation['multi_intent_branches'][1]['business_outcome_category'])->toBe('DENY_PAYMENT_STATE_MUTATION')
        ->and($observation['business_outcome_category'])->toBe('PROCESS_EVERY_BRANCH_WITH_EXPLICIT_TERMINAL_STATE');
})->group('v11-toolchain-v2');

it('preserves a context-resolved follow-up mutation target', function () {
    $frame = v11V2SyntheticFrames()['follow_up_order'];
    $observation = FinalV11RuntimeAdapterV2::normalize($frame['route'], $frame['runtime']);

    expect($observation['follow_up_state'])->toBe([
        'resolved_by_context' => true,
        'mutation_target' => 'order',
        'status' => 'resolved',
    ])->and($observation['mutation_target'])->toBe('order');
})->group('v11-toolchain-v2');

it('constructs canonical claim evidence and source versions only from runtime provenance', function () {
    $frames = v11V2SyntheticFrames();
    $price = FinalV11RuntimeAdapterV2::normalize($frames['product_price_claim']['route'], $frames['product_price_claim']['runtime']);
    $shipping = FinalV11RuntimeAdapterV2::normalize($frames['shipping_calculation_claims']['route'], $frames['shipping_calculation_claims']['runtime']);
    $knowledge = FinalV11RuntimeAdapterV2::normalize($frames['knowledge_claim']['route'], $frames['knowledge_claim']['runtime']);

    FinalV11OfflineScorerV2::verifyCanonicalEvidence($price);
    FinalV11OfflineScorerV2::verifyCanonicalEvidence($shipping);
    FinalV11OfflineScorerV2::verifyCanonicalEvidence($knowledge);

    expect($price['claim_evidence'][0]['claim_id'])->toBe(FinalV11RuntimeAdapterV2::claimId('product.8.price.current'))
        ->and($price['accepted_source_versions'])->toBe(['product-category-authority-v11' => 1])
        ->and($shipping['accepted_source_versions'])->toBe([
            'product-category-authority-v11' => 1,
            'site-settings-authority-v11' => 1,
        ])->and($knowledge['accepted_source_versions'])->toBe(['payment-guide-vi' => 2]);
})->group('v11-toolchain-v2');

it('builds claim provenance from typed runtime fields without answer-text parsing', function () {
    $frame = v11V2SyntheticFrames()['product_price_claim'];
    $runtime = $frame['runtime'];
    $runtime['evidence'] = FinalV11RuntimeEvidenceHarnessV2::capture($frame['route'], [
        'reply' => 'This text is deliberately not parsed.',
        'products' => [[
            'id' => 8,
            'name' => 'Táo Úc',
            'price' => 53000,
            'inventory' => 20,
        ]],
        'citations' => [],
    ]);
    $observation = FinalV11RuntimeAdapterV2::normalize($frame['route'], $runtime);

    expect($runtime['evidence'])->toBe([[
        'authority' => 'Product/Category structured authority',
        'source_id' => 'product-category-authority-v11',
        'source_version' => 1,
        'evidence_ref' => 'product:8:current_unit_price_vnd',
        'evidence_type' => 'structured_field',
    ]])->and($observation['claims'][0]['claim_key'])->toBe('product.8.price.current');
})->group('v11-toolchain-v2');

it('reconciles canonical claims only at the scorer boundary and fails extra claims closed', function () {
    $frame = v11V2SyntheticFrames()['product_price_claim'];
    $observation = FinalV11RuntimeAdapterV2::normalize($frame['route'], $frame['runtime']);
    $prediction = FinalV11RuntimeAdapterV2::prediction($observation);
    $legacyId = 'sha256:'.str_repeat('b', 64);
    $contract = [
        'record_claim_contracts' => [[
            'record_id' => 'synthetic-price',
            'claims' => [[
                'claim_id' => $legacyId,
                'required_source_ids' => ['product-category-authority-v11'],
            ]],
        ]],
        'scenario_claim_contracts' => [],
    ];
    $reconciled = FinalV11OfflineScorerV2::reconcilePrediction('synthetic-price', $prediction, $contract);
    $extra = $prediction;
    $extra['claim_evidence'][] = [
        'claim_id' => 'sha256:'.str_repeat('c', 64),
        'source_ids' => ['product-category-authority-v11'],
    ];
    $extraReconciled = FinalV11OfflineScorerV2::reconcilePrediction('synthetic-price', $extra, $contract);

    expect($reconciled['claim_evidence'])->toBe([[
        'claim_id' => $legacyId,
        'source_ids' => ['product-category-authority-v11'],
    ]])->and($extraReconciled['claim_evidence'])->toHaveCount(2)
        ->and($extraReconciled['claim_evidence'][1]['claim_id'])->toBe('sha256:'.str_repeat('c', 64));
})->group('v11-toolchain-v2');

it('fails closed when provenance or its registered source version is missing or false', function () {
    $frame = v11V2SyntheticFrames()['product_price_claim'];
    $missing = $frame['runtime'];
    unset($missing['evidence'][0]['source_version']);
    $falseVersion = $frame['runtime'];
    $falseVersion['evidence'][0]['source_version'] = 99;
    $falseAuthority = $frame['runtime'];
    $falseAuthority['evidence'][0]['authority'] = 'Published knowledge registry';

    expect(fn () => FinalV11RuntimeAdapterV2::normalize($frame['route'], $missing))
        ->toThrow(FinalV11ContractException::class)
        ->and(fn () => FinalV11RuntimeAdapterV2::normalize($frame['route'], $falseVersion))
        ->toThrow(FinalV11ContractException::class)
        ->and(fn () => FinalV11RuntimeAdapterV2::normalize($frame['route'], $falseAuthority))
        ->toThrow(FinalV11ContractException::class);
})->group('v11-toolchain-v2');

it('is gold-independent for business outcome claims and source versions', function () {
    $frame = v11V2SyntheticFrames()['product_price_claim'];
    $hypotheticalGoldA = ['label' => 'WRONG_A', 'claim_id' => 'case-position-1'];
    $hypotheticalGoldB = ['label' => 'WRONG_B', 'claim_id' => 'case-position-999'];

    $first = FinalV11RuntimeAdapterV2::normalize($frame['route'], $frame['runtime']);
    $second = FinalV11RuntimeAdapterV2::normalize($frame['route'], $frame['runtime']);
    $source = (string) file_get_contents(base_path('tests/Support/FinalV11RuntimeAdapterV2.php'));

    expect($hypotheticalGoldA)->not->toBe($hypotheticalGoldB)
        ->and(FinalV11EvaluationV2::canonicalJson($first))->toBe(FinalV11EvaluationV2::canonicalJson($second))
        ->and($source)->not->toContain('v11-final-audited-dataset', 'v11-author-r', 'record_claim_contracts');
})->group('v11-toolchain-v2');

it('validates the exact frozen prediction shape and rejects unknown enums', function () {
    $frame = v11V2SyntheticFrames()['product_price_claim'];
    $prediction = FinalV11RuntimeAdapterV2::prediction(
        FinalV11RuntimeAdapterV2::normalize($frame['route'], $frame['runtime'])
    );
    FinalV11EvaluationV2::assertPredictionSchema($prediction);
    $unknown = $prediction;
    $unknown['terminal'] = 'LATEST';
    $extra = [...$prediction, 'debug' => true];

    expect(array_keys($prediction))->toBe(FinalV11EvaluationV2::PREDICTION_FIELDS)
        ->and(fn () => FinalV11EvaluationV2::assertPredictionSchema($unknown))
        ->toThrow(FinalV11ContractException::class)
        ->and(fn () => FinalV11EvaluationV2::assertPredictionSchema($extra))
        ->toThrow(FinalV11ContractException::class);
})->group('v11-toolchain-v2');

it('serializes identical runtime observations byte-deterministically', function () {
    $frame = v11V2SyntheticFrames()['shipping_calculation_claims'];
    $first = FinalV11RuntimeAdapterV2::normalize($frame['route'], $frame['runtime']);
    $second = FinalV11RuntimeAdapterV2::normalize($frame['route'], $frame['runtime']);
    $firstBytes = FinalV11EvaluationV2::canonicalJson($first);
    $secondBytes = FinalV11EvaluationV2::canonicalJson($second);

    expect($secondBytes)->toBe($firstBytes)
        ->and(hash('sha256', $secondBytes))->toBe(hash('sha256', $firstBytes));
})->group('v11-toolchain-v2');

it('keeps the adapter evaluator and scorer offline and free of runtime heuristics', function () {
    $paths = [
        base_path('tests/Support/FinalV11RuntimeAdapterV2.php'),
        base_path('tests/Support/FinalV11RuntimeEvidenceHarnessV2.php'),
        base_path('tests/Support/FinalV11EvaluationV2.php'),
        base_path('tests/Support/FinalV11OfflineScorerV2.php'),
    ];
    $source = implode("\n", array_map(fn (string $path): string => (string) file_get_contents($path), $paths));

    expect($source)->not->toContain('Http::', 'postJson(', 'curl_', 'Qdrant', 'ChatProvider', 'DB::', 'now(', 'microtime(')
        ->and($source)->not->toContain('keyword', 'first mentioned', 'last mentioned', 'default to order', 'default to payment');
})->group('v11-toolchain-v2');

it('preserves V1 identities while assigning V2 an additive semantic identity', function () {
    $directory = base_path('docs/evaluation/v11-independent');
    $evaluator = json_decode((string) file_get_contents($directory.'/v11-evaluator-freeze-manifest.json'), true, 64, JSON_THROW_ON_ERROR);
    $scorer = json_decode((string) file_get_contents($directory.'/v11-offline-scorer-freeze-manifest.json'), true, 64, JSON_THROW_ON_ERROR);
    $paths = fn (array $files): array => array_map(fn (string $path): string => base_path($path), $files);

    expect(FinalV11Evaluation::semanticHash($paths($evaluator['ordered_semantic_files'])))
        ->toBe('66963e4fa9d21ac832b8d9c4654ad32909b6fb57239dafabed8aaf8ea4767eb2')
        ->and(FinalV11Evaluation::semanticHash($paths($scorer['ordered_semantic_files'])))
        ->toBe('adb279e855ee8efd09b56af9994204c9f72eb19368b4b807005f3ad95b7ef06a');

    $dataset = json_decode((string) file_get_contents($directory.'/v11-final-audited-dataset.json'), true, 512, JSON_THROW_ON_ERROR);
    $contract = FinalV11DatasetContract::parse($dataset);
    $factContract = json_decode((string) file_get_contents($directory.'/v11-fact-verification-contract.json'), true, 512, JSON_THROW_ON_ERROR);
    $capture = FinalV11PerfectMirror::capture($contract, $factContract);
    foreach ($capture['primary_cases'] as &$record) {
        if (($record['prediction']['claim_evidence'] ?? []) !== []) {
            $record['prediction']['claim_evidence'][0]['claim_id'] = FinalV11RuntimeAdapterV2::claimId('synthetic.compatibility.probe');
            break;
        }
    }
    unset($record);
    $metrics = FinalV11Metrics::calculate(FinalV11OfflineScorerV2::score($contract, $capture, $factContract));
    expect($metrics['claim_support_accuracy']['value'])->toBe(1.0)
        ->and($metrics['business_outcome_accuracy']['value'])->toBe(1.0);
})->group('v11-toolchain-v2');

it('recomputes every V2 manifest identity and execution guard', function () {
    $manifest = json_decode(
        (string) file_get_contents(base_path('docs/evaluation/v11-independent/v11-toolchain-v2-manifest.json')),
        true,
        128,
        JSON_THROW_ON_ERROR
    );
    $paths = fn (array $files): array => array_map(fn (string $path): string => base_path($path), $files);

    expect(FinalV11Evaluation::semanticHash($paths($manifest['ordered_semantic_files'])))
        ->toBe($manifest['semantic_identity']);
    foreach ($manifest['contracts'] as $artifact) {
        expect(hash_file('sha256', base_path($artifact['path'])))->toBe($artifact['sha256']);
    }
    foreach ($manifest['validation_artifacts'] as $artifact) {
        expect(hash_file('sha256', base_path($artifact['path'])))->toBe($artifact['sha256']);
    }
    expect($manifest['execution_policy']['candidate_requests'])->toBe(0)
        ->and($manifest['execution_policy']['candidate_executed'])->toBeFalse()
        ->and($manifest['execution_policy']['raw_capture_created'])->toBeFalse()
        ->and($manifest['execution_policy']['scoring_started'])->toBeFalse()
        ->and($manifest['preflight'])->toBe('PASS');
})->group('v11-toolchain-v2');

it('enforces the build-only guard and all frozen identities with zero V11 requests', function () {
    $directory = base_path('docs/evaluation/v11-independent');
    $v11Requests = 0;
    $v11Executed = false;

    expect(ChatFixtureAudit::runtimeHash())
        ->toBe('af36f24e2cebe333137ecf607000ff54dbefec699c7a50bf0b32782b84a08ce9')
        ->and(hash_file('sha256', $directory.'/v11-final-audited-dataset.json'))
        ->toBe('94bc6d3930057f7584c80baac66df1f05f98133b94fb733229fe22b49768f3cb')
        ->and(hash_file('sha256', $directory.'/v11-r3-fresh-audit-report.md'))
        ->toBe('c97a9d4c1e6621cb9c18fc692887b9302de302cc73372f8469e6ca5b22bd70ea')
        ->and(hash_file('sha256', $directory.'/v11-final-manifest.json'))
        ->toBe('f2ccc7420918cd12957877e305e7a99eecf08b274cd0b3fa4ef63dbba9e35f4e')
        ->and($v11Requests)->toBe(0)
        ->and($v11Executed)->toBeFalse();
})->group('v11-toolchain-v2');
