<?php

namespace Tests\Support;

final class FinalV11OfflineScorerV2R1
{
    public const VERSION = 'farta-final-v11-offline-scorer.2.0.1';

    /**
     * Validate structural prediction scopes, then preserve the frozen V2
     * reconciliation and V1 metric implementation unchanged.
     *
     * @param  array<string, mixed>  $contract
     * @param  array<string, mixed>  $capture
     * @param  array<string, mixed>  $factContract
     * @return array<string, mixed>
     */
    public static function score(array $contract, array $capture, array $factContract): array
    {
        if (($capture['metadata']['runtime_adapter_version'] ?? null) !== FinalV11RuntimeAdapterV2R1::VERSION) {
            throw new FinalV11ContractException('V2-r1 scorer requires a V2-r1 runtime adapter capture.');
        }
        self::assertCapturePredictionSchemas($capture);

        // V2 owns canonical-claim reconciliation and delegates metrics to the
        // frozen V1 core. The distinct adapter version intentionally prevents
        // its obsolete context-free V2 schema precheck from running again.
        return FinalV11OfflineScorerV2::score($contract, $capture, $factContract);
    }

    /** @param array<string, mixed> $capture */
    public static function assertCapturePredictionSchemas(array $capture): void
    {
        foreach ($capture['primary_cases'] ?? [] as $record) {
            if (is_array($record['prediction'] ?? null)) {
                FinalV11EvaluationV2R1::assertPredictionSchema(
                    $record['prediction'],
                    FinalV11EvaluationV2R1::SCOPE_PARENT
                );
            }
            foreach ($record['branches'] ?? [] as $branch) {
                if (is_array($branch['prediction'] ?? null)) {
                    FinalV11EvaluationV2R1::assertPredictionSchema(
                        $branch['prediction'],
                        FinalV11EvaluationV2R1::SCOPE_BRANCH
                    );
                }
            }
        }
        foreach ($capture['scenario_turns'] ?? [] as $turn) {
            if (is_array($turn['prediction'] ?? null)) {
                FinalV11EvaluationV2R1::assertPredictionSchema(
                    $turn['prediction'],
                    FinalV11EvaluationV2R1::SCOPE_PARENT
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $factContract
     * @return array<string, mixed>
     */
    public static function reconcilePrediction(string $recordId, array $prediction, array $factContract): array
    {
        return FinalV11OfflineScorerV2::reconcilePrediction($recordId, $prediction, $factContract);
    }

    /** @param array<string, mixed> $observation */
    public static function verifyCanonicalEvidence(
        array $observation,
        ?string $scope = null
    ): void {
        if (! in_array($scope, [
            FinalV11EvaluationV2R1::SCOPE_PARENT,
            FinalV11EvaluationV2R1::SCOPE_BRANCH,
        ], true)) {
            throw new FinalV11ContractException('V2-r1 evidence verification requires explicit scope.');
        }

        self::verifyOneObservation($observation, $scope);
        foreach ($observation['multi_intent_branches'] ?? [] as $branch) {
            if (! is_array($branch) || array_is_list($branch)) {
                throw new FinalV11ContractException('V2-r1 branch observation must be an object.');
            }
            self::verifyCanonicalEvidence($branch, FinalV11EvaluationV2R1::SCOPE_BRANCH);
        }
    }

    /** @param array<string, mixed> $observation */
    private static function verifyOneObservation(array $observation, string $scope): void
    {
        $prediction = FinalV11RuntimeAdapterV2R1::prediction($observation, $scope);
        $claims = $observation['claims'] ?? null;
        if (! is_array($claims) || ! array_is_list($claims)) {
            throw new FinalV11ContractException('Normalized V2-r1 claims must be an array.');
        }

        $expected = [];
        foreach ($claims as $claim) {
            if (! is_array($claim) || ! is_string($claim['claim_key'] ?? null)
                || ! is_string($claim['source_id'] ?? null)
                || ! is_string($claim['evidence_ref'] ?? null)
                || $claim['evidence_ref'] === '') {
                throw new FinalV11ContractException('Normalized V2-r1 claim provenance is incomplete.');
            }
            $id = FinalV11RuntimeAdapterV2R1::claimId($claim['claim_key']);
            $expected[$id][] = $claim['source_id'];
        }
        ksort($expected, SORT_STRING);

        $actual = [];
        foreach ($prediction['claim_evidence'] as $claim) {
            $actual[$claim['claim_id']] = $claim['source_ids'];
        }
        ksort($actual, SORT_STRING);
        foreach ($expected as &$sources) {
            $sources = array_values(array_unique($sources));
            sort($sources, SORT_STRING);
        }
        unset($sources);

        if ($actual !== $expected) {
            throw new FinalV11ContractException('Canonical V2-r1 claim evidence does not match runtime provenance.');
        }
    }
}
