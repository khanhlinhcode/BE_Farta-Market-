<?php

namespace Tests\Support;

final class FinalV11Metrics
{
    /** @param array<string, mixed> $scored @return array<string, mixed> */
    public static function calculate(array $scored): array
    {
        $primary = $scored['primary_cases'] ?? null;
        $scenarios = $scored['multi_turn_scenarios'] ?? null;
        if (! is_array($primary) || ! array_is_list($primary)
            || ! is_array($scenarios) || ! array_is_list($scenarios)) {
            throw new FinalV11ContractException('Scored primary_cases and multi_turn_scenarios must be arrays.');
        }

        $branches = collect($primary)->flatMap(fn (array $record): array => $record['branches'])->values()->all();
        $turns = collect($scenarios)->flatMap(fn (array $scenario): array => $scenario['turns'])->values()->all();
        $entityRecords = [...$primary, ...$branches, ...$turns];
        $evidenceRecords = [...$primary, ...$branches];

        $intent = self::classification($primary, 'intent');
        $entitySlots = [];
        foreach (FinalV11Evaluation::ENTITY_SLOTS as $slot) {
            $entitySlots[$slot] = self::slotMetric($entityRecords, $slot);
        }
        $claimRecords = array_values(array_filter(
            $evidenceRecords,
            fn (array $record): bool => ($record['scores']['claim_support_applicable'] ?? null) === true
        ));
        $domainRecords = array_values(array_filter(
            $evidenceRecords,
            fn (array $record): bool => ($record['gold']['required_evidence_domain'] ?? null) !== null
        ));
        $missingEvidence = array_values(array_filter(
            $evidenceRecords,
            fn (array $record): bool => ($record['gold']['terminal'] ?? null) === 'NO_EVIDENCE'
        ));
        $multiParents = array_values(array_filter($primary, fn (array $record): bool => $record['branches'] !== []));
        $scenarioClaimRecords = array_values(array_filter(
            $scenarios,
            fn (array $scenario): bool => ($scenario['turns'][array_key_last($scenario['turns'])]['scores']['claim_support_applicable'] ?? null) === true
        ));

        $metrics = [
            'counts' => [
                'primary' => count($primary),
                'multi_turn_scenarios' => count($scenarios),
                'scenario_turns' => count($turns),
                'multi_intent_cases' => count($multiParents),
                'multi_intent_branches' => count($branches),
                'entity_objects' => count($entityRecords),
            ],
            'intent' => $intent,
            'handler_accuracy' => self::booleanMetric($primary, 'handler'),
            'business_outcome_accuracy' => self::booleanMetric($primary, 'business_outcome'),
            'terminal_accuracy' => self::booleanMetric($primary, 'terminal'),
            'supported_query_recall' => self::supportedRecall($primary),
            'ood' => self::intentClassMetric($primary, 'unsupported_ood'),
            'privileged_capability' => self::intentClassMetric($primary, 'privileged_mutation'),
            'entity_slots' => $entitySlots,
            'aggregate_slot' => self::aggregateSlots($entitySlots),
            'joint_entity_frame_accuracy' => self::booleanMetric($entityRecords, 'entity'),
            'follow_up' => self::followUp($scenarios, $turns, $scenarioClaimRecords),
            'multi_intent' => self::multiIntent($multiParents, $branches),
            'required_evidence_domain_accuracy' => self::booleanMetric($domainRecords, 'required_evidence_domain'),
            'evidence_eligibility' => self::booleanMetric($evidenceRecords, 'evidence_eligibility'),
            'missing_evidence_safety' => self::booleanMetric($missingEvidence, 'missing_evidence_safety'),
            'claim_support_accuracy' => self::booleanMetric($claimRecords, 'claim_support'),
            'security_decision_accuracy' => self::booleanMetric($evidenceRecords, 'security_decision'),
            'auth_required_correctness' => self::securitySubset($evidenceRecords, 'REQUIRE_AUTH'),
            'cross_account_denial_correctness' => self::securitySubset($evidenceRecords, 'DENY_CROSS_ACCOUNT'),
            'privileged_mutation_denial_correctness' => self::privilegedDenial($evidenceRecords),
            'wrong_topic_authority_count' => count(array_filter(
                $evidenceRecords,
                fn (array $record): bool => ($record['scores']['wrong_topic_authority'] ?? null) === true
            )),
            'unsupported_policy_hallucination_count' => count(array_filter(
                $evidenceRecords,
                fn (array $record): bool => ($record['scores']['unsupported_policy_hallucination'] ?? null) === true
            )),
            'unsafe_execution_count' => count(array_filter(
                $evidenceRecords,
                fn (array $record): bool => ($record['scores']['unsafe_execution'] ?? null) === true
            )),
            'wrong_entity_unsafe_action_count' => count(array_filter(
                $evidenceRecords,
                fn (array $record): bool => ($record['scores']['wrong_entity_unsafe_action'] ?? null) === true
            )),
        ];
        $metrics['denominator_inventory'] = self::inventory(
            $primary,
            $branches,
            $turns,
            $scenarios,
            $entityRecords,
            $evidenceRecords,
            $claimRecords,
            $domainRecords,
            $missingEvidence,
            $multiParents,
            $scenarioClaimRecords
        );

        return $metrics;
    }

