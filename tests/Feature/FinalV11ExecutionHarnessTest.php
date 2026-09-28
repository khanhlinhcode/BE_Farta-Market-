<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\Support\ChatFixtureAudit;
use Tests\Support\FinalV11ContractException;
use Tests\Support\FinalV11DatasetContract;
use Tests\Support\FinalV11Evaluation;
use Tests\Support\FinalV11ExecutionGuard;
use Tests\Support\FinalV11ExecutionHarness;
use Tests\Support\FinalV11RawCapture;

/** @return array<string, mixed> */
function v11HarnessSynthetic(): array
{
    static $fixtures;

    return $fixtures ??= require base_path('tests/Fixtures/v11_final_execution_harness_synthetic.php');
}

function v11HarnessState(): string
{
    return str_repeat('a', 64);
}

it('runs the synthetic single-turn harness through Toolchain V2', function () {
    $fixture = v11HarnessSynthetic()['single_turn'];
    $result = FinalV11ExecutionHarness::observe(
        $fixture['route'], $fixture['response'], 'anonymous', v11HarnessState(), v11HarnessState()
    );

    expect($result['prediction']['intent'])->toBe('product_search')
        ->and($result['prediction']['terminal'])->toBe('ANSWER')
        ->and($result['prediction']['security_decision'])->toBe('ALLOW_READ')
        ->and($result['adapter_observation']['mutation_target'])->toBeNull();
})->group('v11-final-harness');

it('preserves one synthetic conversation across ordered follow-up turns', function () {
    $turns = v11HarnessSynthetic()['multi_turn']['turns'];
    $observations = [];
    foreach ($turns as $turn) {
        $observations[] = FinalV11ExecutionHarness::observe(
            $turn['route'], $turn['response'], 'anonymous', v11HarnessState(), v11HarnessState()
        );
    }

    expect($turns[0]['input']['session_id'])->toBe($turns[1]['input']['session_id'])
        ->and($turns[0]['input']['candidate_request']['message'])->not->toBe($turns[1]['input']['candidate_request']['message'])
        ->and($observations[1]['prediction']['intent'])->toBe('price')
        ->and($observations[1]['prediction']['entities']['context_reference'])->toBe('that product');
})->group('v11-final-harness');

it('captures synthetic multi-intent branches without splitting the request', function () {
    $fixture = v11HarnessSynthetic()['multi_intent'];
    $result = FinalV11ExecutionHarness::observe(
        $fixture['route'], $fixture['response'], 'anonymous', v11HarnessState(), v11HarnessState()
    );

    expect($result['adapter_observation']['multi_intent_branches'])->toHaveCount(2)
        ->and($result['prediction']['intent'])->toBe('multi_intent')
        ->and($result['prediction']['terminal'])->toBe('PARTIAL_MIXED_TERMINALS');
})->group('v11-final-harness');

it('preserves synthetic ORDER PAYMENT and ambiguous mutation targets', function () {
    $fixtures = v11HarnessSynthetic();
    $order = FinalV11ExecutionHarness::observe(
        $fixtures['order_mutation']['route'], $fixtures['order_mutation']['response'], 'anonymous', v11HarnessState(), v11HarnessState()
    );
    $payment = FinalV11ExecutionHarness::observe(
        $fixtures['payment_mutation']['route'], $fixtures['payment_mutation']['response'], 'anonymous', v11HarnessState(), v11HarnessState()
    );
    $ambiguous = FinalV11ExecutionHarness::observe(
        $fixtures['ambiguous_mutation']['route'], $fixtures['ambiguous_mutation']['response'], 'anonymous', v11HarnessState(), v11HarnessState()
    );

    expect($order['adapter_observation']['mutation_target'])->toBe('order')
        ->and($payment['adapter_observation']['mutation_target'])->toBe('payment')
        ->and($ambiguous['adapter_observation']['mutation_target'])->toBeNull()
        ->and($ambiguous['adapter_observation']['mutation_target_ambiguous'])->toBeTrue();
})->group('v11-final-harness');

it('captures synthetic claim evidence only from structured runtime provenance', function () {
    $fixture = v11HarnessSynthetic()['claim_evidence'];
    $result = FinalV11ExecutionHarness::observe(
        $fixture['route'], $fixture['response'], 'anonymous', v11HarnessState(), v11HarnessState()
    );

    expect($result['prediction']['accepted_source_ids'])->toBe(['product-category-authority-v11'])
        ->and($result['prediction']['accepted_source_versions'])->toBe(['product-category-authority-v11' => 1])
        ->and($result['prediction']['claim_evidence'])->toHaveCount(1);
})->group('v11-final-harness');

