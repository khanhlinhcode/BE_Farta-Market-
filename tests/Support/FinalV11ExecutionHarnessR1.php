<?php

namespace Tests\Support;

use App\Enums\ChatIntent;
use App\Services\Chat\ChatRouteFrame;

final class FinalV11ExecutionHarnessR1
{
    public const VERSION = 'farta-v11-final-execution-harness.1.0.1';

    private const NONDETERMINISTIC_KEYS = [
        'started_at_utc', 'completed_at_utc', 'recorded_at_utc', 'latency_ms',
    ];

    /**
     * Build and validate a synthetic runtime observation through V2-r1. This
     * class deliberately contains no final V11 execution entry point.
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
        foreach ($frame['subrequests'] ?? [] as $index => $branch) {
            $branchObservations[] = self::runtimeEnvelope(
                $branch,
                self::branchResponse($branch, $response, $index),
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
        $observation = FinalV11RuntimeAdapterV2R1::normalize(
            $route,
            $runtime,
            FinalV11EvaluationV2R1::SCOPE_PARENT
        );
        FinalV11OfflineScorerV2R1::verifyCanonicalEvidence(
            $observation,
            FinalV11EvaluationV2R1::SCOPE_PARENT
        );

        return [
            'runtime' => $runtime,
            'adapter_observation' => $observation,
            'prediction' => FinalV11RuntimeAdapterV2R1::prediction(
                $observation,
                FinalV11EvaluationV2R1::SCOPE_PARENT
            ),
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

        return hash('sha256', FinalV11EvaluationV2R1::canonicalJson($strip($value)));
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
                $terminals[] = self::terminal(
                    $subrequest,
                    self::branchResponse($subrequest, $response, $index),
                    $actor
                );
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
            throw new FinalV11ContractException('Observed V2-r1 route must be an object.');
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
}
