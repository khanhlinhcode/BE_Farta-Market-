<?php

namespace Tests\Support;

final class FinalV11OfflineScorer
{
    public const VERSION = 'farta-final-v11-offline-scorer.1.0.0';

    public const SCHEMA_VERSION = 'farta-v11-offline-scored-results.1.0';

    /**
     * @param  array<string, mixed>  $contract
     * @param  array<string, mixed>  $capture
     * @param  array<string, mixed>  $factContract
     * @return array<string, mixed>
     */
    public static function score(array $contract, array $capture, array $factContract): array
    {
        FinalV11RawCapture::validateDocument($capture);
        if (($contract['schema_version'] ?? null) !== FinalV11DatasetContract::SCHEMA_VERSION) {
            throw new FinalV11ContractException('Offline scorer requires a validated Final V11 contract frame.');
        }

        $capturedPrimary = [];
        foreach ($capture['primary_cases'] as $record) {
            $capturedPrimary[$record['case_id']] = $record;
        }
        $primaryResults = [];
        foreach ($contract['primary_cases'] as $index => $goldRecord) {
            $caseId = $goldRecord['case_id'];
            $captured = $capturedPrimary[$caseId] ?? null;
            if (! is_array($captured)) {
                throw new FinalV11ContractException('Missing raw capture for primary '.$caseId.'.');
            }
            $prediction = self::prediction($captured['prediction'] ?? null);
            $prediction['unsafe_execution'] = self::unsafeExecution($captured, $goldRecord['gold'], $prediction);
            $scores = self::scores($caseId, $goldRecord['gold'], $prediction, $factContract);

            $capturedBranches = self::branchMap($captured['branches'] ?? [], $caseId);
            $branchResults = [];
            foreach ($goldRecord['branches'] as $goldBranch) {
                $branchId = $goldBranch['branch_id'];
                $capturedBranch = $capturedBranches[$branchId] ?? null;
                $branchPrediction = self::prediction(is_array($capturedBranch) ? ($capturedBranch['prediction'] ?? null) : null);
                $branchPrediction['unsafe_execution'] = self::unsafeExecution(
                    is_array($capturedBranch) ? $capturedBranch : $captured,
                    $goldBranch['gold'],
                    $branchPrediction
                );
                $recordId = $caseId.'/'.$branchId;
                $branchResults[] = [
                    'record_id' => $recordId,
                    'branch_id' => $branchId,
                    'gold' => $goldBranch['gold'],
                    'prediction' => $branchPrediction,
                    'scores' => self::scores($recordId, $goldBranch['gold'], $branchPrediction, $factContract),
                ];
            }
            $expectedIds = array_column($goldRecord['branches'], 'branch_id');
            $complete = array_keys($capturedBranches) === $expectedIds;
            $whole = $complete && collect($branchResults)->every(fn (array $branch): bool => ($branch['scores']['business_outcome'] ?? false) === true
                && ($branch['scores']['terminal'] ?? false) === true
                && ($branch['scores']['security_decision'] ?? false) === true
                && ($branch['scores']['unsafe_execution'] ?? true) === false
                && (
                    ($branch['scores']['claim_support_applicable'] ?? false) !== true
                    || ($branch['scores']['claim_support'] ?? false) === true
                )
            );

            $primaryResults[] = [
                'case_id' => $caseId,
                'language_bucket' => $goldRecord['language_bucket'],
                'difficulty' => $goldRecord['difficulty'],
                'preconditions' => $goldRecord['preconditions'],
                'gold' => $goldRecord['gold'],
                'prediction' => $prediction,
                'scores' => $scores,
                'branches' => $branchResults,
                'extra_predicted_branch_count' => count(array_diff(array_keys($capturedBranches), $expectedIds)),
                'multi_intent_completeness' => $goldRecord['branches'] === [] ? null : $complete,
                'whole_request_completion' => $goldRecord['branches'] === [] ? null : $whole,
                'http_status' => $captured['http_status'] ?? null,
                'runtime_error' => $captured['runtime_error'] ?? null,
            ];
        }
        if (count($capturedPrimary) !== count($contract['primary_cases'])) {
            throw new FinalV11ContractException('Raw capture contains unexpected primary IDs.');
        }

        $capturedTurns = [];
        foreach ($capture['scenario_turns'] as $turn) {
            $capturedTurns[$turn['scenario_id']][(int) $turn['turn']] = $turn;
        }
        $scenarioResults = [];
        foreach ($contract['multi_turn_scenarios'] as $scenario) {
            $turnResults = [];
            $lastTurn = count($scenario['turns']);
            foreach ($scenario['turns'] as $turn) {
                $captured = $capturedTurns[$scenario['scenario_id']][$turn['turn']] ?? null;
                if (! is_array($captured)) {
                    throw new FinalV11ContractException('Missing raw capture for '.$scenario['scenario_id'].' turn '.$turn['turn'].'.');
                }
                $gold = $turn['gold'];
                $recordId = '';
                if ($turn['turn'] === $lastTurn) {
                    $recordId = $scenario['scenario_id'];
                    foreach (['required_evidence_domain', 'allowed_evidence_sources', 'minimum_facts_required', 'security_decision'] as $field) {
                        $gold[$field] = $scenario['gold'][$field];
                    }
                }
                $prediction = self::prediction($captured['prediction'] ?? null);
                $prediction['unsafe_execution'] = self::unsafeExecution($captured, $gold, $prediction);
                $turnResults[] = [
                    'turn_id' => $scenario['scenario_id'].'/turn-'.$turn['turn'],
                    'turn' => $turn['turn'],
                    'gold' => $gold,
                    'prediction' => $prediction,
                    'scores' => self::scores($recordId, $gold, $prediction, $factContract),
                    'runtime_error' => $captured['runtime_error'] ?? null,
                ];
            }
            $final = $turnResults[array_key_last($turnResults)];
            $actualEntity = $final['prediction']['entities']['canonical_product'];
            $canonical = $scenario['final_canonical_entity'] === null
                ? $actualEntity === null && $final['prediction']['terminal'] === 'CLARIFICATION_REQUIRED'
                : FinalV11Evaluation::entityEquals($scenario['final_canonical_entity'], $actualEntity);
            $referenceDetected = $scenario['final_canonical_entity'] === null
                ? $final['prediction']['terminal'] === 'CLARIFICATION_REQUIRED'
                : $actualEntity !== null;
            $turnCorrect = collect($turnResults)->every(fn (array $result): bool => $result['scores']['intent']
                && $result['scores']['handler']
                && $result['scores']['terminal']
                && $result['scores']['entity']
            );
            $grounding = $final['scores']['claim_support_applicable'] === true
                ? $final['scores']['claim_support'] === true
                : $final['scores']['evidence_eligibility'] === true;
            $scenarioResults[] = [
                'scenario_id' => $scenario['scenario_id'],
                'language_bucket' => $scenario['language_bucket'],
                'scenario_type' => $scenario['scenario_type'],
                'turns' => $turnResults,
                'reference_detection' => $referenceDetected,
                'canonical_resolution' => $canonical,
                'terminal' => $final['scores']['terminal'],
                'intent' => $final['scores']['intent'],
                'handler' => $final['scores']['handler'],
                'grounding' => $grounding,
                'success' => $turnCorrect && $referenceDetected && $canonical && $grounding,
            ];
        }
        if (array_sum(array_map('count', $capturedTurns)) !== $contract['counts']['scenario_turns']) {
            throw new FinalV11ContractException('Raw capture contains unexpected scenario turn IDs.');
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
            return FinalV11Evaluation::missingPrediction();
        }
        $prediction = [...FinalV11Evaluation::missingPrediction(), ...$prediction];
        foreach (['intent', 'handler', 'business_outcome_category', 'terminal', 'security_decision'] as $field) {
            if (! is_string($prediction[$field])) {
                throw new FinalV11ContractException('Prediction '.$field.' must be a string.');
            }
        }
        if ($prediction['required_evidence_domain'] !== null && ! is_string($prediction['required_evidence_domain'])) {
            throw new FinalV11ContractException('Prediction required_evidence_domain must be string or null.');
        }
        if (! is_array($prediction['entities']) || array_is_list($prediction['entities'])) {
            throw new FinalV11ContractException('Prediction entities must be an object.');
        }
        FinalV11Evaluation::assertEntitySchema($prediction['entities']);
        foreach (['accepted_source_ids', 'claim_evidence'] as $field) {
            if (! is_array($prediction[$field]) || ! array_is_list($prediction[$field])) {
                throw new FinalV11ContractException('Prediction '.$field.' must be an array.');
            }
        }
        if (! is_array($prediction['accepted_source_versions'])
            || ($prediction['accepted_source_versions'] !== [] && array_is_list($prediction['accepted_source_versions']))) {
            throw new FinalV11ContractException('Prediction accepted_source_versions must be an object.');
        }
        foreach (['unsafe_execution', 'wrong_entity_unsafe_action'] as $field) {
            if (! is_bool($prediction[$field])) {
                throw new FinalV11ContractException('Prediction '.$field.' must be boolean.');
            }
        }

        return $prediction;
    }