it('captures synthetic security denial without protected state change', function () {
    $fixture = v11HarnessSynthetic()['security'];
    $result = FinalV11ExecutionHarness::observe(
        $fixture['route'], $fixture['response'], 'anonymous', v11HarnessState(), v11HarnessState()
    );

    expect($result['prediction']['security_decision'])->toBe('DENY')
        ->and($result['prediction']['unsafe_execution'])->toBeFalse()
        ->and($result['prediction']['wrong_entity_unsafe_action'])->toBeFalse();
})->group('v11-final-harness');

it('keeps gold fields out of candidate inputs', function () {
    $primary = FinalV11ExecutionHarness::primaryInput([
        'case_id' => 'synthetic-gold-isolation',
        'utterance' => 'Synthetic user input.',
        'language_bucket' => 'en',
        'preconditions' => ['actor' => 'anonymous'],
        'multi_intent_branches' => [],
        'gold_intent' => 'must-not-cross-input-boundary',
    ]);
    $turn = FinalV11ExecutionHarness::scenarioTurnInput(
        [
            'scenario_id' => 'synthetic-gold-isolation-scenario',
            'language_bucket' => 'en',
            'preconditions' => ['actor' => 'anonymous'],
            'final_expected_intent' => 'must-not-cross-input-boundary',
        ],
        [
            'turn' => 1,
            'utterance' => 'Synthetic scenario input.',
            'expected_intent' => 'must-not-cross-input-boundary',
        ]
    );

    expect(array_keys($primary['candidate_request']))->toBe(['message'])
        ->and(array_keys($turn['candidate_request']))->toBe(['message'])
        ->and(fn () => FinalV11ExecutionHarness::assertGoldIsolation([
            'message' => 'synthetic', 'expected_intent' => 'price',
        ]))->toThrow(FinalV11ContractException::class, 'Gold-derived');
})->group('v11-final-harness');

it('accepts only the in-process local candidate target', function () {
    $config = json_decode(
        (string) file_get_contents(base_path('docs/evaluation/v11-independent/v11-final-execution-harness-config.json')),
        true,
        64,
        JSON_THROW_ON_ERROR
    );
    $source = (string) file_get_contents(base_path('tests/Feature/FinalV11FinalExecutionTest.php'));

    expect(FinalV11ExecutionHarness::EXECUTION_TARGET)->toBe('LOCAL_CANDIDATE')
        ->and($config['execution_target'])->toBe('LOCAL_CANDIDATE')
        ->and($config['candidate_entry_point'])->toBe('POST /api/chat (Laravel in-process test kernel)')
        ->and($source)->not->toMatch('/https?:\/\/(?!127\.0\.0\.1|localhost)/i');
})->group('v11-final-harness');

it('finds the existing local runtime entry point and writable artifact directory', function () {
    $chatRoute = collect(Route::getRoutes()->getRoutes())->first(
        fn (\Illuminate\Routing\Route $route): bool => $route->uri() === 'api/chat'
            && in_array('POST', $route->methods(), true)
    );
    $directory = base_path('docs/evaluation/v11-independent');

    expect($chatRoute)->not->toBeNull()
        ->and($chatRoute?->getActionName())->toBe('App\\Http\\Controllers\\ChatController@send')
        ->and(is_dir($directory))->toBeTrue()
        ->and(is_writable($directory))->toBeTrue();
})->group('v11-final-harness');

it('refuses a second execution after the synthetic one-shot transition', function () {
    $directory = sys_get_temp_dir().'/v11-guard-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700, true);
    $path = $directory.'/state.json';
    try {
        FinalV11ExecutionGuard::acquire($path, 'synthetic-run', ['candidate' => str_repeat('a', 64)], '2026-09-28T00:00:00Z');
        FinalV11ExecutionGuard::markExecuting($path);
        FinalV11ExecutionGuard::recordStarted($path, 'synthetic-single-turn');
        FinalV11ExecutionGuard::recordCompleted($path, 'synthetic-single-turn');
        FinalV11ExecutionGuard::complete(
            $path,
            ['path' => 'synthetic.raw', 'sha256' => str_repeat('b', 64)],
            ['path' => 'synthetic.scored', 'sha256' => str_repeat('c', 64)],
            '2026-09-28T00:00:01Z'
        );

        expect(FinalV11ExecutionGuard::read($path)['execution_state'])->toBe('COMPLETED')
            ->and(fn () => FinalV11ExecutionGuard::acquire(
                $path, 'synthetic-second-run', [], '2026-09-28T00:00:02Z'
            ))->toThrow(FinalV11ContractException::class, 'refusing a second execution');
    } finally {
        @unlink($path);
        @rmdir($directory);
    }
})->group('v11-final-harness');

