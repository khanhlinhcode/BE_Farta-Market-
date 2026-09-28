<?php

namespace Tests\Support;

final class FinalV11PerfectMirror
{
    /**
     * @param  array<string, mixed>  $contract
     * @param  array<string, mixed>  $factContract
     * @return array<string, mixed>
     */
    public static function capture(array $contract, array $factContract): array
    {
        $primary = [];
        foreach ($contract['primary_cases'] as $record) {
            $branches = [];
            foreach ($record['branches'] as $branch) {
                $id = $record['case_id'].'/'.$branch['branch_id'];
                $branches[] = [
                    'branch_id' => $branch['branch_id'],
                    'prediction' => self::prediction($id, $branch['gold'], $factContract),
                    'state_changed' => false,
                ];
            }
            $primary[] = [
                'case_id' => $record['case_id'],
                'prediction' => self::prediction($record['case_id'], $record['gold'], $factContract),
                'branches' => $branches,
                'raw_response' => ['mirror' => true],
                'http_status' => 200,
                'runtime_error' => null,
                'latency_ms' => ['router' => 0.0, 'total_request' => 0.0],
                'state_changed' => false,
            ];
        }

        $scenarioTurns = [];
        foreach ($contract['multi_turn_scenarios'] as $scenario) {
            $lastTurn = count($scenario['turns']);
            foreach ($scenario['turns'] as $turn) {
                $gold = $turn['gold'];
                if ($turn['turn'] === $lastTurn) {
                    $gold['required_evidence_domain'] = $scenario['gold']['required_evidence_domain'];
                    $gold['allowed_evidence_sources'] = $scenario['gold']['allowed_evidence_sources'];
                    $gold['minimum_facts_required'] = $scenario['gold']['minimum_facts_required'];
                    $gold['security_decision'] = $scenario['gold']['security_decision'];
                }
                $scenarioTurns[] = [
                    'scenario_id' => $scenario['scenario_id'],
                    'turn' => $turn['turn'],
                    'prediction' => self::prediction(
                        $turn['turn'] === $lastTurn ? $scenario['scenario_id'] : '',
                        $gold,
                        $factContract
                    ),
                    'raw_response' => ['mirror' => true],
                    'http_status' => 200,
                    'runtime_error' => null,
                    'latency_ms' => ['total_request' => 0.0],
                    'state_changed' => false,
                ];
            }
        }

        return [
            'schema_version' => FinalV11RawCapture::SCHEMA_VERSION,
            'metadata' => [
                'run_id' => 'v11-perfect-gold-mirror',
                'candidate_executed' => false,
                'dataset_hash' => hash_file('sha256', base_path('docs/evaluation/v11-independent/v11-final-audited-dataset.json')),
            ],
            'primary_cases' => $primary,
            'scenario_turns' => $scenarioTurns,
        ];
    }

    /**
     * @param  array<string, mixed>  $gold
     * @param  array<string, mixed>  $factContract
     * @return array<string, mixed>
     */
    public static function prediction(string $recordId, array $gold, array $factContract): array
    {
        $prediction = FinalV11Evaluation::missingPrediction();
        foreach (['intent', 'handler', 'business_outcome_category', 'terminal', 'entities', 'required_evidence_domain', 'security_decision'] as $field) {
            if (array_key_exists($field, $gold)) {
                $prediction[$field] = $gold[$field];
            }
        }
        $prediction['accepted_source_ids'] = $gold['allowed_evidence_sources'];
        $versions = collect($factContract['authority_sources'])
            ->mapWithKeys(fn (array $source): array => [$source['source_id'] => $source['version']])
            ->all();
        $prediction['accepted_source_versions'] = [];
        foreach ($prediction['accepted_source_ids'] as $sourceId) {
            $prediction['accepted_source_versions'][$sourceId] = $versions[$sourceId];
        }

        $records = [...$factContract['record_claim_contracts'], ...$factContract['scenario_claim_contracts']];
        $record = collect($records)->firstWhere('record_id', $recordId);
        $prediction['claim_evidence'] = is_array($record)
            ? array_map(fn (array $claim): array => [
                'claim_id' => $claim['claim_id'],
                'source_ids' => $claim['required_source_ids'],
            ], $record['claims'])
            : [];

        return $prediction;
    }
}
