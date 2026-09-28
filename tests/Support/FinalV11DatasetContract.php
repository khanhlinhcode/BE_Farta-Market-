<?php

namespace Tests\Support;

final class FinalV11DatasetContract
{
    public const SCHEMA_VERSION = 'farta-v10.3.0';

    /** @return array<string, mixed> */
    public static function load(string $path): array
    {
        return self::parse(json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * The frozen V11 dataset intentionally retains the validated farta-v10.3.0
     * structural schema. This adapter reuses that parser and adds V11-only
     * authority and scenario-grounding fields; scoring semantics live only in
     * the FinalV11 classes.
     *
     * @return array<string, mixed>
     */
    public static function parse(mixed $dataset): array
    {
        if (! is_array($dataset) || array_is_list($dataset)) {
            throw new FinalV11ContractException('Final V11 dataset must be a JSON object.');
        }

        $contract = FinalV10DatasetContract::parse($dataset);
        $contract['source_registry'] = $dataset['source_registry'];
        $contract['counts']['entity_objects'] = $contract['counts']['primary']
            + $contract['counts']['multi_intent_branches']
            + $contract['counts']['scenario_turns'];

        foreach ($contract['multi_turn_scenarios'] as $index => &$scenario) {
            $source = $dataset['multi_turn_scenarios'][$index];
            $grounding = $source['grounding_gold'] ?? [
                'minimum_facts_required' => [],
                'allowed_source_ids' => $source['allowed_evidence_sources'],
            ];
            $scenario['gold'] = [
                'intent' => $source['final_expected_intent'],
                'handler' => $source['expected_handler'],
                'terminal' => $source['expected_terminal_state'],
                'entities' => $source['turns'][array_key_last($source['turns'])]['entity_gold'],
                'required_evidence_domain' => $source['required_evidence_domain'],
                'allowed_evidence_sources' => $source['allowed_evidence_sources'],
                'minimum_facts_required' => $grounding['minimum_facts_required'],
                'security_decision' => $source['security_capability_expectation']['decision'],
                'security_capability' => $source['security_capability_expectation']['capability'],
            ];
        }
        unset($scenario);

        return $contract;
    }
}