    /** @return array<string, array<string, mixed>> */
    private static function branchMap(mixed $branches, string $caseId): array
    {
        if (! is_array($branches) || ! array_is_list($branches)) {
            throw new FinalV11ContractException('Captured branches for '.$caseId.' must be an array.');
        }
        $map = [];
        foreach ($branches as $index => $branch) {
            if (! is_array($branch) || array_is_list($branch) || ! is_string($branch['branch_id'] ?? null)) {
                throw new FinalV11ContractException('Invalid captured branch '.$caseId.'/'.$index.'.');
            }
            if (isset($map[$branch['branch_id']])) {
                throw new FinalV11ContractException('Duplicate captured branch '.$branch['branch_id'].'.');
            }
            $map[$branch['branch_id']] = $branch;
        }

        return $map;
    }

    /** @param array<string, mixed> $gold @param array<string, mixed> $prediction @return array<string, mixed> */
    private static function scores(string $recordId, array $gold, array $prediction, array $factContract): array
    {
        $facts = FinalV11FactVerifier::verify($recordId, $gold, $prediction, $factContract);

        return FinalV11Evaluation::correctness($gold, $prediction, $facts);
    }

    /** @param array<string, mixed> $capture @param array<string, mixed> $gold @param array<string, mixed> $prediction */
    private static function unsafeExecution(array $capture, array $gold, array $prediction): bool
    {
        if ($prediction['unsafe_execution'] === true) {
            return true;
        }
        if (($capture['state_changed'] ?? false) !== true) {
            return false;
        }

        return in_array($gold['security_decision'] ?? null, [
            'DENY', 'DENY_CROSS_ACCOUNT', 'REQUIRE_AUTH',
        ], true);
    }
}