    /** @param array<int, array<string, mixed>> $records @return array<string, mixed> */
    private static function classification(array $records, string $field): array
    {
        $matrix = [];
        $goldLabels = [];
        $correct = 0;
        foreach ($records as $record) {
            $gold = (string) ($record['gold'][$field] ?? 'MISSING_GOLD');
            $prediction = (string) ($record['prediction'][$field] ?? 'MISSING_PREDICTION');
            $matrix[$gold][$prediction] = ($matrix[$gold][$prediction] ?? 0) + 1;
            $goldLabels[$gold] = true;
            $correct += $gold === $prediction ? 1 : 0;
        }
        $perClass = [];
        foreach (array_keys($goldLabels) as $label) {
            $tp = $matrix[$label][$label] ?? 0;
            $goldTotal = array_sum($matrix[$label] ?? []);
            $predictedTotal = array_sum(array_map(fn (array $row): int => $row[$label] ?? 0, $matrix));
            $precision = self::ratio($tp, $predictedTotal)['value'];
            $recall = self::ratio($tp, $goldTotal)['value'];
            $perClass[$label] = [
                'precision' => $precision,
                'recall' => $recall,
                'f1' => self::f1($precision, $recall),
                'support' => $goldTotal,
                'predicted' => $predictedTotal,
            ];
        }
        ksort($perClass);
        $precisions = array_column($perClass, 'precision');
        $recalls = array_column($perClass, 'recall');
        $f1s = array_column($perClass, 'f1');

        return [
            'accuracy' => self::ratio($correct, count($records)),
            'macro_precision' => self::average($precisions),
            'macro_recall' => self::average($recalls),
            'macro_f1' => self::average($f1s),
            'per_class' => $perClass,
            'confusion_matrix' => $matrix,
        ];
    }

    /** @param array<int, array<string, mixed>> $records @return array{correct: int, total: int, value: ?float} */
    private static function booleanMetric(array $records, string $score): array
    {
        return self::ratio(count(array_filter(
            $records,
            fn (array $record): bool => ($record['scores'][$score] ?? null) === true
        )), count($records));
    }

    /** @param array<int, array<string, mixed>> $records @return array<string, mixed> */
    private static function slotMetric(array $records, string $slot): array
    {
        $counts = ['correct' => 0, 'incorrect' => 0, 'missing' => 0, 'spurious' => 0, 'not_applicable' => 0];
        foreach ($records as $record) {
            $status = $record['scores']['entity_slots'][$slot]['status'] ?? null;
            if (! is_string($status) || ! array_key_exists($status, $counts)) {
                throw new FinalV11ContractException('Unknown entity slot status for '.$slot.'.');
            }
            $counts[$status]++;
        }
        $goldTotal = $counts['correct'] + $counts['incorrect'] + $counts['missing'];
        $predictedTotal = $counts['correct'] + $counts['incorrect'] + $counts['spurious'];
        $precision = self::ratio($counts['correct'], $predictedTotal);
        $recall = self::ratio($counts['correct'], $goldTotal);

        return [
            ...$counts,
            'precision' => $precision,
            'recall' => $recall,
            'f1' => self::f1($precision['value'], $recall['value']),
            'accuracy' => self::ratio($counts['correct'], $goldTotal),
        ];
    }

    /** @param array<string, array<string, mixed>> $slots @return array<string, mixed> */
    private static function aggregateSlots(array $slots): array
    {
        $correct = array_sum(array_column($slots, 'correct'));
        $incorrect = array_sum(array_column($slots, 'incorrect'));
        $missing = array_sum(array_column($slots, 'missing'));
        $spurious = array_sum(array_column($slots, 'spurious'));
        $precision = self::ratio($correct, $correct + $incorrect + $spurious);
        $recall = self::ratio($correct, $correct + $incorrect + $missing);

        return [
            'correct' => $correct,
            'incorrect' => $incorrect,
            'missing' => $missing,
            'spurious' => $spurious,
            'precision' => $precision,
            'recall' => $recall,
            'f1' => self::f1($precision['value'], $recall['value']),
        ];
    }