it('marks an interrupted synthetic execution incomplete without retry', function () {
    $directory = sys_get_temp_dir().'/v11-incomplete-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700, true);
    $path = $directory.'/state.json';
    try {
        FinalV11ExecutionGuard::acquire($path, 'synthetic-incomplete', [], '2026-09-28T00:00:00Z');
        FinalV11ExecutionGuard::markExecuting($path);
        FinalV11ExecutionGuard::recordStarted($path, 'synthetic-failure');
        $state = FinalV11ExecutionGuard::recordFailed($path, 'synthetic-failure', 'Synthetic failure');

        expect($state['execution_state'])->toBe('EXECUTION_INCOMPLETE')
            ->and($state['cases_started'])->toBe(1)
            ->and($state['cases_completed'])->toBe(0)
            ->and($state['cases_failed'])->toBe(1)
            ->and($state['last_case'])->toBe('synthetic-failure');
    } finally {
        @unlink($path);
        @rmdir($directory);
    }
})->group('v11-final-harness');

it('is deterministic apart from declared clock and latency metadata', function () {
    $fixture = v11HarnessSynthetic()['claim_evidence'];
    $first = FinalV11ExecutionHarness::observe(
        $fixture['route'], $fixture['response'], 'anonymous', v11HarnessState(), v11HarnessState()
    );
    $second = FinalV11ExecutionHarness::observe(
        $fixture['route'], $fixture['response'], 'anonymous', v11HarnessState(), v11HarnessState()
    );
    $first['started_at_utc'] = '2026-09-28T00:00:00Z';
    $second['started_at_utc'] = '2026-09-28T00:00:01Z';
    $first['latency_ms'] = 1.0;
    $second['latency_ms'] = 999.0;

    expect(FinalV11ExecutionHarness::deterministicHash($first))
        ->toBe(FinalV11ExecutionHarness::deterministicHash($second));
})->group('v11-final-harness');

it('writes and freezes a synthetic raw capture without a V11 request', function () {
    $directory = sys_get_temp_dir().'/v11-capture-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700, true);
    $path = $directory.'/synthetic.jsonl';
    try {
        FinalV11RawCapture::initialize($path, [
            'run_id' => 'synthetic-capture',
            'runtime_adapter_version' => \Tests\Support\FinalV11RuntimeAdapterV2::VERSION,
        ]);
        FinalV11RawCapture::appendPrimary($path, [
            'case_id' => 'synthetic-single-turn',
            'prediction' => v11HarnessSynthetic()['single_turn']['route'],
        ]);
        $hash = FinalV11ExecutionHarness::freezeArtifact($path);

        expect($hash)->toMatch('/^[a-f0-9]{64}$/')
            ->and(is_writable($path))->toBeFalse()
            ->and(FinalV11RawCapture::read($path)['primary_cases'])->toHaveCount(1);
    } finally {
        @chmod($path, 0600);
        @unlink($path);
        @rmdir($directory);
    }
})->group('v11-final-harness');

it('keeps synthetic tests and harness preflight offline', function () {
    Http::preventStrayRequests();
    $paths = [
        base_path('tests/Support/FinalV11ExecutionHarness.php'),
        base_path('tests/Support/FinalV11ExecutionGuard.php'),
        base_path('scripts/v11-final-execution-harness-preflight'),
    ];
    $source = implode("\n", array_map(fn (string $path): string => (string) file_get_contents($path), $paths));

    expect($source)->not->toContain('Http::get(', 'Http::post(', 'curl_', 'file_get_contents("http')
        ->and($source)->not->toContain('production.', 'app.fartamarket');
})->group('v11-final-harness');

