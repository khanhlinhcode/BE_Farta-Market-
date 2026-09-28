<?php

namespace Tests\Support;

use App\Services\Chat\ChatRouteFrame;

final class FinalV11ExecutionHarnessR4
{
    public const VERSION = 'farta-v11-final-execution-harness.1.0.4';

    /**
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
        $canonicalRoute = FinalV11RuntimeAdapterV2R4::canonicalRoute(
            $route,
            FinalV11EvaluationV2R1::SCOPE_PARENT
        );
        $runtime = FinalV11ExecutionHarnessR1::observe(
            $canonicalRoute,
            $response,
            $actor,
            $businessStateBefore,
            $businessStateAfter,
            $followUpState,
            $wrongEntityUnsafeAction
        )['runtime'];
        $observation = FinalV11RuntimeAdapterV2R4::normalize(
            $route,
            $runtime,
            FinalV11EvaluationV2R1::SCOPE_PARENT
        );
        FinalV11OfflineScorerV2R4::verifyCanonicalEvidence(
            $observation,
            FinalV11EvaluationV2R1::SCOPE_PARENT
        );

        return [
            'runtime' => $runtime,
            'adapter_observation' => $observation,
            'prediction' => FinalV11RuntimeAdapterV2R4::prediction(
                $observation,
                FinalV11EvaluationV2R1::SCOPE_PARENT
            ),
        ];
    }

    /** @param array<string, mixed> $value */
    public static function deterministicHash(array $value): string
    {
        return FinalV11ExecutionHarnessR3::deterministicHash($value);
    }
}