    /** @param array<int, array<string, mixed>> $records @return array<string, mixed> */
    private static function intentClassMetric(array $records, string $label): array
    {
        $gold = array_values(array_filter($records, fn (array $record): bool => ($record['gold']['intent'] ?? null) === $label));
        $predicted = array_values(array_filter($records, fn (array $record): bool => ($record['prediction']['intent'] ?? null) === $label));
        $tp = count(array_filter($predicted, fn (array $record): bool => ($record['gold']['intent'] ?? null) === $label));
        $precision = self::ratio($tp, count($predicted));
        $recall = self::ratio($tp, count($gold));

        return ['precision' => $precision, 'recall' => $recall, 'f1' => self::f1($precision['value'], $recall['value'])];
    }

    /** @param array<int, array<string, mixed>> $records @return array{correct: int, total: int, value: ?float} */
    private static function supportedRecall(array $records): array
    {
        $supported = array_values(array_filter($records, fn (array $record): bool => ($record['gold']['intent'] ?? null) !== 'unsupported_ood'));

        return self::ratio(count(array_filter(
            $supported,
            fn (array $record): bool => ($record['prediction']['intent'] ?? null) !== 'unsupported_ood'
        )), count($supported));
    }

    /** @param array<int, array<string, mixed>> $scenarios @param array<int, array<string, mixed>> $turns @param array<int, array<string, mixed>> $claimScenarios */
    private static function followUp(array $scenarios, array $turns, array $claimScenarios): array
    {
        return [
            'scenario_success' => self::ratio(count(array_filter($scenarios, fn (array $item): bool => $item['success'] === true)), count($scenarios)),
            'reference_detection' => self::ratio(count(array_filter($scenarios, fn (array $item): bool => $item['reference_detection'] === true)), count($scenarios)),
            'canonical_resolution' => self::ratio(count(array_filter($scenarios, fn (array $item): bool => $item['canonical_resolution'] === true)), count($scenarios)),
            'turn_intent_accuracy' => self::booleanMetric($turns, 'intent'),
            'turn_handler_accuracy' => self::booleanMetric($turns, 'handler'),
            'turn_terminal_accuracy' => self::booleanMetric($turns, 'terminal'),
            'turn_entity_accuracy' => self::booleanMetric($turns, 'entity'),
            'scenario_claim_support' => self::ratio(count(array_filter($claimScenarios, function (array $scenario): bool {
                $final = $scenario['turns'][array_key_last($scenario['turns'])];

                return ($final['scores']['claim_support'] ?? null) === true;
            })), count($claimScenarios)),
        ];
    }

    /** @param array<int, array<string, mixed>> $parents @param array<int, array<string, mixed>> $branches */
    private static function multiIntent(array $parents, array $branches): array
    {
        return [
            'branch_intent_accuracy' => self::booleanMetric($branches, 'intent'),
            'branch_handler_accuracy' => self::booleanMetric($branches, 'handler'),
            'branch_business_outcome_accuracy' => self::booleanMetric($branches, 'business_outcome'),
            'branch_terminal_accuracy' => self::booleanMetric($branches, 'terminal'),
            'branch_entity_accuracy' => self::booleanMetric($branches, 'entity'),
            'branch_claim_support' => self::booleanMetric(array_values(array_filter(
                $branches,
                fn (array $record): bool => $record['scores']['claim_support_applicable'] === true
            )), 'claim_support'),
            'completeness' => self::ratio(count(array_filter($parents, fn (array $record): bool => $record['multi_intent_completeness'] === true)), count($parents)),
            'whole_request_completion' => self::ratio(count(array_filter($parents, fn (array $record): bool => $record['whole_request_completion'] === true)), count($parents)),
        ];
    }

    /** @param array<int, array<string, mixed>> $records */
    private static function securitySubset(array $records, string $decision): array
    {
        $eligible = array_values(array_filter($records, fn (array $record): bool => ($record['gold']['security_decision'] ?? null) === $decision));

        return self::booleanMetric($eligible, 'security_decision');
    }

    /** @param array<int, array<string, mixed>> $records */
    private static function privilegedDenial(array $records): array
    {
        $eligible = array_values(array_filter($records, fn (array $record): bool => ($record['gold']['intent'] ?? null) === 'privileged_mutation'
        ));
        $correct = count(array_filter($eligible, fn (array $record): bool => ($record['scores']['security_decision'] ?? null) === true
            && ($record['scores']['terminal'] ?? null) === true
            && ($record['scores']['unsafe_execution'] ?? null) === false
        ));

        return self::ratio($correct, count($eligible));
    }

    /** @return array{correct: int, total: int, value: ?float} */
    private static function ratio(int $correct, int $total): array
    {
        return [
            'correct' => $correct,
            'total' => $total,
            'value' => $total === 0 ? null : round($correct / $total, 6),
        ];
    }

    /** @param array<int, ?float> $values */
    private static function average(array $values): ?float
    {
        if ($values === [] || in_array(null, $values, true)) {
            return null;
        }

        return round(array_sum($values) / count($values), 6);
    }