it('preflights frozen identities schema counts outputs and zero V11 requests', function () {
    $directory = base_path('docs/evaluation/v11-independent');
    $toolchain = json_decode((string) file_get_contents($directory.'/v11-toolchain-v2-manifest.json'), true, 128, JSON_THROW_ON_ERROR);
    $evaluator = json_decode((string) file_get_contents($directory.'/v11-evaluator-freeze-manifest.json'), true, 64, JSON_THROW_ON_ERROR);
    $scorer = json_decode((string) file_get_contents($directory.'/v11-offline-scorer-freeze-manifest.json'), true, 64, JSON_THROW_ON_ERROR);
    $paths = fn (array $files): array => array_map(fn (string $path): string => base_path($path), $files);
    $contract = FinalV11DatasetContract::load($directory.'/v11-final-audited-dataset.json');
    $evaluatorV2 = [...$evaluator['ordered_semantic_files'], 'tests/Support/FinalV11EvaluationV2.php'];
    $scorerV2 = [...$scorer['ordered_semantic_files'], 'tests/Support/FinalV11EvaluationV2.php', 'tests/Support/FinalV11OfflineScorerV2.php'];
    $adapter = ['tests/Support/FinalV11RuntimeAdapterV2.php', 'tests/Support/FinalV11RuntimeEvidenceHarnessV2.php'];
    $executionOutputs = [
        'v11-final-raw-capture.jsonl',
        'v11-final-scored-results.json',
        'v11-final-score-report.md',
        'v11-final-execution-manifest.json',
        'v11-final-execution-state.json',
    ];

    expect(ChatFixtureAudit::runtimeHash())->toBe(FinalV11ExecutionHarness::CANDIDATE_SHA256)
        ->and(hash_file('sha256', $directory.'/v11-final-audited-dataset.json'))->toBe(FinalV11ExecutionHarness::DATASET_SHA256)
        ->and(hash_file('sha256', $directory.'/v11-r3-fresh-audit-report.md'))->toBe(FinalV11ExecutionHarness::AUDIT_SHA256)
        ->and(hash_file('sha256', $directory.'/v11-final-manifest.json'))->toBe(FinalV11ExecutionHarness::MANIFEST_SHA256)
        ->and(hash_file('sha256', $directory.'/v11-evaluation-contract.json'))->toBe(FinalV11ExecutionHarness::EVALUATION_CONTRACT_SHA256)
        ->and(hash_file('sha256', $directory.'/v11-fact-verification-contract.json'))->toBe(FinalV11ExecutionHarness::FACT_CONTRACT_SHA256)
        ->and(hash_file('sha256', $directory.'/v11-runtime-adapter-v2-contract.json'))->toBe(FinalV11ExecutionHarness::ADAPTER_CONTRACT_SHA256)
        ->and(FinalV11Evaluation::semanticHash($paths($toolchain['ordered_semantic_files'])))->toBe(FinalV11ExecutionHarness::TOOLCHAIN_SHA256)
        ->and(FinalV11Evaluation::semanticHash($paths($evaluatorV2)))->toBe(FinalV11ExecutionHarness::EVALUATOR_SHA256)
        ->and(FinalV11Evaluation::semanticHash($paths($scorerV2)))->toBe(FinalV11ExecutionHarness::SCORER_SHA256)
        ->and(FinalV11Evaluation::semanticHash($paths($adapter)))->toBe(FinalV11ExecutionHarness::ADAPTER_SHA256)
        ->and($contract['counts'])->toMatchArray([
            'primary' => 160,
            'multi_turn_scenarios' => 20,
            'scenario_turns' => 40,
            'multi_intent_branches' => 40,
            'entity_objects' => 240,
        ])
        ->and(collect($executionOutputs)->every(fn (string $file): bool => ! file_exists($directory.'/'.$file)))->toBeTrue()
        ->and(0)->toBe(0);
})->group('v11-final-harness');

it('recomputes the frozen final-execution harness identity and ready status', function () {
    $manifest = json_decode(
        (string) file_get_contents(base_path('docs/evaluation/v11-independent/v11-final-execution-harness-manifest.json')),
        true,
        128,
        JSON_THROW_ON_ERROR
    );
    $paths = fn (array $files): array => array_map(fn (string $path): string => base_path($path), $files);

    expect(FinalV11Evaluation::semanticHash($paths($manifest['ordered_semantic_files'])))
        ->toBe($manifest['harness_identity'])
        ->and(FinalV11Evaluation::semanticHash($paths($manifest['test_suite']['ordered_files'])))
        ->toBe($manifest['test_suite']['semantic_identity'])
        ->and($manifest['status'])->toBe('READY_FOR_FINAL_EXECUTION')
        ->and($manifest['execution_target'])->toBe('LOCAL_CANDIDATE')
        ->and($manifest['v11_requests'])->toBe(0)
        ->and($manifest['v11_executed'])->toBeFalse();
})->group('v11-final-harness');
