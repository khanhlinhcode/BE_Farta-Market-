<?php

namespace Tests\Support;

final class FinalV11OfflineScorerV2
{
    public const VERSION = 'farta-final-v11-offline-scorer.2.0.0';

    /**
     * Preserve the frozen V1 metric implementation. V2 adds adapter schema and
     * provenance validation before a separately authorized capture is scored.
     *
     * @param  array<string, mixed>  $contract
     * @param  array<string, mixed>  $capture
     * @param  array<string, mixed>  $factContract
     * @return array<string, mixed>
     */
    public static function score(array $contract, array $capture, array $factContract): array
    {
        $isV2Capture = ($capture['metadata']['runtime_adapter_version'] ?? null) === FinalV11RuntimeAdapterV2::VERSION;
        if ($isV2Capture) {
            foreach ($capture['primary_cases'] ?? [] as $record) {
                if (is_array($record['prediction'] ?? null)) {
                    FinalV11EvaluationV2::assertPredictionSchema($record['prediction']);
                }
                foreach ($record['branches'] ?? [] as $branch) {
                    if (is_array($branch['prediction'] ?? null)) {
                        FinalV11EvaluationV2::assertPredictionSchema($branch['prediction']);
                    }
                }
            }
            foreach ($capture['scenario_turns'] ?? [] as $turn) {
                if (is_array($turn['prediction'] ?? null)) {
                    FinalV11EvaluationV2::assertPredictionSchema($turn['prediction']);
                }
            }
        }

        return FinalV11OfflineScorer::score(
            $contract,
            self::reconcileCapture($capture, $factContract),
            $factContract
        );
    }

    /**
     * Reconcile canonical runtime claim IDs to the frozen record-local IDs at
     * the scorer boundary. The adapter remains blind to record identity and
     * gold facts; the scorer uses only the pre-existing required source sets.
     *
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $factContract
     * @return array<string, mixed>
     */
    public static function reconcilePrediction(string $recordId, array $prediction, array $factContract): array
    {
        $records = [...($factContract['record_claim_contracts'] ?? []), ...($factContract['scenario_claim_contracts'] ?? [])];
        $record = collect($records)->first(
            fn (mixed $item): bool => is_array($item) && ($item['record_id'] ?? null) === $recordId
        );
        if (! is_array($record)) {
            return $prediction;
        }
        $expected = $record['claims'] ?? null;
        $provided = $prediction['claim_evidence'] ?? null;
        if (! is_array($expected) || ! array_is_list($expected)
            || ! is_array($provided) || ! array_is_list($provided)) {
            throw new FinalV11ContractException('V2 claim reconciliation requires claim arrays.');
        }
        $expectedById = [];
        foreach ($expected as $claim) {
            if (! is_array($claim) || ! is_string($claim['claim_id'] ?? null)
                || ! is_array($claim['required_source_ids'] ?? null)) {
                throw new FinalV11ContractException('V2 claim reconciliation received an invalid fact contract.');
            }
            $expectedById[$claim['claim_id']] = $claim;
        }
        $used = [];
        $reconciled = [];
        foreach ($provided as $claim) {
            if (! is_array($claim) || ! is_string($claim['claim_id'] ?? null)
                || ! is_array($claim['source_ids'] ?? null)) {
                throw new FinalV11ContractException('V2 claim reconciliation received invalid evidence.');
            }
            $claimId = $claim['claim_id'];
            if (isset($expectedById[$claimId])) {
                $used[$claimId] = true;
                $reconciled[] = $claim;

                continue;
            }
            $replacement = null;
            foreach ($expected as $candidate) {
                $candidateId = $candidate['claim_id'];
                $required = $candidate['required_source_ids'];
                if (! isset($used[$candidateId])
                    && array_diff($required, $claim['source_ids']) === []
                    && array_diff($claim['source_ids'], $required) === []) {
                    $replacement = $candidateId;
                    break;
                }
            }
            if ($replacement === null) {
                $reconciled[] = $claim;

                continue;
            }
            $used[$replacement] = true;
            $reconciled[] = ['claim_id' => $replacement, 'source_ids' => $claim['source_ids']];
        }
        $prediction['claim_evidence'] = $reconciled;

        return $prediction;
    }

    /** @param array<string, mixed> $observation */
    public static function verifyCanonicalEvidence(array $observation): void
    {
        $prediction = FinalV11RuntimeAdapterV2::prediction($observation);
        FinalV11EvaluationV2::assertPredictionSchema($prediction);

        $claims = $observation['claims'] ?? null;
        if (! is_array($claims) || ! array_is_list($claims)) {
            throw new FinalV11ContractException('Normalized V2 claims must be an array.');
        }
        $expected = [];
        foreach ($claims as $claim) {
            if (! is_array($claim) || ! is_string($claim['claim_key'] ?? null)
                || ! is_string($claim['source_id'] ?? null)
                || ! is_string($claim['evidence_ref'] ?? null)
                || $claim['evidence_ref'] === '') {
                throw new FinalV11ContractException('Normalized V2 claim provenance is incomplete.');
            }
            $id = FinalV11RuntimeAdapterV2::claimId($claim['claim_key']);
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
            throw new FinalV11ContractException('Canonical claim evidence does not match runtime provenance.');
        }
    }

    /** @param array<string, mixed> $capture @param array<string, mixed> $factContract @return array<string, mixed> */
    private static function reconcileCapture(array $capture, array $factContract): array
    {
        if (is_array($capture['primary_cases'] ?? null)) {
            foreach ($capture['primary_cases'] as &$record) {
                $caseId = $record['case_id'] ?? null;
                if (! is_string($caseId) || ! is_array($record['prediction'] ?? null)) {
                    continue;
                }
                $record['prediction'] = self::reconcilePrediction($caseId, $record['prediction'], $factContract);
                if (is_array($record['branches'] ?? null)) {
                    foreach ($record['branches'] as &$branch) {
                        $branchId = $branch['branch_id'] ?? null;
                        if (is_string($branchId) && is_array($branch['prediction'] ?? null)) {
                            $branch['prediction'] = self::reconcilePrediction(
                                $caseId.'/'.$branchId,
                                $branch['prediction'],
                                $factContract
                            );
                        }
                    }
                    unset($branch);
                }
            }
            unset($record);
        }
        if (is_array($capture['scenario_turns'] ?? null)) {
            foreach ($capture['scenario_turns'] as &$turn) {
                $scenarioId = $turn['scenario_id'] ?? null;
                if (is_string($scenarioId) && is_array($turn['prediction'] ?? null)) {
                    $turn['prediction'] = self::reconcilePrediction($scenarioId, $turn['prediction'], $factContract);
                }
            }
            unset($turn);
        }

        return $capture;
    }
}
