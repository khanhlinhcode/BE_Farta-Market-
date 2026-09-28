<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Chat\ChatCapabilityGuard;
use App\Services\Chat\ChatContextResolver;
use App\Services\Chat\ChatEntityCanonicalizer;
use App\Services\Chat\ChatEntityExtractor;
use App\Services\Chat\ChatIntentRouter;
use App\Services\Chat\ChatKnowledgeSyncService;
use App\Services\Chat\ChatRouteFrame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\ChatFixtureAudit;
use Tests\Support\FinalV11DatasetContract;
use Tests\Support\FinalV11EvaluationV2;
use Tests\Support\FinalV11ExecutionGuard;
use Tests\Support\FinalV11ExecutionHarness;
use Tests\Support\FinalV11Metrics;
use Tests\Support\FinalV11OfflineScorerV2;
use Tests\Support\FinalV11RawCapture;
use Tests\Support\FinalV11RuntimeAdapterV2;

uses(RefreshDatabase::class);

/** @return array<string, mixed> */
function finalV11BusinessSnapshot(): array
{
    return [
        'products' => Product::query()->orderBy('id')->get(['id', 'price', 'inventory', 'is_active'])->toArray(),
        'orders' => Order::query()->orderBy('id')->get(['id', 'user_id', 'status', 'payment_status', 'grand_total'])->toArray(),
        'users' => User::query()->orderBy('id')->get(['id', 'role', 'email_verified_at'])->toArray(),
    ];
}

function finalV11StateHash(): string
{
    return hash('sha256', FinalV11EvaluationV2::canonicalJson(finalV11BusinessSnapshot()));
}

/** @param array<string, int> $symbols */
function finalV11Render(string $utterance, array $symbols): string
{
    return str_replace(array_keys($symbols), array_map('strval', array_values($symbols)), $utterance);
}

/** @param array<string, string> $reverseOrderReferences */
function finalV11RestoreOrderReferences(ChatRouteFrame $route, array $reverseOrderReferences): ChatRouteFrame
{
    if ($route->branches !== []) {
        $route = $route->withBranches(array_map(
            fn (ChatRouteFrame $branch): ChatRouteFrame => finalV11RestoreOrderReferences($branch, $reverseOrderReferences),
            $route->branches
        ));
    }
    $entities = $route->entities;
    foreach (['order_reference', 'order_id'] as $field) {
        $value = $entities[$field] ?? null;
        if ((is_int($value) || is_string($value)) && isset($reverseOrderReferences[(string) $value])) {
            $entities['order_reference'] = $reverseOrderReferences[(string) $value];
        }
    }

    return $route->withEntities($entities);
}

/** @param array<string, mixed>|null $context @return array{resolved_by_context: bool, mutation_target: ?string, status: string} */
function finalV11FollowUpState(ChatRouteFrame $base, ChatRouteFrame $resolved, ?array $context): array
{
    $contextTarget = is_string($context['mutation_target'] ?? null) ? $context['mutation_target'] : null;
    $resolvedByContext = $base->mutationTarget === null
        && $resolved->mutationTarget !== null
        && $contextTarget === $resolved->mutationTarget->value;

    return [
        'resolved_by_context' => $resolvedByContext,
        'mutation_target' => $resolvedByContext ? $resolved->mutationTarget?->value : null,
        'status' => $resolvedByContext
            ? 'resolved'
            : ($resolved->mutationTargetAmbiguous ? 'ambiguous' : 'not_applicable'),
    ];
}

/** @param array<string, mixed> $response @return array<string, mixed>|null */
function finalV11NextContext(ChatRouteFrame $route, array $response): ?array
{
    if ($route->operation === 'mutate' && $route->mutationTarget !== null) {
        return ['product_ids' => [], 'mutation_target' => $route->mutationTarget->value, 'stage' => 'mutation_target'];
    }
    if ($route->semanticIntent === 'general_chat') {
        return null;
    }
    $products = array_values(array_filter(
        $response['products'] ?? [],
        fn (mixed $product): bool => is_array($product) && is_int($product['id'] ?? null)
    ));
    if ($products === []) {
        return null;
    }
    $categoryIds = array_values(array_unique(array_filter(
        array_map(fn (array $product): mixed => $product['category']['id'] ?? null, $products),
        'is_int'
    )));

    return [
        'product_ids' => array_values(array_unique(array_column($products, 'id'))),
        'category_id' => count($categoryIds) === 1 ? $categoryIds[0] : null,
        'stage' => 'context',
    ];
}