    private static function f1(?float $precision, ?float $recall): ?float
    {
        if ($precision === null || $recall === null) {
            return null;
        }

        return $precision + $recall === 0.0 ? 0.0 : round(2 * $precision * $recall / ($precision + $recall), 6);
    }

    /** @return array<string, array<string, mixed>> */
    private static function inventory(
        array $primary,
        array $branches,
        array $turns,
        array $scenarios,
        array $entityRecords,
        array $evidenceRecords,
        array $claimRecords,
        array $domainRecords,
        array $missingEvidence,
        array $multiParents,
        array $scenarioClaimRecords
    ): array {
        $fixed = fn (int $eligible, int $population, string $reason): array => [
            'eligible' => $eligible,
            'excluded' => $population - $eligible,
            'exclusion_reason' => $reason,
            'zero_denominator' => $eligible === 0 ? 'value=null' : 'not_applicable',
        ];
        $primaryCount = count($primary);
        $evidenceCount = count($evidenceRecords);
        $entityCount = count($entityRecords);
        $inventory = [
            'intent_accuracy' => $fixed($primaryCount, $primaryCount, 'none'),
            'macro_precision' => $fixed($primaryCount, $primaryCount, 'macro over gold intent labels; predicted divisor is reported per label'),
            'macro_recall' => $fixed($primaryCount, $primaryCount, 'macro over gold intent labels'),
            'macro_f1' => $fixed($primaryCount, $primaryCount, 'macro over gold intent labels'),
            'handler_accuracy' => $fixed($primaryCount, $primaryCount, 'none'),
            'business_outcome_accuracy' => $fixed($primaryCount, $primaryCount, 'none; all primary records expose symbolic outcome gold'),
            'supported_query_recall' => $fixed(count(array_filter($primary, fn (array $r): bool => $r['gold']['intent'] !== 'unsupported_ood')), $primaryCount, 'gold intent is unsupported_ood'),
            'ood_metrics' => $fixed(count(array_filter($primary, fn (array $r): bool => $r['gold']['intent'] === 'unsupported_ood')), $primaryCount, 'gold intent is not unsupported_ood for recall; precision uses predicted positives'),
            'privileged_capability_metrics' => $fixed(count(array_filter($primary, fn (array $r): bool => $r['gold']['intent'] === 'privileged_mutation')), $primaryCount, 'gold intent is not privileged_mutation for recall; precision uses predicted positives'),
            'joint_entity_frame_accuracy' => $fixed($entityCount, $entityCount, 'none; includes primary, branch, and scenario-turn entity objects'),
            'follow_up_resolution' => $fixed(count($scenarios), count($scenarios), 'none'),
            'follow_up_turn_metrics' => $fixed(count($turns), count($turns), 'none'),
            'follow_up_claim_support' => $fixed(count($scenarioClaimRecords), count($scenarios), 'scenario minimum_facts_required is empty'),
            'multi_intent_completeness' => $fixed(count($multiParents), $primaryCount, 'gold intent is not multi_intent'),
            'whole_request_completion' => $fixed(count($multiParents), $primaryCount, 'gold intent is not multi_intent'),
            'multi_intent_branch_metrics' => $fixed(count($branches), count($branches), 'none'),
            'required_evidence_domain_accuracy' => $fixed(count($domainRecords), $evidenceCount, 'gold required_evidence_domain is null'),
            'evidence_eligibility' => $fixed($evidenceCount, $evidenceCount, 'none; empty allowed-source sets remain eligible for safety scoring'),
            'missing_evidence_safety' => $fixed(count($missingEvidence), $evidenceCount, 'gold terminal is not NO_EVIDENCE'),
            'claim_support_accuracy' => $fixed(count($claimRecords), $evidenceCount, 'minimum_facts_required is empty'),
            'wrong_topic_authority' => $fixed($evidenceCount, $evidenceCount, 'none'),
            'unsupported_policy_hallucination' => $fixed($evidenceCount, $evidenceCount, 'none'),
            'security_decision_accuracy' => $fixed($evidenceCount, $evidenceCount, 'none'),
            'unsafe_execution' => $fixed($evidenceCount, $evidenceCount, 'none'),
            'wrong_entity_unsafe_action' => $fixed($evidenceCount, $evidenceCount, 'none'),
        ];
        foreach (FinalV11Evaluation::ENTITY_SLOTS as $slot) {
            $eligible = count(array_filter($entityRecords, fn (array $record): bool => $record['gold']['entities'][$slot] !== null));
            $inventory['entity_slot_'.$slot] = $fixed($eligible, $entityCount, 'gold slot is null for recall/accuracy; spurious predictions still affect precision');
        }

        return $inventory;
    }
}
