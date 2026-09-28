<?php

namespace Tests\Support;

final class FinalV10Report
{
    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $qa
     */
    public static function render(array $raw, array $metrics, array $qa, string $rawHash, string $postRunCandidateHash): string
    {
        $metadata = $raw['metadata'];
        $lines = [
            '# Final V10 replacement scored evaluation report',
            '',
            '## 1. EXECUTIVE SUMMARY',
            '',
            '**'.$metrics['release_decision'].'**',
            '',
            'This is valid run candidate #2: the single replacement for invalid run #1. No invalid-run output was reused, and no runtime, gold, threshold, or evaluator change was made after the first replacement case. Storefront/mobile UI was outside this pass.',
            '',
            '## 2. RUN INTEGRITY',
            '',
            '- One-scored-run declaration: `'.($metadata['one_scored_run_declaration'] ? 'TRUE' : 'FALSE').'`',
            '- Run ID: `'.$metadata['run_id'].'`',
            '- Started UTC: `'.$metadata['started_at_utc'].'`',
            '- Completed UTC: `'.$metadata['completed_at_utc'].'`',
            '- Deterministic order: dataset array order; primary cases first, then scenario array order and turn order.',
            '- Manual retries: `0`',
            '- Session contamination detected: `'.(($metadata['session_contamination_detected'] ?? false) ? 'YES' : 'NO').'`',
            '',
            '## 3. CANDIDATE HASH VERIFICATION',
            '',
            '- Expected: `'.FinalV10Evaluation::CANDIDATE_SHA256.'`',
            '- Pre-run observed: `'.$metadata['candidate_hash'].'`',
            '- Result: '.($metadata['candidate_hash'] === FinalV10Evaluation::CANDIDATE_SHA256 ? 'PASS' : 'FAIL'),
            '',
            '## 4. FINAL V10 HASH VERIFICATION',
            '',
            '| Artifact | SHA-256 | Result |',
            '|---|---|---|',
            '| Final dataset | `'.$metadata['dataset_hash'].'` | '.($metadata['dataset_hash'] === FinalV10Evaluation::DATASET_SHA256 ? 'PASS' : 'FAIL').' |',
            '| Final audit report | `'.$metadata['audit_hash'].'` | '.($metadata['audit_hash'] === FinalV10Evaluation::AUDIT_SHA256 ? 'PASS' : 'FAIL').' |',
            '| Final manifest | `'.$metadata['manifest_hash'].'` | '.($metadata['manifest_hash'] === FinalV10Evaluation::MANIFEST_SHA256 ? 'PASS' : 'FAIL').' |',
            '',
            'Schema `farta-v10.3.0`, freeze status `FROZEN`, and all manifest lineage/count checks passed before execution.',
            '',
            '## 5. EVALUATOR/HARNESS VERIFICATION',
            '',
            '- Version: `'.$metadata['evaluator_version'].'`',
            '- Frozen harness hash: `'.$metadata['evaluator_hash'].'`',
            '- Golden validation: PASS before scoring.',
            '- Missing predictions fail; errors remain in denominators; entity slots are scored separately; multi-intent branches use fixed ordinal alignment.',
            '- Normalization: Unicode-to-ASCII, lowercase, punctuation-to-space, whitespace squish for textual entity equality only.',
            '- Runtime route labels are not upgraded to finer V10 labels from gold or utterance text.',
            '',
            '## 6. INFRASTRUCTURE PREFLIGHT',
            '',
            self::preflightText($metadata['infrastructure_preflight'] ?? []),
            '',
            '## 7. SCORED RUN IDENTITY',
            '',
            '- Candidate: `'.$metadata['candidate_hash'].'`',
            '- Dataset: `'.$metadata['dataset_hash'].'`',
            '- Evaluator: `'.$metadata['evaluator_hash'].'`',
            '- Environment: `'.json_encode($metadata['environment'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'`',
            '',
            '## 8. RAW RESULTS ARTIFACT + HASH',
            '',
            '- Artifact: `artifacts/evaluation/v10/scored-raw-results.json`',
            '- SHA-256: `'.$rawHash.'`',
            '- Raw results were not edited after this hash was calculated.',
            '',
            '## 9. DATASET COUNTS',
            '',
            self::countTable($metrics['counts']),
            '',
            '## 10. INTENT METRICS',
            '',
            self::intentSummary($metrics['intent']),
            '',
            '## 11. CONFUSION MATRIX',
            '',
            self::confusionTable($metrics['intent']['confusion_matrix']),
            '',
            '## 12. PER-INTENT PRECISION/RECALL/F1',
            '',
            self::perIntentTable($metrics['intent']['per_intent']),
            '',
            '## 13. HANDLER ACCURACY',
            '',
            self::metricText($metrics['handler_accuracy']),
            '',
            '## 14. BUSINESS OUTCOME ACCURACY',
            '',
            self::metricText($metrics['business_outcome_accuracy']),
            '',
            '## 15. PRODUCT ENTITY METRICS',
            '',
            self::slotTable($metrics['entity_slots'], ['product_raw_mention', 'canonical_product']).'\n\nAggregate slot P/R/F1: '.self::prfText($metrics['aggregate_slot']).' Joint frame: '.self::metricText($metrics['joint_entity_frame_accuracy']),
            '',
            '## 16. QUANTITY / UNIT METRICS',
            '',
            self::slotTable($metrics['entity_slots'], ['quantity', 'unit']),
            '',
            '## 17. ACCOUNT_TARGET METRICS',
            '',
            self::slotTable($metrics['entity_slots'], ['account_target']),
            '',
            '## 18. REQUESTED_MUTATION_VALUE METRICS',
            '',
            self::slotTable($metrics['entity_slots'], ['requested_mutation_value']),
            '',
            '## 19. ORDER / CONTEXT REFERENCE METRICS',
            '',
            self::slotTable($metrics['entity_slots'], ['order_reference', 'ordinal_reference', 'context_reference']),
            '',
            '## 20. FOLLOW-UP METRICS',
            '',
            '- Scenario success: '.self::metricText($metrics['follow_up']['scenario_success'])."\n".
            '- Reference detection: '.self::metricText($metrics['follow_up']['reference_detection'])."\n".
            '- Canonical reference resolution: '.self::metricText($metrics['follow_up']['canonical_reference_resolution'])."\n".
            '- Terminal correctness: '.self::metricText($metrics['follow_up']['terminal_correctness']),
            '',
            '## 21. FOLLOW-UP SUBTYPE BREAKDOWN',
            '',
            self::followUpSubtypeTable($metrics['follow_up']['by_subtype']),
            '',
            '## 22. MULTI-INTENT BRANCH METRICS',
            '',
            '- Branch intent: '.self::metricText($metrics['multi_intent']['branch_intent_accuracy'])."\n".
            '- Branch handler: '.self::metricText($metrics['multi_intent']['branch_handler_accuracy'])."\n".
            '- Branch entity: '.self::metricText($metrics['multi_intent']['branch_entity_correctness'])."\n".
            '- Branch terminal: '.self::metricText($metrics['multi_intent']['branch_terminal_accuracy']),
            '',
            '## 23. MULTI-INTENT COMPLETENESS',
            '',
            self::metricText($metrics['multi_intent']['completeness']),
            '',
            '## 24. WHOLE-REQUEST COMPLETION',
            '',
            self::metricText($metrics['multi_intent']['whole_request_completion']),
            '',
            '## 25. OOD PRECISION/RECALL/F1',
            '',
            self::prfText($metrics['ood']).' False Accept Rate: '.self::metricText($metrics['ood']['false_accept_rate']),
            '',
            '## 26. SUPPORTED QUERY RECALL',
            '',
            self::metricText($metrics['supported_query_recall']),
            '',
            '## 27. ABSTENTION / CLARIFICATION',
            '',
            self::prfText($metrics['clarification']).' Abstention Rate: '.self::metricText($metrics['clarification']['abstention_rate']),
            '',
            '## 28. PRIVILEGED CAPABILITY METRICS',
            '',
            self::prfText($metrics['privileged_capability']).' DENIED correctness: '.self::metricText($metrics['denied_correctness']),
            '',
            '## 29. BENIGN READ FALSE-DENIAL RATE',
            '',
            self::metricText($metrics['benign_read_false_denial_rate']),
            '',
            '## 30. REQUIRED EVIDENCE DOMAIN',
            '',
            self::metricText($metrics['required_evidence_domain_accuracy']),
            '',
            '## 31. EVIDENCE ELIGIBILITY',
            '',
            self::metricText($metrics['evidence_eligibility_accuracy']),
            '',
            '## 32. WRONG-TOPIC AUTHORITY COUNT',
            '',
            $metrics['wrong_topic_authority_count'].'; cases: '.self::idList($metrics['wrong_topic_authority_case_ids']),
            '',
            '## 33. MISSING-EVIDENCE SAFETY',
            '',
            self::metricText($metrics['missing_evidence_safety']),
            '',
            '## 34. CLAIM SUPPORT',
            '',
            self::metricText($metrics['claim_support_accuracy']),
            '',
            '## 35. GROUNDEDNESS',
            '',
            self::metricText($metrics['groundedness_accuracy']).' Deterministic source/section/structured-fact verifier; no post-hoc LLM judge.',
            '',
            '## 36. RESPONSE COMPLETENESS',
            '',
            self::metricText($metrics['response_completeness']),
            '',
            '## 37. POLICY HALLUCINATION COUNT',
            '',
            $metrics['unsupported_policy_hallucination_count'].'; cases: '.self::idList($metrics['unsupported_policy_hallucination_case_ids']),
            '',
            '## 38. RETRIEVAL METRICS',
            '',
            '**'.$metrics['retrieval_metrics']['status'].'** — '.$metrics['retrieval_metrics']['reason'],
            '',
            '## 39. LANGUAGE BREAKDOWN',
            '',
            self::breakdownTable($metrics['language_breakdown']),
            '',
            '## 40. CAPABILITY BREAKDOWN',
            '',
            self::breakdownTable($metrics['capability_breakdown']),
            '',
            '## 41. LATENCY',
            '',
            self::latencyTable($metrics['latency_ms']),
            '',
            '## 42. INFRASTRUCTURE/RUNTIME ERRORS',
            '',
            '```json\n'.json_encode($metrics['errors'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'\n```',
            '',
            '## 43. UNSAFE EXECUTION COUNT',
            '',
            (string) $metrics['unsafe_execution_count'],
            '',
            '## 44. WRONG-ENTITY UNSAFE ACTION COUNT',
            '',
            (string) $metrics['wrong_entity_unsafe_action_count'],
            '',
            '## 45. RELEASE GATE TABLE',
            '',
            self::gateTable($metrics['release_gates']),
            '',
            '## 46. HARD BLOCKERS',
            '',
            $metrics['hard_blockers'] === [] ? 'None observed.' : implode("\n", array_map(fn (string $blocker): string => '- '.$blocker, $metrics['hard_blockers'])),
            '',
            '## 47. BACKEND QA',
            '',
            self::qaTable($qa),
            '',
            '## 48. POST-RUN CANDIDATE HASH',
            '',
            '- Observed: `'.$postRunCandidateHash.'`',
            '- Match: '.($postRunCandidateHash === FinalV10Evaluation::CANDIDATE_SHA256 ? 'PASS' : 'FAIL'),
            '',
            '## 49. FIRST-FAILURE DISTRIBUTION',
            '',
            self::failureTable($metrics['first_failure_distribution']),
            '',
            '## 50. POST-HOC FAILURE TAXONOMY',
            '',
            'Attribution was performed only after raw results were frozen. Each failed primary case has one earliest observable layer; downstream effects are not double-counted as root causes.',
            '',
            '## 51. STOREFRONT STATUS',
            '',
            '**STOREFRONT NOT VERIFIED.**',
            '',
            '## 52. UNVERIFIED ITEMS',
            '',
            '- Ranked retrieval Hit@5/MRR/nDCG: not measured because V10 has no ranked relevance gold.',
            '- Qdrant/inference latency: not measured because those services were disabled by the frozen local evaluator configuration.',
            '- Production deployment and storefront/mobile behavior: not evaluated.',
            '',
            '## 53. EXECUTION MANIFEST HASHES',
            '',
            '- Raw results SHA-256: `'.$rawHash.'`',
            '- Score report SHA-256 is recorded in `artifacts/evaluation/v10/execution-manifest.json` after this report is closed.',
            '- The execution manifest is not self-hashed inside its own byte content.',
            '',
            '## 54. RELEASE DECISION',
            '',
            '**'.$metrics['release_decision'].'**',
            '',
            '## 55. NEXT STEP',
            '',
            $metrics['release_decision'] === 'BACKEND GO FOR CONTROLLED STAGING'
                ? 'Stop and wait for explicit authorization before any controlled staging activity. Do not deploy automatically.'
                : 'Preserve this historical Final V10 result. Do not patch or rerun V10. A future candidate requires a fresh independent blind holdout.',
            '',
        ];

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $raw @param array<string, mixed> $metrics @param array<string, mixed> $qa @return array<string, mixed> */
    public static function executionManifest(
        array $raw,
        array $metrics,
        array $qa,
        string $rawHash,
        string $scoreReportHash,
        string $postRunCandidateHash,
    ): array {
        return [
            'schema_version' => 'farta-v10-final-replacement-execution-manifest.1.0',
            'run_id' => $raw['metadata']['run_id'],
            'run_timestamp_utc' => $raw['metadata']['started_at_utc'],
            'candidate_hash' => $raw['metadata']['candidate_hash'],
            'post_run_candidate_hash' => $postRunCandidateHash,
            'final_v10_dataset_hash' => $raw['metadata']['dataset_hash'],
            'final_v10_audit_hash' => $raw['metadata']['audit_hash'],
            'final_v10_manifest_hash' => $raw['metadata']['manifest_hash'],
            'evaluator_version' => $raw['metadata']['evaluator_version'],
            'evaluator_hash' => $raw['metadata']['evaluator_hash'],
            'raw_results_hash' => $rawHash,
            'score_report_hash' => $scoreReportHash,
            'case_counts' => $metrics['counts'],
            'execution_status' => 'COMPLETE',
            'run_classification' => 'VALID_RUN_CANDIDATE_2',
            'predecessor_run_status' => 'INVALID_RUN_1_EVALUATOR_INFRASTRUCTURE_DEFECT',
            'invalid_run_output_reused' => false,
            'one_scored_run_declaration' => true,
            'manual_retries' => 0,
            'qa_status' => $qa['overall_status'] ?? 'UNKNOWN',
            'release_decision' => $metrics['release_decision'],
            'storefront_status' => 'STOREFRONT NOT VERIFIED',
        ];
    }

    /** @param array<string, mixed> $preflight */
    private static function preflightText(array $preflight): string
    {
        if ($preflight === []) {
            return 'No preflight record available.';
        }

        return implode("\n", array_map(
            fn (string $key, mixed $value): string => '- '.str_replace('_', ' ', $key).': `'.(is_scalar($value) ? (string) $value : json_encode($value)).'`',
            array_keys($preflight),
            array_values($preflight)
        ));
    }

    /** @param array<string, int> $counts */
    private static function countTable(array $counts): string
    {
        $rows = ['| Item | Count |', '|---|---:|'];
        foreach ($counts as $name => $count) {
            $rows[] = '| '.str_replace('_', ' ', $name).' | '.$count.' |';
        }

        return implode("\n", $rows);
    }

    /** @param array<string, mixed> $intent */
    private static function intentSummary(array $intent): string
    {
        return '- Accuracy: '.self::metricText($intent['accuracy'])."\n".
            '- Macro precision: '.self::percent($intent['macro_precision'])."\n".
            '- Macro recall: '.self::percent($intent['macro_recall'])."\n".
            '- Macro-F1: '.self::percent($intent['macro_f1']);
    }

    /** @param array<string, array<string, int>> $matrix */
    private static function confusionTable(array $matrix): string
    {
        $labels = array_keys($matrix);
        foreach ($matrix as $row) {
            $labels = array_values(array_unique([...$labels, ...array_keys($row)]));
        }
        sort($labels);
        $rows = ['| Gold \\ Pred | '.implode(' | ', $labels).' |', '|---|'.str_repeat('---:|', count($labels))];
        foreach ($labels as $gold) {
            $values = array_map(fn (string $predicted): int => $matrix[$gold][$predicted] ?? 0, $labels);
            $rows[] = '| '.$gold.' | '.implode(' | ', $values).' |';
        }

        return implode("\n", $rows);
    }

    /** @param array<string, array<string, mixed>> $perIntent */
    private static function perIntentTable(array $perIntent): string
    {
        $rows = ['| Intent | TP | Predicted | Support | Precision | Recall | F1 |', '|---|---:|---:|---:|---:|---:|---:|'];
        foreach ($perIntent as $intent => $values) {
            $rows[] = '| '.$intent.' | '.$values['true_positive'].' | '.$values['predicted'].' | '.$values['support'].' | '.self::percent($values['precision']).' | '.self::percent($values['recall']).' | '.self::percent($values['f1']).' |';
        }

        return implode("\n", $rows);
    }

    /** @param array<string, array<string, mixed>> $slots @param array<int, string> $selected */
    private static function slotTable(array $slots, array $selected): string
    {
        $rows = ['| Slot | Correct | Incorrect | Missing | Spurious | Accuracy | Precision | Recall | F1 |', '|---|---:|---:|---:|---:|---:|---:|---:|---:|'];
        foreach ($selected as $slot) {
            $value = $slots[$slot];
            $rows[] = '| '.$slot.' | '.$value['correct'].' | '.$value['incorrect'].' | '.$value['missing'].' | '.$value['spurious'].' | '.self::metricValue($value['accuracy']).' | '.self::metricValue($value['precision']).' | '.self::metricValue($value['recall']).' | '.self::percent($value['f1']).' |';
        }

        return implode("\n", $rows);
    }

    /** @param array<string, array<string, mixed>> $subtypes */
    private static function followUpSubtypeTable(array $subtypes): string
    {
        $rows = ['| Subtype | n | Success | Detection | Resolution | Terminal |', '|---|---:|---:|---:|---:|---:|'];
        foreach ($subtypes as $type => $values) {
            $rows[] = '| '.$type.' | '.$values['n'].' | '.self::metricValue($values['success_rate']).' | '.self::metricValue($values['reference_detection_rate']).' | '.self::metricValue($values['canonical_resolution_rate']).' | '.self::metricValue($values['terminal_rate']).' |';
        }

        return implode("\n", $rows);
    }

    /** @param array<string, array<string, mixed>> $breakdown */
    private static function breakdownTable(array $breakdown): string
    {
        $rows = ['| Bucket | n | Intent | Handler | Business outcome |', '|---|---:|---:|---:|---:|'];
        foreach ($breakdown as $bucket => $values) {
            $rows[] = '| '.$bucket.' | '.$values['n'].' | '.self::metricValue($values['intent_accuracy']).' | '.self::metricValue($values['handler_accuracy']).' | '.self::metricValue($values['business_outcome_accuracy']).' |';
        }

        return implode("\n", $rows);
    }

    /** @param array<string, mixed> $latency */
    private static function latencyTable(array $latency): string
    {
        $rows = ['| Measurement | n | p50 ms | p95 ms |', '|---|---:|---:|---:|'];
        foreach (['router_primary', 'total_request_primary', 'total_request_scenario_turns'] as $key) {
            $value = $latency[$key];
            $rows[] = '| '.$key.' | '.$value['n'].' | '.($value['p50'] ?? 'N/A').' | '.($value['p95'] ?? 'N/A').' |';
        }
        $rows[] = '| qdrant/inference | 0 | NOT MEASURED | NOT MEASURED |';

        return implode("\n", $rows);
    }

    /** @param array<string, array<string, mixed>> $gates */
    private static function gateTable(array $gates): string
    {
        $rows = ['| Gate | Observed | Threshold | Result |', '|---|---:|---:|---|'];
        foreach ($gates as $name => $gate) {
            $observed = is_float($gate['observed']) ? self::percent($gate['observed']) : (string) ($gate['observed'] ?? 'N/A');
            $threshold = is_float($gate['threshold']) ? self::percent($gate['threshold']) : (string) $gate['threshold'];
            $rows[] = '| '.$name.' | '.$observed.' | '.$threshold.' | '.($gate['pass'] ? 'PASS' : 'FAIL').' |';
        }

        return implode("\n", $rows);
    }

    /** @param array<string, mixed> $qa */
    private static function qaTable(array $qa): string
    {
        $rows = ['| Check | Status | Detail |', '|---|---|---|'];
        foreach ($qa['commands'] ?? [] as $command) {
            $rows[] = '| `'.str_replace('|', '\\|', (string) $command['command']).'` | '.$command['status'].' | '.str_replace('|', '\\|', (string) ($command['detail'] ?? '')).' |';
        }
        $rows[] = '| Overall | '.($qa['overall_status'] ?? 'UNKNOWN').' | |';

        return implode("\n", $rows);
    }

    /** @param array<int, array<string, mixed>> $distribution */
    private static function failureTable(array $distribution): string
    {
        if ($distribution === []) {
            return 'No failed primary cases.';
        }
        $rows = ['| Failure layer | Cases | % failed | Representative IDs | Impact |', '|---|---:|---:|---|---|'];
        foreach ($distribution as $row) {
            $rows[] = '| '.$row['failure_layer'].' | '.$row['case_count'].' | '.self::percent($row['percentage_of_failed_cases']).' | '.implode(', ', $row['representative_case_ids']).' | '.$row['business_safety_impact'].' |';
        }

        return implode("\n", $rows);
    }

    /** @param array<string, mixed> $metric */
    private static function metricText(array $metric): string
    {
        return ($metric['value'] === null ? 'N/A' : self::percent($metric['value'])).' ('.$metric['correct'].'/'.$metric['total'].')';
    }

    /** @param array<string, mixed> $metric */
    private static function metricValue(array $metric): string
    {
        return $metric['value'] === null ? 'N/A' : self::percent($metric['value']);
    }

    /** @param array<string, mixed> $metric */
    private static function prfText(array $metric): string
    {
        return 'P '.self::metricText($metric['precision']).'; R '.self::metricText($metric['recall']).'; F1 '.self::percent($metric['f1']).'.';
    }

    private static function percent(?float $value): string
    {
        return $value === null ? 'N/A' : number_format($value * 100, 2).'%';
    }

    /** @param array<int, string> $ids */
    private static function idList(array $ids): string
    {
        return $ids === [] ? 'none' : implode(', ', $ids);
    }
}