it('executes the frozen V11 exactly once only under separate authorization', function () {
    expect(getenv('V11_FINAL_EXECUTION_AUTHORIZATION') ?: '')->toBe(
        'RUN_FROZEN_V11_ONCE',
        'This runner is frozen but not authorized. Use the dedicated run-once prompt and command.'
    );

    $directory = base_path('docs/evaluation/v11-independent');
    $config = json_decode((string) file_get_contents($directory.'/v11-final-execution-harness-config.json'), true, 64, JSON_THROW_ON_ERROR);
    expect($config['execution_target'])->toBe(FinalV11ExecutionHarness::EXECUTION_TARGET)
        ->and(ChatFixtureAudit::runtimeHash())->toBe(FinalV11ExecutionHarness::CANDIDATE_SHA256)
        ->and(hash_file('sha256', $directory.'/v11-final-audited-dataset.json'))->toBe(FinalV11ExecutionHarness::DATASET_SHA256)
        ->and(hash_file('sha256', $directory.'/v11-r3-fresh-audit-report.md'))->toBe(FinalV11ExecutionHarness::AUDIT_SHA256)
        ->and(hash_file('sha256', $directory.'/v11-final-manifest.json'))->toBe(FinalV11ExecutionHarness::MANIFEST_SHA256)
        ->and(hash_file('sha256', $directory.'/v11-evaluation-contract.json'))->toBe(FinalV11ExecutionHarness::EVALUATION_CONTRACT_SHA256)
        ->and(hash_file('sha256', $directory.'/v11-fact-verification-contract.json'))->toBe(FinalV11ExecutionHarness::FACT_CONTRACT_SHA256)
        ->and(hash_file('sha256', $directory.'/v11-runtime-adapter-v2-contract.json'))->toBe(FinalV11ExecutionHarness::ADAPTER_CONTRACT_SHA256);

    $paths = $config['outputs'];
    $rawPath = base_path($paths['raw_capture']);
    $scoredPath = base_path($paths['scored_results']);
    $scoreReportPath = base_path($paths['score_report']);
    $manifestPath = base_path($paths['execution_manifest']);
    $executionReportPath = base_path($paths['execution_report']);
    $statePath = base_path($config['execution_state']);
    foreach ([$rawPath, $scoredPath, $scoreReportPath, $manifestPath, $executionReportPath, $statePath] as $path) {
        expect(file_exists($path))->toBeFalse('Execution artifact already exists; refusing V11 rerun: '.$path);
    }

    $dataset = json_decode((string) file_get_contents($directory.'/v11-final-audited-dataset.json'), true, 512, JSON_THROW_ON_ERROR);
    $contract = FinalV11DatasetContract::parse($dataset);
    $evaluationContract = json_decode((string) file_get_contents($directory.'/v11-evaluation-contract.json'), true, 512, JSON_THROW_ON_ERROR);
    $factContract = json_decode((string) file_get_contents($directory.'/v11-fact-verification-contract.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($contract['counts'])->toMatchArray([
        'primary' => 160,
        'multi_turn_scenarios' => 20,
        'scenario_turns' => 40,
        'multi_intent_branches' => 40,
        'entity_objects' => 240,
    ]);

    config()->set('services.ai_chat.enabled', true);
    config()->set('services.ai_chat.semantic_router_enabled', false);
    config()->set('services.ai_chat.vector_search_enabled', false);
    config()->set('services.ai_chat.qdrant_inference_enabled', false);
    config()->set('services.ai_chat.knowledge_generation_enabled', false);
    config()->set('services.ai_chat.query_expansion_enabled', false);
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->withExceptionHandling();
    Http::preventStrayRequests();

    $productAuthority = json_decode((string) file_get_contents($directory.'/v11-sanitized-product-authority.json'), true, 128, JSON_THROW_ON_ERROR);
    foreach ($productAuthority['categories'] as $definition) {
        Category::query()->forceCreate([
            'id' => $definition['id'],
            'name' => $definition['name'],
            'is_active' => $definition['active'],
        ]);
    }
    foreach ($productAuthority['products'] as $definition) {
        Product::query()->forceCreate([
            'id' => $definition['id'],
            'name' => $definition['name'],
            'slug' => Str::slug($definition['name']).'-v11-final',
            'img' => '/images/'.Str::slug($definition['name']).'.png',
            'price' => $definition['current_unit_price_vnd'],
            'inventory' => $definition['current_stock'],
            'is_active' => $definition['active'],
            'description' => $definition['name'].' is a frozen local V11 authority product.',
            'sort_description' => $definition['name'].' is a frozen local V11 authority product.',
            'facebook' => '',
            'twitter' => '',
            'instagram' => '',
            'linkedin' => '',
            'category_id' => $definition['category']['id'],
        ]);
    }
    $siteAuthority = json_decode((string) file_get_contents($directory.'/v11-sanitized-sitesetting-authority.json'), true, 64, JSON_THROW_ON_ERROR);
    SiteSetting::current()->update([
        'brand_name' => $siteAuthority['brand_name'],
        'contact_email' => $siteAuthority['public_contact']['email'],
        'contact_phone' => $siteAuthority['public_contact']['contact_phone'],
        'support_phone' => $siteAuthority['public_contact']['support_phone'],
        'address_vi' => $siteAuthority['public_contact']['address_vi'],
        'address_en' => $siteAuthority['public_contact']['address_en'],
        'shipping_fee' => $siteAuthority['shipping']['shipping_fee_vnd'],
        'free_shipping_threshold' => $siteAuthority['shipping']['free_shipping_threshold_vnd'],
    ]);
    app(ChatKnowledgeSyncService::class)->sync(resource_path('chat/knowledge'));

    $verified = User::factory()->customer()->create(['email' => 'v11-verified@example.test']);
    $owner = User::factory()->customer()->create(['email' => 'v11-owner@example.test']);
    $other = User::factory()->customer()->create(['email' => 'v11-other@example.test']);
    $symbolDefinitions = [];
    foreach ($dataset['primary_cases'] as $case) {
        $actor = (string) $case['preconditions']['actor'];
        $symbol = $case['preconditions']['symbolic_order_reference'] ?? null;
        if (is_string($symbol)) {
            $symbolDefinitions[$symbol] = $actor;
        }
        foreach ($case['multi_intent_branches'] as $branch) {
            $symbol = $branch['preconditions']['symbolic_order_reference'] ?? null;
            if (is_string($symbol)) {
                $symbolDefinitions[$symbol] = $actor;
            }
        }
    }
    $symbolToId = ['ORD-UNKNOWN-404' => 999999];
    foreach ($symbolDefinitions as $symbol => $actor) {
        $orderOwner = $actor === 'authenticated_non_owner' ? $other : $owner;
        $order = Order::create([
            'user_id' => $orderOwner->id,
            'fullname' => $orderOwner->name,
            'address' => 'V11 isolated local address',
            'phone' => '0900000000',
            'email' => $orderOwner->email,
            'status' => Order::STATUS_PROCESSING,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'subtotal' => 100000,
            'shipping_fee' => 20000,
            'grand_total' => 120000,
        ]);
        $symbolToId[$symbol] = (int) $order->id;
    }
    Order::create([
        'user_id' => $owner->id,
        'fullname' => $owner->name,
        'address' => 'V11 latest owned order address',
        'phone' => '0900000000',
        'email' => $owner->email,
        'status' => Order::STATUS_CONFIRMED,
        'payment_method' => Order::PAYMENT_METHOD_SEPAY,
        'payment_status' => Order::PAYMENT_STATUS_PAID,
        'subtotal' => 200000,
        'shipping_fee' => 0,
        'grand_total' => 200000,
        'created_at' => now()->addMinute(),
        'updated_at' => now()->addMinute(),
    ]);
    $reverseOrderReferences = collect($symbolToId)->mapWithKeys(
        fn (int $id, string $symbol): array => [(string) $id => $symbol]
    )->all();

    $runId = 'final-v11-'.now('UTC')->format('Ymd\THis\Z');
    $startedAt = now('UTC')->toIso8601String();
    FinalV11ExecutionGuard::acquire($statePath, $runId, FinalV11ExecutionHarness::identities(), $startedAt);
    FinalV11ExecutionGuard::markExecuting($statePath);
    FinalV11RawCapture::initialize($rawPath, [
        'run_id' => $runId,
        'started_at_utc' => $startedAt,
        'execution_target' => FinalV11ExecutionHarness::EXECUTION_TARGET,
        'candidate_hash' => FinalV11ExecutionHarness::CANDIDATE_SHA256,
        'dataset_hash' => FinalV11ExecutionHarness::DATASET_SHA256,
        'toolchain_hash' => FinalV11ExecutionHarness::TOOLCHAIN_SHA256,
        'evaluator_hash' => FinalV11ExecutionHarness::EVALUATOR_SHA256,
        'scorer_hash' => FinalV11ExecutionHarness::SCORER_SHA256,
        'runtime_adapter_hash' => FinalV11ExecutionHarness::ADAPTER_SHA256,
        'runtime_adapter_version' => FinalV11RuntimeAdapterV2::VERSION,
        'manual_retries' => 0,
    ]);

    $reset = function (): void {
        Cache::flush();
        $this->flushSession();
        $this->app['session']->invalidate();
        app('auth')->forgetGuards();
        config()->set('auth.defaults.guard', 'web');
    };
    $authenticate = function (string $actor) use ($verified, $owner): void {
        $user = match ($actor) {
            'authenticated_verified_customer' => $verified,
            'authenticated_owner', 'authenticated_non_owner' => $owner,
            default => null,
        };
        if ($user) {
            Sanctum::actingAs($user);
        }
    };
    $headers = fn (string $language): array => [
        'Origin' => 'http://127.0.0.1:5173',
        'Referer' => 'http://127.0.0.1:5173/',
        'Accept-Language' => $language === 'en' ? 'en' : 'vi',
    ];
    $router = app(ChatIntentRouter::class);
    $contextResolver = app(ChatContextResolver::class);
    $capabilityGuard = app(ChatCapabilityGuard::class);
    $canonicalizer = app(ChatEntityCanonicalizer::class);
    $entityExtractor = app(ChatEntityExtractor::class);
    $captureRoute = function (string $message, ?array $context) use (
        $router, $contextResolver, $capabilityGuard, $canonicalizer, $entityExtractor, $reverseOrderReferences
    ): array {
        $base = $router->route($message);
        $resolved = $contextResolver->resolve($entityExtractor->normalize($message), $base, $context);
        $resolved = $canonicalizer->resolve($capabilityGuard->enforceResolvedMutationTarget($resolved));
        $followUp = finalV11FollowUpState($base, $resolved, $context);

        return [finalV11RestoreOrderReferences($resolved, $reverseOrderReferences), $followUp];
    };

    try {
        foreach ($dataset['primary_cases'] as $case) {
            $input = FinalV11ExecutionHarness::primaryInput($case);
            $recordId = $input['case_id'];
            FinalV11ExecutionGuard::recordStarted($statePath, $recordId);
            $reset();
            $authenticate($input['actor']);
            $message = finalV11Render($input['candidate_request']['message'], $symbolToId);
            [$route, $followUp] = $captureRoute($message, null);
            $before = finalV11StateHash();
            $started = hrtime(true);
            $runtimeError = null;
            try {
                $http = $this->withHeaders($headers($input['language_bucket']))->postJson('/api/chat', ['message' => $message]);
                $status = $http->status();
                $response = is_array($http->json()) ? $http->json() : [];
            } catch (Throwable $exception) {
                $runtimeError = $exception::class.': '.$exception->getMessage();
                $status = 0;
                $response = [];
            }
            $latency = round((hrtime(true) - $started) / 1_000_000, 3);
            $after = finalV11StateHash();
            $observed = FinalV11ExecutionHarness::observe(
                $route, $response, $input['actor'], $before, $after, $followUp
            );
            $branchPredictions = $observed['adapter_observation']['multi_intent_branches'];
            $branchIds = count($branchPredictions) === count($input['branch_ids'])
                ? $input['branch_ids']
                : array_map(fn (int $index): string => 'observed-branch-'.($index + 1), array_keys($branchPredictions));
            $branches = [];
            foreach ($branchPredictions as $index => $branchObservation) {
                $branches[] = [
                    'branch_id' => $branchIds[$index],
                    'prediction' => FinalV11RuntimeAdapterV2::prediction($branchObservation),
                    'adapter_observation' => $branchObservation,
                    'runtime_observation' => $observed['runtime']['branch_observations'][$index],
                    'state_changed' => ! hash_equals($before, $after),
                ];
            }
            FinalV11RawCapture::appendPrimary($rawPath, [
                'case_id' => $recordId,
                'input' => $input,
                'executed_candidate_request' => ['message' => $message],
                'runtime_observation' => $observed['runtime'],
                'adapter_observation' => $observed['adapter_observation'],
                'prediction' => $observed['prediction'],
                'branches' => $branches,
                'final_response' => $response,
                'http_status' => $status,
                'runtime_error' => $runtimeError,
                'latency_ms' => ['total_request' => $latency],
                'state_changed' => ! hash_equals($before, $after),
            ]);
            FinalV11ExecutionGuard::recordCompleted($statePath, $recordId);
        }

        foreach ($dataset['multi_turn_scenarios'] as $scenario) {
            $reset();
            $actor = (string) $scenario['preconditions']['actor'];
            $authenticate($actor);
            $context = null;
            foreach ($scenario['turns'] as $index => $turn) {
                $input = FinalV11ExecutionHarness::scenarioTurnInput($scenario, $turn);
                $recordId = $input['scenario_id'].'/turn-'.$input['turn_id'];
                FinalV11ExecutionGuard::recordStarted($statePath, $recordId);
                if ($index > 0 && $input['context_ttl_state'] === 'expired') {
                    Cache::flush();
                    $context = null;
                }
                $message = finalV11Render($input['candidate_request']['message'], $symbolToId);
                [$route, $followUp] = $captureRoute($message, $context);
                $before = finalV11StateHash();
                $started = hrtime(true);
                $runtimeError = null;
                try {
                    $request = $this->withHeaders($headers($input['language_bucket']));
                    if ($index > 0) {
                        $request = $request->withCookie(config('session.cookie'), session()->getId());
                    }
                    $http = $request->postJson('/api/chat', ['message' => $message]);
                    $status = $http->status();
                    $response = is_array($http->json()) ? $http->json() : [];
                } catch (Throwable $exception) {
                    $runtimeError = $exception::class.': '.$exception->getMessage();
                    $status = 0;
                    $response = [];
                }
                $latency = round((hrtime(true) - $started) / 1_000_000, 3);
                $after = finalV11StateHash();
                $observed = FinalV11ExecutionHarness::observe(
                    $route, $response, $actor, $before, $after, $followUp
                );
                FinalV11RawCapture::appendScenarioTurn($rawPath, [
                    'scenario_id' => $input['scenario_id'],
                    'turn' => $input['turn_id'],
                    'input' => $input,
                    'executed_candidate_request' => ['message' => $message],
                    'runtime_observation' => $observed['runtime'],
                    'adapter_observation' => $observed['adapter_observation'],
                    'prediction' => $observed['prediction'],
                    'final_response' => $response,
                    'http_status' => $status,
                    'runtime_error' => $runtimeError,
                    'latency_ms' => ['total_request' => $latency],
                    'state_changed' => ! hash_equals($before, $after),
                ]);
                $context = finalV11NextContext($route, $response);
                FinalV11ExecutionGuard::recordCompleted($statePath, $recordId);
            }
        }

        $rawHash = FinalV11ExecutionHarness::freezeArtifact($rawPath);
        $capture = FinalV11RawCapture::read($rawPath);
        $scored = FinalV11OfflineScorerV2::score($contract, $capture, $factContract);
        $metrics = FinalV11Metrics::calculate($scored);
        $scoredHash = FinalV11ExecutionHarness::writeJsonExclusive($scoredPath, [
            'schema_version' => 'farta-v11-final-scored-results.1.0',
            'raw_capture' => ['path' => $paths['raw_capture'], 'sha256' => $rawHash],
            'scored' => $scored,
            'metrics' => $metrics,
        ]);
        $goNoGo = 'NO-GO';
        $goNoGoReason = 'The frozen evaluation contract defines metrics but no positive release thresholds; GO is not invented.';
        $report = "# V11 Final Execution Report\n\n"
            .'- Candidate: `'.FinalV11ExecutionHarness::CANDIDATE_SHA256."`\n"
            .'- Dataset: `'.FinalV11ExecutionHarness::DATASET_SHA256."`\n"
            .'- Toolchain V2: `'.FinalV11ExecutionHarness::TOOLCHAIN_SHA256."`\n"
            ."- V11 requests: `1` complete dataset execution\n"
            ."- V11 executed: `true`\n"
            ."- Raw capture: `{$paths['raw_capture']}` (`{$rawHash}`)\n"
            ."- Scored results: `{$paths['scored_results']}` (`{$scoredHash}`)\n"
            ."- Unsafe execution: `{$metrics['unsafe_execution_count']}`\n"
            ."- Wrong-entity unsafe action: `{$metrics['wrong_entity_unsafe_action_count']}`\n"
            ."- GO/NO-GO: `{$goNoGo}`\n"
            ."- Decision basis: {$goNoGoReason}\n\n"
            ."## Metrics\n\n```json\n".FinalV11EvaluationV2::canonicalJson($metrics)."```\n\n"
            ."## Failure breakdown\n\nFailures are preserved in the scored-results artifact by record and score dimension; no result was edited or rerun.\n\n"
            ."## Immutability\n\nCandidate, V11 dataset/audit/manifest, Toolchain V2, Evaluator V2, and Scorer V2 were reverified unchanged after capture.\n";
        $scoreReportHash = FinalV11ExecutionHarness::writeTextExclusive($scoreReportPath, $report);
        $executionReportHash = FinalV11ExecutionHarness::writeTextExclusive($executionReportPath, $report);
        $executionManifest = [
            'schema_version' => 'farta-v11-final-execution-manifest.1.0',
            'run_id' => $runId,
            'execution_target' => FinalV11ExecutionHarness::EXECUTION_TARGET,
            'execution_count' => 1,
            'v11_executed' => true,
            'identities' => FinalV11ExecutionHarness::identities(),
            'artifacts' => [
                'raw_capture' => ['path' => $paths['raw_capture'], 'sha256' => $rawHash],
                'scored_results' => ['path' => $paths['scored_results'], 'sha256' => $scoredHash],
                'score_report' => ['path' => $paths['score_report'], 'sha256' => $scoreReportHash],
                'execution_report' => ['path' => $paths['execution_report'], 'sha256' => $executionReportHash],
            ],
            'counts' => $metrics['counts'],
            'metrics' => $metrics,
            'go_no_go' => $goNoGo,
            'go_no_go_reason' => $goNoGoReason,
            'commit' => false,
            'push' => false,
            'deploy' => false,
        ];
        FinalV11ExecutionHarness::writeJsonExclusive($manifestPath, $executionManifest);
        FinalV11ExecutionGuard::complete(
            $statePath,
            ['path' => $paths['raw_capture'], 'sha256' => $rawHash],
            ['path' => $paths['scored_results'], 'sha256' => $scoredHash],
            now('UTC')->toIso8601String()
        );

        expect(ChatFixtureAudit::runtimeHash())->toBe(FinalV11ExecutionHarness::CANDIDATE_SHA256)
            ->and(hash_file('sha256', $directory.'/v11-final-audited-dataset.json'))->toBe(FinalV11ExecutionHarness::DATASET_SHA256)
            ->and(hash_file('sha256', $directory.'/v11-r3-fresh-audit-report.md'))->toBe(FinalV11ExecutionHarness::AUDIT_SHA256)
            ->and(hash_file('sha256', $directory.'/v11-final-manifest.json'))->toBe(FinalV11ExecutionHarness::MANIFEST_SHA256)
            ->and($metrics['counts'])->toMatchArray([
                'primary' => 160,
                'multi_turn_scenarios' => 20,
                'scenario_turns' => 40,
                'multi_intent_branches' => 40,
                'entity_objects' => 240,
            ]);
    } catch (Throwable $exception) {
        if (is_file($rawPath) && is_writable($rawPath)) {
            FinalV11ExecutionHarness::freezeArtifact($rawPath);
        }
        $state = FinalV11ExecutionGuard::read($statePath);
        if (($state['execution_state'] ?? null) === 'EXECUTING') {
            FinalV11ExecutionGuard::recordFailed(
                $statePath,
                (string) ($state['last_case'] ?? 'pre-capture'),
                $exception::class.': '.$exception->getMessage()
            );
        }

        throw $exception;
    }
})->group('v11-final-execution');
