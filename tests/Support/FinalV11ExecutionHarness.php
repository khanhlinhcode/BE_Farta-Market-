<?php

namespace Tests\Support;

use App\Enums\ChatIntent;
use App\Services\Chat\ChatRouteFrame;

final class FinalV11ExecutionHarness
{
    public const VERSION = 'farta-v11-final-execution-harness.1.0.0';

    public const EXECUTION_TARGET = 'LOCAL_CANDIDATE';

    public const CANDIDATE_SHA256 = 'af36f24e2cebe333137ecf607000ff54dbefec699c7a50bf0b32782b84a08ce9';

    public const DATASET_SHA256 = '94bc6d3930057f7584c80baac66df1f05f98133b94fb733229fe22b49768f3cb';

    public const AUDIT_SHA256 = 'c97a9d4c1e6621cb9c18fc692887b9302de302cc73372f8469e6ca5b22bd70ea';

    public const MANIFEST_SHA256 = 'f2ccc7420918cd12957877e305e7a99eecf08b274cd0b3fa4ef63dbba9e35f4e';

    public const TOOLCHAIN_SHA256 = '9fcf7f1de8f42156074a65ac71255098c1945144bcbb940cfc222a02ecbdbd9b';

    public const EVALUATOR_SHA256 = 'df40ceb8336814629eeb5ae93a0dce5e623a2c3d8e2f964f24e5789f3a1ba2de';

    public const SCORER_SHA256 = '6dd5ebece571b35eb89b4c9d244300e64b9a2c9491294f1b13277f6d6a406f09';

    public const ADAPTER_SHA256 = '4567cd4db35fbd34bac3f82a536b24bb20032227aa8dc6808964fd2399ec899e';

    public const EVALUATION_CONTRACT_SHA256 = 'f51bc21f66d31c2b03cf94a2ae602896d4a251ad053ba72760c707f6bd8dfa20';

    public const FACT_CONTRACT_SHA256 = '8ee38762fe7d51392bb3ec644a44913077722ef108a38102df648fc8c1a1c604';

    public const ADAPTER_CONTRACT_SHA256 = '53b1ae811888f62d09a379c6e8905f2604528ac6c1bb9e22ffd7b1420aaa20cb';

    private const FORBIDDEN_CANDIDATE_INPUT_KEYS = [
        'expected_intent', 'expected_entity', 'expected_handler', 'expected_business_outcome',
        'expected_claim', 'gold_label', 'gold_branch', 'gold_intent', 'entity_gold',
        'grounding_gold', 'minimum_facts_required',
    ];

    private const NONDETERMINISTIC_KEYS = [
        'started_at_utc', 'completed_at_utc', 'recorded_at_utc', 'latency_ms',
    ];

    /** @return array<string, string> */
    public static function identities(): array
    {
        return [
            'candidate' => self::CANDIDATE_SHA256,
            'dataset' => self::DATASET_SHA256,
            'audit' => self::AUDIT_SHA256,
            'manifest' => self::MANIFEST_SHA256,
            'toolchain_v2' => self::TOOLCHAIN_SHA256,
            'evaluator_v2' => self::EVALUATOR_SHA256,
            'scorer_v2' => self::SCORER_SHA256,
            'runtime_adapter_v2' => self::ADAPTER_SHA256,
            'evaluation_contract' => self::EVALUATION_CONTRACT_SHA256,
            'fact_verification_contract' => self::FACT_CONTRACT_SHA256,
            'runtime_adapter_contract' => self::ADAPTER_CONTRACT_SHA256,
        ];
    }

    /** @return array<string, mixed> */
    public static function primaryInput(array $case): array
    {
        $input = [
            'record_type' => 'primary',
            'case_id' => self::requiredString($case['case_id'] ?? null, 'case_id'),
            'session_id' => 'primary:'.self::requiredString($case['case_id'] ?? null, 'case_id'),
            'language_bucket' => self::requiredString($case['language_bucket'] ?? null, 'language_bucket'),
            'actor' => self::requiredString($case['preconditions']['actor'] ?? null, 'preconditions.actor'),
            'symbolic_order_reference' => is_string($case['preconditions']['symbolic_order_reference'] ?? null)
                ? $case['preconditions']['symbolic_order_reference'] : null,
            'candidate_request' => [
                'message' => self::requiredString($case['utterance'] ?? null, 'utterance'),
            ],
            'branch_ids' => array_values(array_map(
                fn (array $branch): string => self::requiredString($branch['branch_id'] ?? null, 'branch_id'),
                $case['multi_intent_branches'] ?? []
            )),
        ];
        self::assertGoldIsolation($input['candidate_request']);

        return $input;
    }

