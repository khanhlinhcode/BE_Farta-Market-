<?php

namespace Tests\Support;

use App\Services\Chat\ChatRouteFrame;

final class FinalV11RuntimeAdapterV2R1
{
    public const VERSION = 'farta-final-v11-runtime-adapter.2.0.1';

    /**
     * Preserve the frozen V2 normalization semantics while applying the new
     * structural security scope to the completed observation tree.
     *
     * @param  array<string, mixed>  $runtime
     * @return array<string, mixed>
     */
    public static function normalize(
        ChatRouteFrame|array $route,
        array $runtime,
        ?string $scope = null
    ): array {
        self::assertScope($scope);

        $compatibilityRuntime = self::compatibilityRuntime($runtime, $scope);
        $observation = FinalV11RuntimeAdapterV2::normalize($route, $compatibilityRuntime);
        $observation = self::restoreSecurityOutcomes($observation, $runtime);
        self::assertObservationTree($observation, $scope);

        return $observation;
    }

    /** @param array<string, mixed> $observation @return array<string, mixed> */
    public static function prediction(array $observation, ?string $scope = null): array
    {
        self::assertScope($scope);

        $prediction = [
            'intent' => $observation['intent'] ?? null,
            'handler' => $observation['handler'] ?? null,
            'business_outcome_category' => $observation['business_outcome_category'] ?? null,
            'terminal' => $observation['terminal'] ?? null,
            'entities' => $observation['entities'] ?? null,
            'required_evidence_domain' => $observation['required_evidence_domain'] ?? null,
            'accepted_source_ids' => $observation['accepted_source_ids'] ?? null,
            'accepted_source_versions' => $observation['accepted_source_versions'] ?? null,
            'claim_evidence' => $observation['claim_evidence'] ?? null,
            'security_decision' => $observation['security_outcome'] ?? null,
            'unsafe_execution' => $observation['unsafe_execution'] ?? null,
            'wrong_entity_unsafe_action' => $observation['wrong_entity_unsafe_action'] ?? null,
        ];
        FinalV11EvaluationV2R1::assertPredictionSchema($prediction, $scope);

        return $prediction;
    }

    public static function claimId(string $claimKey): string
    {
        return FinalV11RuntimeAdapterV2::claimId($claimKey);
    }

    public static function claimKeyFromProvenance(
        string $sourceId,
        string $evidenceRef,
        string $evidenceType
    ): string {
        return FinalV11RuntimeAdapterV2::claimKeyFromProvenance($sourceId, $evidenceRef, $evidenceType);
    }

    /** @param array<string, mixed> $runtime @return array<string, mixed> */
    private static function compatibilityRuntime(array $runtime, string $scope): array
    {
        $compatible = $runtime;
        if ($scope === FinalV11EvaluationV2R1::SCOPE_BRANCH) {
            // V2 performs a context-free parent check during normalization.
            // A closed parent value lets its unchanged non-security rules run;
            // the exact branch value is restored and checked by V2-r1 below.
            $compatible['authorization_result'] = 'DENY';
        }
        if (is_array($compatible['branch_observations'] ?? null)) {
            foreach ($compatible['branch_observations'] as $index => $branch) {
                if (is_array($branch) && ! array_is_list($branch)) {
                    $compatible['branch_observations'][$index] = self::compatibilityRuntime(
                        $branch,
                        FinalV11EvaluationV2R1::SCOPE_BRANCH
                    );
                }
            }
        }

        return $compatible;
    }

    /**
     * @param  array<string, mixed>  $observation
     * @param  array<string, mixed>  $runtime
     * @return array<string, mixed>
     */
    private static function restoreSecurityOutcomes(array $observation, array $runtime): array
    {
        $authorization = $runtime['authorization_result'] ?? null;
        $observation['authorization_result'] = $authorization;
        $observation['security_outcome'] = $authorization;

        $runtimeBranches = $runtime['branch_observations'] ?? [];
        $observationBranches = $observation['multi_intent_branches'] ?? [];
        if (is_array($runtimeBranches) && array_is_list($runtimeBranches)
            && is_array($observationBranches) && array_is_list($observationBranches)) {
            foreach ($observationBranches as $index => $branch) {
                if (is_array($branch) && ! array_is_list($branch)
                    && is_array($runtimeBranches[$index] ?? null)
                    && ! array_is_list($runtimeBranches[$index])) {
                    $observation['multi_intent_branches'][$index] = self::restoreSecurityOutcomes(
                        $branch,
                        $runtimeBranches[$index]
                    );
                }
            }
        }

        return $observation;
    }

    /** @param array<string, mixed> $observation */
    private static function assertObservationTree(array $observation, string $scope): void
    {
        self::prediction($observation, $scope);
        foreach ($observation['multi_intent_branches'] ?? [] as $branch) {
            if (! is_array($branch) || array_is_list($branch)) {
                throw new FinalV11ContractException('V2-r1 branch observation must be an object.');
            }
            self::assertObservationTree($branch, FinalV11EvaluationV2R1::SCOPE_BRANCH);
        }
    }

    private static function assertScope(?string $scope): void
    {
        if (! in_array($scope, [
            FinalV11EvaluationV2R1::SCOPE_PARENT,
            FinalV11EvaluationV2R1::SCOPE_BRANCH,
        ], true)) {
            throw new FinalV11ContractException('V2-r1 adapter requires explicit PARENT or BRANCH scope.');
        }
    }
}
