<?php

namespace Tests\Support;

final class FinalV10PerfectMirror
{
    /** @param array<string, mixed> $contract @return array<string, mixed> */
    public static function capture(array $contract): array
    {
        $primary = [];
        foreach ($contract['primary_cases'] as $record) {
            $branches = [];
            foreach ($record['branches'] as $branch) {
                $branches[] = [
                    'branch_id' => $branch['branch_id'],
                    'prediction' => self::prediction($branch['gold'], 0),
                    'state_changed' => false,
                ];
            }
            $primary[] = [
                'case_id' => $record['case_id'],
                'prediction' => self::prediction($record['gold'], count($record['branches'])),
                'branches' => $branches,
                'raw_response' => ['mirror' => true, 'terminal' => $record['gold']['terminal']],
                'http_status' => 200,
                'runtime_error' => null,
                'latency_ms' => ['router' => 0.0, 'total_request' => 0.0],
                'state_changed' => false,
            ];
        }

        $scenarioTurns = [];
        foreach ($contract['multi_turn_scenarios'] as $scenario) {
            foreach ($scenario['turns'] as $turn) {
                $prediction = self::prediction($turn['gold'], 0);
                if ($turn['turn'] === count($scenario['turns'])) {
                    $prediction['entities']['canonical_product'] = $scenario['final_canonical_entity'];
                }
                $scenarioTurns[] = [
                    'scenario_id' => $scenario['scenario_id'],
                    'turn' => $turn['turn'],
                    'prediction' => $prediction,
                    'raw_response' => ['mirror' => true, 'terminal' => $turn['gold']['terminal']],
                    'http_status' => 200,
                    'runtime_error' => null,
                    'latency_ms' => ['total_request' => 0.0],
                    'state_changed' => false,
                ];
            }
        }

        return [
            'schema_version' => FinalV10RawCapture::SCHEMA_VERSION,
            'metadata' => [
                'run_id' => 'evaluator-v3-perfect-mirror',
                'candidate_executed' => false,
                'dataset_hash' => FinalV10Evaluation::DATASET_SHA256,
                'candidate_hash' => FinalV10Evaluation::CANDIDATE_SHA256,
            ],
            'primary_cases' => $primary,
            'scenario_turns' => $scenarioTurns,
        ];
    }

    /** @param array<string, mixed> $gold @return array<string, mixed> */
    public static function prediction(array $gold, int $expectedBranchCount = 0): array
    {
        $products = [];
        $actions = [];
        $citations = [];
        $messageParts = [];
        foreach ($gold['minimum_facts_required'] as $fact) {
            $messageParts[] = $fact;
            $section = FinalV10Evaluation::citationSectionForFact($fact);
            if ($section !== null) {
                $citations[] = ['source_id' => $gold['allowed_evidence_sources'][0] ?? 'SYNTHETIC_MIRROR', 'section' => $section];
            }
            if (in_array($fact, FinalV10Evaluation::productNames(), true)) {
                $products[] = ['name' => $fact, 'is_active' => true];
            }
            if (preg_match('/^(?:price_vnd|unit_price_vnd)=(\d+)$/', $fact, $match) === 1) {
                $products[] = ['name' => 'Synthetic mirror', 'price' => (int) $match[1], 'is_active' => true];
            }
            if (preg_match('/^inventory=(\d+)$/', $fact, $match) === 1) {
                $products[] = ['name' => 'Synthetic mirror', 'inventory' => (int) $match[1], 'is_active' => true];
            }
            if (preg_match('/^quantity=(\d+)$/', $fact, $match) === 1) {
                $actions[] = ['quantity' => (int) $match[1]];
            }
            if (preg_match('/^category=(.+)$/u', $fact, $match) === 1) {
                $products[] = ['name' => 'Synthetic mirror', 'category' => ['name' => $match[1]], 'is_active' => true];
            }
        }
        if (in_array('claims must not exceed the stored product description', $gold['minimum_facts_required'], true)
            || in_array('return only active catalog products matching the requested scope', $gold['minimum_facts_required'], true)) {
            $products[] = ['name' => 'Synthetic mirror', 'is_active' => true];
        }
        if (in_array('199999 is below threshold', $gold['minimum_facts_required'], true)
            || in_array('subtotal at or above threshold qualifies', $gold['minimum_facts_required'], true)) {
            $messageParts[] = '200000 threshold';
        }
        if ($gold['terminal'] === 'NO_RESULTS') {
            $products = [];
        }

        $prediction = [
            'intent' => $gold['intent'],
            'handler' => $gold['handler'],
            'terminal' => $gold['terminal'],
            'entities' => $gold['entities'],
            'required_evidence_domain' => $gold['required_evidence_domain'],
            'accepted_source_ids' => $gold['allowed_evidence_sources'],
            'retrieved_source_ids' => null,
            'security_decision' => $gold['security_decision'],
            'message' => implode(' | ', $messageParts),
            'products' => $products,
            'suggested_actions' => $actions,
            'citations' => $citations,
            'action_type' => 'none',
            'unsafe_execution' => false,
            'wrong_entity_unsafe_action' => 0,
            'order_owned_by_actor' => true,
            'branch_count' => $expectedBranchCount,
            'expected_branch_count' => $expectedBranchCount,
        ];
        if (! FinalV10Evaluation::factsSatisfied($gold['minimum_facts_required'], $prediction)) {
            throw new FinalV10ContractException('Perfect mirror could not satisfy frozen facts for '.$gold['intent'].'/'.$gold['terminal']);
        }

        return $prediction;
    }
}