    /** @return array<string, mixed> */
    public static function scenarioTurnInput(array $scenario, array $turn): array
    {
        $scenarioId = self::requiredString($scenario['scenario_id'] ?? null, 'scenario_id');
        $turnId = $turn['turn'] ?? null;
        if (! is_int($turnId) || $turnId < 1) {
            throw new FinalV11ContractException('turn must be a positive integer.');
        }
        $input = [
            'record_type' => 'scenario_turn',
            'scenario_id' => $scenarioId,
            'turn_id' => $turnId,
            'session_id' => 'scenario:'.$scenarioId,
            'language_bucket' => self::requiredString($scenario['language_bucket'] ?? null, 'language_bucket'),
            'actor' => self::requiredString($scenario['preconditions']['actor'] ?? null, 'preconditions.actor'),
            'context_ttl_state' => ($scenario['preconditions']['context_ttl_state'] ?? 'active') === 'expired'
                ? 'expired' : 'active',
            'candidate_request' => [
                'message' => self::requiredString($turn['utterance'] ?? null, 'utterance'),
            ],
        ];
        self::assertGoldIsolation($input['candidate_request']);

        return $input;
    }

    /** @param array<string, mixed> $candidateInput */
    public static function assertGoldIsolation(array $candidateInput): void
    {
        $walk = function (mixed $value) use (&$walk): void {
            if (! is_array($value)) {
                return;
            }
            foreach ($value as $key => $item) {
                if (is_string($key) && in_array(strtolower($key), self::FORBIDDEN_CANDIDATE_INPUT_KEYS, true)) {
                    throw new FinalV11ContractException('Gold-derived candidate input is forbidden: '.$key.'.');
                }
                $walk($item);
            }
        };
        $walk($candidateInput);
        if (array_keys($candidateInput) !== ['message'] || ! is_string($candidateInput['message'])) {
            throw new FinalV11ContractException('Candidate request must contain only the user message.');
        }
    }

    /**
     * Build the exact Toolchain V2 runtime envelope from typed candidate state
     * and structured response fields. It never reads a V11 record or gold.
     *
     * @param  array<string, mixed>  $response
     * @param  array{resolved_by_context: bool, mutation_target: ?string, status: string}  $followUpState
     * @return array<string, mixed>
     */
    public static function observe(
        ChatRouteFrame|array $route,
        array $response,
        string $actor,
        string $businessStateBefore,
        string $businessStateAfter,
        array $followUpState = [
            'resolved_by_context' => false,
            'mutation_target' => null,
            'status' => 'not_applicable',
        ],
        ?bool $wrongEntityUnsafeAction = null
    ): array {
        $frame = self::frame($route);
        if ($wrongEntityUnsafeAction === null) {
            if (! hash_equals($businessStateBefore, $businessStateAfter)) {
                throw new FinalV11ContractException('MISSING OBSERVABILITY: changed state requires explicit wrong-entity adjudication.');
            }
            $wrongEntityUnsafeAction = false;
        }

        $branchObservations = [];
        $branches = $frame['subrequests'] ?? [];
        foreach ($branches as $index => $branch) {
            $branchResponse = self::branchResponse($branch, $response, $index);
            $branchObservations[] = self::runtimeEnvelope(
                $branch,
                $branchResponse,
                $actor,
                $businessStateBefore,
                $businessStateAfter,
                ['resolved_by_context' => false, 'mutation_target' => null, 'status' => 'not_applicable'],
                $wrongEntityUnsafeAction,
                true
            );
        }

        $runtime = self::runtimeEnvelope(
            $route,
            $response,
            $actor,
            $businessStateBefore,
            $businessStateAfter,
            $followUpState,
            $wrongEntityUnsafeAction,
            false,
            $branchObservations
        );
        $observation = FinalV11RuntimeAdapterV2::normalize($route, $runtime);
        FinalV11OfflineScorerV2::verifyCanonicalEvidence($observation);

        return [
            'runtime' => $runtime,
            'adapter_observation' => $observation,
            'prediction' => FinalV11RuntimeAdapterV2::prediction($observation),
        ];
    }

    /** @param array<string, mixed> $value */
    public static function deterministicHash(array $value): string
    {
        $strip = function (mixed $item) use (&$strip): mixed {
            if (! is_array($item)) {
                return $item;
            }
            $result = [];
            foreach ($item as $key => $child) {
                if (is_string($key) && in_array($key, self::NONDETERMINISTIC_KEYS, true)) {
                    continue;
                }
                $result[$key] = $strip($child);
            }

            return $result;
        };

        return hash('sha256', FinalV11EvaluationV2::canonicalJson($strip($value)));
    }

