<?php

namespace Tests\Support;

final class FinalV10OfflineScorer
{
    public const SCHEMA_VERSION = 'farta-v10-evaluator-v3-scored-results.1.0';

    /** @param array<string, mixed> $raw */
    public static function assertScoredResults(array $raw): void
    {
        foreach (['primary_cases', 'multi_turn_scenarios'] as $field) {
            if (! is_array($raw[$field] ?? null) || ! array_is_list($raw[$field])) {
                throw new FinalV10ContractException('Scored results '.$field.' must be an array.');
            }
        }
        foreach ($raw['primary_cases'] as $index => $record) {
            if (! is_array($record) || array_is_list($record)) {
                throw new FinalV10ContractException('Scored primary record '.$index.' must be an object.');
            }
            foreach (['gold', 'prediction', 'scores'] as $field) {
                $value = $record[$field] ?? null;
                if (! is_array($value) || ($value !== [] && array_is_list($value))) {
                    throw new FinalV10ContractException('Scored primary record '.$index.' '.$field.' must be an object.');
                }
            }
            if (! is_array($record['branches'] ?? null) || ! array_is_list($record['branches'])) {
                throw new FinalV10ContractException('Scored primary record '.$index.' branches must be an array.');
            }
            $entities = $record['prediction']['entities'] ?? [];
            if (! is_array($entities) || ($entities !== [] && array_is_list($entities))) {
                throw new FinalV10ContractException('Scored primary record '.$index.' prediction entities must be an object.');
            }
            FinalV10Evaluation::assertEntitySchema($entities);
            foreach ($record['branches'] as $branchIndex => $branch) {
                if (! is_array($branch) || array_is_list($branch)
                    || ! is_array($branch['gold'] ?? null)
                    || ! is_array($branch['prediction'] ?? null)
                    || ! is_array($branch['scores'] ?? null)) {
                    throw new FinalV10ContractException('Scored branch '.$index.'/'.$branchIndex.' has an invalid shape.');
                }
            }
        }
        foreach ($raw['multi_turn_scenarios'] as $index => $scenario) {
            if (! is_array($scenario) || array_is_list($scenario)
                || ! is_array($scenario['turns'] ?? null)
                || ! array_is_list($scenario['turns'])) {
                throw new FinalV10ContractException('Scored scenario '.$index.' has an invalid shape.');
            }
            foreach ($scenario['turns'] as $turnIndex => $turn) {
                if (! is_array($turn) || array_is_list($turn)) {
                    throw new FinalV10ContractException('Scored scenario turn '.$index.'/'.$turnIndex.' must be an object.');
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $contract
     * @param  array<string, mixed>  $capture
     * @return array<string, mixed>
     */
    public static function score(array $contract, array $capture): array
    {
        FinalV10RawCapture::validateDocument($capture);
        if (($contract['schema_version'] ?? null) !== FinalV10DatasetContract::SCHEMA_VERSION) {
            throw new FinalV10ContractException('Offline scorer requires a validated Final V10 contract frame.');
        }

        $capturedPrimary = [];
        foreach ($capture['primary_cases'] as $record) {
            $capturedPrimary[$record['case_id']] = $record;
        }
        $primaryResults = [];
        foreach ($contract['primary_cases'] as $index => $goldRecord) {
            $caseId = $goldRecord['case_id'];
            if (! isset($capturedPrimary[$caseId])) {
                throw new FinalV10ContractException('$.primary_cases['.$index.']: missing raw capture for '.$caseId);
            }
            $captured = $capturedPrimary[$caseId];
            $prediction = self::prediction($captured['prediction'] ?? null);
            $prediction['unsafe_execution'] = self::unsafeExecution($captured, $goldRecord['gold']);
            $scores = FinalV10Evaluation::correctness($goldRecord['gold'], $prediction);

            $capturedBranches = is_array($captured['branches'] ?? null) && array_is_list($captured['branches'])
                ? $captured['branches']
                : [];
            $branchResults = [];
            foreach ($goldRecord['branches'] as $branchIndex => $goldBranch) {
                $capturedBranch = $capturedBranches[$branchIndex] ?? null;
                $aligned = is_array($capturedBranch)
                    && ($capturedBranch['branch_id'] ?? null) === $goldBranch['branch_id'];
                $branchPrediction = self::prediction($aligned ? ($capturedBranch['prediction'] ?? null) : null);
                $branchPrediction['unsafe_execution'] = self::unsafeExecution(
                    $aligned ? $capturedBranch : $captured,
                    $goldBranch['gold']
                );
                $branchResult = [
                    'case_id' => $caseId.'/'.$goldBranch['branch_id'],
                    'branch_id' => $goldBranch['branch_id'],
                    'gold' => $goldBranch['gold'],
                    'prediction' => $branchPrediction,
                    'scores' => FinalV10Evaluation::correctness($goldBranch['gold'], $branchPrediction),
                    'runtime_error' => $captured['runtime_error'] ?? null,
                ];
                $branchResult['first_failure_layer'] = FinalV10Evaluation::firstFailureLayer($branchResult);
                $branchResults[] = $branchResult;
            }
            $complete = count($capturedBranches) === count($goldRecord['branches'])
                && collect($branchResults)->every(fn (array $branch): bool => ($branch['prediction']['intent'] ?? null) !== 'MISSING_PREDICTION');
            $whole = $complete && collect($branchResults)->every(
                fn (array $branch): bool => ($branch['scores']['business_outcome'] ?? false) === true
            );
            $result = [
                'case_id' => $caseId,
                'language_bucket' => $goldRecord['language_bucket'],
                'difficulty' => $goldRecord['difficulty'],
                'preconditions' => $goldRecord['preconditions'],
                'gold' => $goldRecord['gold'],
                'prediction' => $prediction,
                'scores' => $scores,
                'branches' => $branchResults,
                'extra_predicted_branch_count' => max(0, count($capturedBranches) - count($goldRecord['branches'])),
                'multi_intent_completeness' => $goldRecord['branches'] === [] ? null : $complete,
                'whole_request_completion' => $goldRecord['branches'] === [] ? null : $whole,
                'http_status' => $captured['http_status'] ?? null,
                'runtime_error' => $captured['runtime_error'] ?? null,
                'latency_ms' => $captured['latency_ms'] ?? [],
                'raw_response' => $captured['raw_response'] ?? null,
            ];
            $result['first_failure_layer'] = FinalV10Evaluation::firstFailureLayer($result);
            if ($result['first_failure_layer'] === null) {
                $result['first_failure_layer'] = collect($branchResults)
                    ->map(fn (array $branch): ?string => $branch['first_failure_layer'])
                    ->filter(fn (mixed $value): bool => is_string($value))
                    ->first();
            }
            $primaryResults[] = $result;
        }
        if (count($capturedPrimary) !== count($contract['primary_cases'])) {
            throw new FinalV10ContractException('Raw capture contains unexpected primary case IDs.');
        }

        $capturedTurns = [];
        foreach ($capture['scenario_turns'] as $turn) {
            $capturedTurns[$turn['scenario_id']][(int) $turn['turn']] = $turn;
        }
        $scenarioResults = [];
        foreach ($contract['multi_turn_scenarios'] as $scenarioIndex => $scenario) {
            $turnResults = [];
            foreach ($scenario['turns'] as $turnIndex => $goldTurn) {
                $captured = $capturedTurns[$scenario['scenario_id']][$goldTurn['turn']] ?? null;
                if (! is_array($captured)) {
                    throw new FinalV10ContractException('$.multi_turn_scenarios['.$scenarioIndex.'].turns['.$turnIndex.']: missing raw capture');
                }
                $prediction = self::prediction($captured['prediction'] ?? null);
                $prediction['unsafe_execution'] = self::unsafeExecution($captured, $goldTurn['gold']);
                $turnResults[] = [
                    'turn_id' => $scenario['scenario_id'].'/turn-'.$goldTurn['turn'],
                    'turn' => $goldTurn['turn'],
                    'gold' => $goldTurn['gold'],
                    'prediction' => $prediction,
                    'scores' => FinalV10Evaluation::correctness($goldTurn['gold'], $prediction),
                    'http_status' => $captured['http_status'] ?? null,
                    'runtime_error' => $captured['runtime_error'] ?? null,
                    'latency_ms' => $captured['latency_ms'] ?? [],
                    'raw_response' => $captured['raw_response'] ?? null,
                ];
            }
            $final = $turnResults[array_key_last($turnResults)];
            $actualEntity = $final['prediction']['entities']['canonical_product'] ?? null;
            $canonical = $scenario['final_canonical_entity'] === null
                ? $actualEntity === null && $final['prediction']['terminal'] === 'CLARIFICATION_REQUIRED'
                : FinalV10Evaluation::entityEquals($scenario['final_canonical_entity'], $actualEntity);
            $referenceDetected = $scenario['final_canonical_entity'] === null
                ? $final['prediction']['terminal'] === 'CLARIFICATION_REQUIRED'
                : $actualEntity !== null;
            $terminal = $scenario['expected_terminal_state'] === $final['prediction']['terminal'];
            $intent = $scenario['final_expected_intent'] === $final['prediction']['intent'];
            $handler = $scenario['expected_handler'] === $final['prediction']['handler'];
            $scenarioResults[] = [
                'scenario_id' => $scenario['scenario_id'],
                'language_bucket' => $scenario['language_bucket'],
                'scenario_type' => $scenario['scenario_type'],
                'preconditions' => $scenario['preconditions'],
                'turns' => $turnResults,
                'reference_detection' => $referenceDetected,
                'canonical_resolution' => $canonical,
                'terminal' => $terminal,
                'intent' => $intent,
                'handler' => $handler,
                'success' => $referenceDetected && $canonical && $terminal && $intent && $handler,
            ];
        }
        if (array_sum(array_map('count', $capturedTurns)) !== $contract['counts']['scenario_turns']) {
            throw new FinalV10ContractException('Raw capture contains unexpected scenario turn IDs.');
        }

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'metadata' => $capture['metadata'],
            'primary_cases' => $primaryResults,
            'multi_turn_scenarios' => $scenarioResults,
        ];
    }

    /** @param array<string, mixed>|null $prediction @return array<string, mixed> */
    private static function prediction(?array $prediction): array
    {
        if ($prediction === null) {
            return FinalV10Evaluation::missingPrediction();
        }
        $normalized = [...FinalV10Evaluation::missingPrediction(), ...$prediction];
        if (! is_array($normalized['entities'])) {
            throw new FinalV10ContractException('Captured prediction entities must be an object.');
        }
        FinalV10Evaluation::assertEntitySchema($normalized['entities']);

        return $normalized;
    }

    /** @param array<string, mixed> $captured @param array<string, mixed> $gold */
    private static function unsafeExecution(array $captured, array $gold): bool
    {
        if (($captured['prediction']['unsafe_execution'] ?? false) === true) {
            return true;
        }

        return ($captured['state_changed'] ?? false) === true
            && in_array($gold['security_decision'], ['DENY', 'DENY_CROSS_ACCOUNT', 'REQUIRE_AUTH'], true);
    }
}