    public static function freezeArtifact(string $path): string
    {
        if (! is_file($path)) {
            throw new FinalV11ContractException('Artifact does not exist: '.$path);
        }
        $hash = hash_file('sha256', $path);
        if (! is_string($hash) || ! chmod($path, 0444)) {
            throw new FinalV11ContractException('Unable to freeze artifact: '.$path);
        }

        return $hash;
    }

    /** @param array<string, mixed> $value */
    public static function writeJsonExclusive(string $path, array $value): string
    {
        if (file_exists($path)) {
            throw new FinalV11ContractException('Refusing to overwrite execution artifact: '.$path);
        }
        $bytes = FinalV11EvaluationV2::canonicalJson($value);
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            throw new FinalV11ContractException('Unable to create execution artifact: '.$path);
        }
        try {
            if (! flock($handle, LOCK_EX) || fwrite($handle, $bytes) !== strlen($bytes)) {
                throw new FinalV11ContractException('Unable to persist execution artifact: '.$path);
            }
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return self::freezeArtifact($path);
    }

    public static function writeTextExclusive(string $path, string $bytes): string
    {
        if (file_exists($path)) {
            throw new FinalV11ContractException('Refusing to overwrite execution artifact: '.$path);
        }
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            throw new FinalV11ContractException('Unable to create execution artifact: '.$path);
        }
        try {
            if (! flock($handle, LOCK_EX) || fwrite($handle, $bytes) !== strlen($bytes)) {
                throw new FinalV11ContractException('Unable to persist execution artifact: '.$path);
            }
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return self::freezeArtifact($path);
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array{resolved_by_context: bool, mutation_target: ?string, status: string}  $followUpState
     * @param  array<int, array<string, mixed>>  $branchObservations
     * @return array<string, mixed>
     */
    private static function runtimeEnvelope(
        ChatRouteFrame|array $route,
        array $response,
        string $actor,
        string $businessStateBefore,
        string $businessStateAfter,
        array $followUpState,
        bool $wrongEntityUnsafeAction,
        bool $branch,
        array $branchObservations = []
    ): array {
        return [
            'terminal' => self::terminal($route, $response, $actor),
            'authorization_result' => self::authorization($route, $response, $actor, $branch),
            'evidence' => FinalV11RuntimeEvidenceHarnessV2::capture($route, $response),
            'follow_up_state' => $followUpState,
            'business_state_before' => self::stateHash($businessStateBefore),
            'business_state_after' => self::stateHash($businessStateAfter),
            'wrong_entity_unsafe_action' => $wrongEntityUnsafeAction,
            'branch_observations' => $branchObservations,
            'response' => $response,
        ];
    }

    /** @param array<string, mixed> $response */
    private static function terminal(ChatRouteFrame|array $route, array $response, string $actor): string
    {
        $frame = self::frame($route);
        $intent = $frame['intent'];
        $code = (string) ($response['code'] ?? '');
        $status = (string) ($response['answer_status'] ?? $response['status'] ?? '');
        if (($frame['decision_state'] ?? null) === 'denied_action' || $status === 'denied' || $code === 'ACTION_NOT_ALLOWED') {
            return 'DENIED';
        }
        if ($status === 'unsupported' || $code === 'UNSUPPORTED_REQUEST') {
            return 'UNSUPPORTED';
        }
        if ($status === 'refused_unverified' || in_array($code, ['NO_EVIDENCE', 'VERIFICATION_FAILED'], true)) {
            return 'NO_EVIDENCE';
        }
        if ($status === 'clarification' || str_contains($code, 'CLARIFICATION_REQUIRED') || $intent === 'clarification') {
            return 'CLARIFICATION_REQUIRED';
        }
        if ($status === 'auth_required' || in_array($code, ['AUTH_REQUIRED', 'AUTH_REQUIRED_FOR_CART'], true)) {
            return 'AUTH_REQUIRED';
        }
        if ($intent === 'multi_intent' && ($frame['composition'] ?? null) !== 'shipping_eligibility') {
            $terminals = [];
            foreach ($frame['subrequests'] ?? [] as $index => $subrequest) {
                $terminals[] = self::terminal($subrequest, self::branchResponse($subrequest, $response, $index), $actor);
            }
            $mixed = collect($terminals)->contains(fn (string $terminal): bool => in_array($terminal, [
                'AUTH_REQUIRED', 'CLARIFICATION_REQUIRED', 'DENIED', 'NO_EVIDENCE', 'UNAVAILABLE', 'UNSUPPORTED',
            ], true));

            return $mixed ? 'PARTIAL_MIXED_TERMINALS' : 'ALL_BRANCHES_HANDLED';
        }
        if (($response['suggested_actions'] ?? []) !== []) {
            return 'SUGGESTED_ACTION';
        }
        if ($code === 'UNAVAILABLE' || ($intent === 'cart_action_request' && ($response['products'] ?? []) === [])) {
            return 'UNAVAILABLE';
        }
        if ($intent === 'product_search' && ($response['products'] ?? []) === []) {
            return 'NO_RESULTS';
        }
        if (in_array($intent, ['order_query', 'order_read', 'payment_status_read'], true)
            && ! isset($response['order'])) {
            return $actor === 'authenticated_non_owner' ? 'DENIED' : 'ANSWER_OR_NOT_FOUND';
        }

        return 'ANSWER';
    }

    /** @param array<string, mixed> $response */
    private static function authorization(ChatRouteFrame|array $route, array $response, string $actor, bool $branch): string
    {
        $frame = self::frame($route);
        $intent = $frame['intent'];
        if ($intent === 'multi_intent') {
            $decisions = array_map(
                fn (mixed $item): string => self::authorization($item, [], $actor, true),
                $frame['subrequests'] ?? []
            );
            $hasDenied = in_array('DENY', $decisions, true);
            $hasSafe = count(array_diff($decisions, ['DENY'])) > 0;

            return $hasDenied && $hasSafe ? 'ALLOW_SAFE_BRANCHES_DENY_PROHIBITED_BRANCHES' : 'PER_BRANCH';
        }
        if (($frame['decision_state'] ?? null) === 'denied_action' || ($response['code'] ?? null) === 'ACTION_NOT_ALLOWED') {
            return 'DENY';
        }
        if ($intent === 'cart_action_request') {
            return 'SUGGEST_ONLY';
        }
        if (in_array($intent, ['order_query', 'order_read', 'payment_status_read'], true)) {
            $decision = match (true) {
                in_array($response['code'] ?? null, ['AUTH_REQUIRED', 'AUTH_REQUIRED_FOR_CART'], true), $actor === 'anonymous' => 'REQUIRE_AUTH',
                ($response['code'] ?? null) === 'ACTION_NOT_ALLOWED', $actor === 'authenticated_non_owner' => 'DENY_CROSS_ACCOUNT',
                default => 'ALLOW_OWNED_READ',
            };

            return $branch ? (in_array($decision, ['REQUIRE_AUTH', 'DENY_CROSS_ACCOUNT'], true) ? 'DENY' : 'ALLOW') : $decision;
        }
        if (in_array($intent, ['unsupported', 'unsupported_ood', 'clarification', 'general_chat'], true)) {
            return 'NOT_APPLICABLE';
        }

        return $branch ? 'ALLOW' : 'ALLOW_READ';
    }

    /** @param array<string, mixed> $response @return array<string, mixed> */
    private static function branchResponse(ChatRouteFrame|array $branch, array $response, int $index): array
    {
        $frame = self::frame($branch);
        $subresponse = is_array($response['subresponses'][$index] ?? null)
            ? $response['subresponses'][$index] : [];
        $productIds = array_values(array_filter(
            $frame['entities']['canonical_product_ids'] ?? [],
            fn (mixed $id): bool => is_int($id)
        ));
        if ($productIds === [] && is_int($frame['entities']['canonical_product_id'] ?? null)) {
            $productIds = [$frame['entities']['canonical_product_id']];
        }
        $products = array_values(array_filter(
            $response['products'] ?? [],
            fn (mixed $product): bool => is_array($product)
                && ($productIds === [] || in_array($product['id'] ?? null, $productIds, true))
        ));

        return [
            ...$response,
            ...$subresponse,
            'products' => $products,
            'answer_status' => $subresponse['status'] ?? $response['answer_status'] ?? null,
            'citations' => ($subresponse['source'] ?? null) === 'knowledge' ? ($response['citations'] ?? []) : [],
        ];
    }

    /** @return array<string, mixed> */
    private static function frame(ChatRouteFrame|array $route): array
    {
        if ($route instanceof ChatRouteFrame) {
            return [
                ...$route->toArray(),
                'intent' => $route->intent->value,
                'subrequests' => $route->branches,
            ];
        }
        if (array_is_list($route)) {
            throw new FinalV11ContractException('Observed route must be an object.');
        }
        if (($route['intent'] ?? null) instanceof ChatIntent) {
            $route['intent'] = $route['intent']->value;
        }

        return $route;
    }

    private static function stateHash(string $value): string
    {
        return preg_match('/^[a-f0-9]{64}$/', $value) === 1 ? $value : hash('sha256', $value);
    }

    private static function requiredString(mixed $value, string $path): string
    {
        if (! is_string($value) || $value === '') {
            throw new FinalV11ContractException($path.' must be a non-empty string.');
        }

        return $value;
    }
}
