<?php

namespace Tests\Support;

final class FinalV11FactVerifier
{
    /** @param array<string, mixed> $dataset @return array<string, mixed> */
    public static function buildContract(array $dataset): array
    {
        $authorities = [];
        foreach ($dataset['source_registry'] as $source) {
            $authorities[] = [
                'source_id' => $source['source_id'],
                'authority_type' => $source['authority_type'],
                'version' => $source['version'] ?? null,
            ];
        }

        $records = [];
        foreach ($dataset['primary_cases'] as $primary) {
            self::appendRecordContract(
                $records,
                'primary',
                $primary['case_id'],
                $primary['expected_terminal_state'],
                $primary['required_evidence_domain'],
                $primary['allowed_evidence_sources'],
                $primary['allowed_evidence_source_versions'] ?? [],
                $primary['grounding_gold']['minimum_facts_required']
            );
            foreach ($primary['multi_intent_branches'] as $branch) {
                self::appendRecordContract(
                    $records,
                    'branch',
                    $primary['case_id'].'/'.$branch['branch_id'],
                    $branch['expected_terminal_state'],
                    $branch['required_evidence_domain'],
                    $branch['allowed_evidence_sources'],
                    $branch['allowed_evidence_source_versions'] ?? [],
                    $branch['minimum_facts_required']
                );
            }
        }
        $scenarioRecords = [];
        foreach ($dataset['multi_turn_scenarios'] as $scenario) {
            self::appendRecordContract(
                $scenarioRecords,
                'scenario',
                $scenario['scenario_id'],
                $scenario['expected_terminal_state'],
                $scenario['required_evidence_domain'],
                $scenario['allowed_evidence_sources'],
                $scenario['allowed_evidence_source_versions'] ?? [],
                $scenario['grounding_gold']['minimum_facts_required'] ?? []
            );
        }

        return [
            'contract_version' => 'farta-v11-fact-verification-contract.1.0',
            'dataset_sha256' => hash_file('sha256', base_path('docs/evaluation/v11-independent/v11-final-audited-dataset.json')),
            'claim_population' => [
                'scope' => 'primary_cases plus multi_intent_branches; scenario grounding is scored under follow_up',
                'eligible' => count($records),
                'excluded' => count($dataset['primary_cases'])
                    + array_sum(array_map(fn (array $case): int => count($case['multi_intent_branches']), $dataset['primary_cases']))
                    - count($records),
                'exclusion_reason' => 'minimum_facts_required is empty',
            ],
            'scenario_claim_population' => [
                'scope' => 'multi_turn_scenarios final response',
                'eligible' => count($scenarioRecords),
                'excluded' => count($dataset['multi_turn_scenarios']) - count($scenarioRecords),
                'exclusion_reason' => 'scenario grounding minimum_facts_required is empty',
            ],
            'rules' => [
                'comparison' => 'Every record-specific claim_id must be present with all required source IDs; accepted source IDs, versions, evidence domain, and terminal must match the frozen contract.',
                'topic_similarity' => 'never sufficient',
                'no_evidence' => 'NO_EVIDENCE with an empty eligible-source set is correct without fabricated evidence.',
                'wrong_topic_authority' => 'Any accepted source outside the record allowed_source_ids is wrong-topic authority.',
                'failure' => 'Missing, fabricated, duplicate, wrong-source, wrong-version, wrong-domain, or wrong-terminal evidence fails closed.',
            ],
            'authority_sources' => $authorities,
            'record_claim_contracts' => $records,
            'scenario_claim_contracts' => $scenarioRecords,
        ];
    }

    /**
     * @param  array<string, mixed>  $gold
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $contract
     * @return array<string, mixed>
     */
    public static function verify(string $recordId, array $gold, array $prediction, array $contract): array
    {
        $allowed = self::stringList($gold['allowed_evidence_sources'] ?? null, 'gold.allowed_evidence_sources');
        $accepted = self::stringList($prediction['accepted_source_ids'] ?? null, 'prediction.accepted_source_ids');
        $unexpected = array_values(array_diff($accepted, $allowed));
        $answerLike = in_array($prediction['terminal'] ?? null, [
            'ANSWER', 'ANSWER_OR_NOT_FOUND', 'NO_RESULTS', 'SUGGESTED_ACTION', 'UNAVAILABLE',
        ], true);
        $needsEvidence = $allowed !== [] && $answerLike;
        $eligibility = $unexpected === [] && (! $needsEvidence || $accepted !== []);
        $facts = self::stringList($gold['minimum_facts_required'] ?? null, 'gold.minimum_facts_required');
        $authorityVersions = [];
        foreach ($contract['authority_sources'] ?? [] as $source) {
            if (is_array($source) && is_string($source['source_id'] ?? null)) {
                $authorityVersions[$source['source_id']] = $source['version'] ?? null;
            }
        }
        self::assertVersions($accepted, $prediction['accepted_source_versions'] ?? null, $authorityVersions);

        if ($facts === []) {
            $hallucination = ($gold['terminal'] ?? null) === 'NO_EVIDENCE'
                && ($prediction['terminal'] ?? null) !== 'NO_EVIDENCE';

            return [
                'applicable' => false,
                'claim_support' => null,
                'evidence_eligibility' => $eligibility,
                'wrong_topic_authority' => $unexpected !== [],
                'unexpected_source_ids' => $unexpected,
                'missing_claim_ids' => [],
                'fabricated_claim_ids' => [],
                'unsupported_policy_hallucination' => $hallucination,
            ];
        }

        $records = $contract['record_claim_contracts'] ?? null;
        $scenarioRecords = $contract['scenario_claim_contracts'] ?? null;
        if (! is_array($records) || ! array_is_list($records)
            || ! is_array($scenarioRecords) || ! array_is_list($scenarioRecords)) {
            throw new FinalV11ContractException('Fact contract record_claim_contracts must be an array.');
        }
        $record = collect([...$records, ...$scenarioRecords])->first(
            fn (mixed $value): bool => is_array($value) && ($value['record_id'] ?? null) === $recordId
        );
        if (! is_array($record)) {
            throw new FinalV11ContractException('Missing fact-verification contract for '.$recordId.'.');
        }
        self::assertGoldContract($recordId, $gold, $record, $facts, $allowed);
        self::assertVersions($accepted, $prediction['accepted_source_versions'] ?? null, $record['allowed_source_versions'] ?? null);

        $evidence = $prediction['claim_evidence'] ?? null;
        if (! is_array($evidence) || ! array_is_list($evidence)) {
            throw new FinalV11ContractException('prediction.claim_evidence must be an array.');
        }
        $provided = [];
        foreach ($evidence as $index => $item) {
            if (! is_array($item) || array_is_list($item) || ! is_string($item['claim_id'] ?? null)) {
                throw new FinalV11ContractException('prediction.claim_evidence['.$index.'] must be an object with claim_id.');
            }
            if (isset($provided[$item['claim_id']])) {
                throw new FinalV11ContractException('Duplicate claim evidence ID '.$item['claim_id'].'.');
            }
            $provided[$item['claim_id']] = self::stringList(
                $item['source_ids'] ?? null,
                'prediction.claim_evidence['.$index.'].source_ids'
            );
        }

        $expectedIds = [];
        $missing = [];
        foreach ($record['claims'] as $claim) {
            $claimId = $claim['claim_id'];
            $expectedIds[] = $claimId;
            $sources = $provided[$claimId] ?? null;
            $required = self::stringList($claim['required_source_ids'] ?? null, 'fact_contract.required_source_ids');
            if ($sources === null || array_diff($required, $sources) !== [] || array_diff($sources, $accepted) !== []) {
                $missing[] = $claimId;
            }
        }
        $fabricated = array_values(array_diff(array_keys($provided), $expectedIds));
        $supported = $eligibility
            && $missing === []
            && $fabricated === []
            && ($gold['required_evidence_domain'] ?? null) === ($prediction['required_evidence_domain'] ?? null)
            && ($gold['terminal'] ?? null) === ($prediction['terminal'] ?? null);

        return [
            'applicable' => true,
            'claim_support' => $supported,
            'evidence_eligibility' => $eligibility,
            'wrong_topic_authority' => $unexpected !== [],
            'unexpected_source_ids' => $unexpected,
            'missing_claim_ids' => $missing,
            'fabricated_claim_ids' => $fabricated,
            'unsupported_policy_hallucination' => $answerLike && ! $supported,
        ];
    }

    /** @return array<int, string> */
    private static function stringList(mixed $value, string $path): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new FinalV11ContractException($path.' must be an array.');
        }
        $seen = [];
        foreach ($value as $index => $item) {
            if (! is_string($item) || $item === '') {
                throw new FinalV11ContractException($path.'['.$index.'] must be a non-empty string.');
            }
            if (isset($seen[$item])) {
                throw new FinalV11ContractException($path.' contains duplicate '.$item.'.');
            }
            $seen[$item] = true;
        }

        return $value;
    }

    /** @param array<int, string> $facts @param array<int, string> $allowed */
    private static function assertGoldContract(
        string $recordId,
        array $gold,
        array $record,
        array $facts,
        array $allowed
    ): void {
        $contractFacts = array_map(fn (array $claim): mixed => $claim['fact'] ?? null, $record['claims'] ?? []);
        if (($record['terminal'] ?? null) !== ($gold['terminal'] ?? null)
            || ($record['required_evidence_domain'] ?? null) !== ($gold['required_evidence_domain'] ?? null)
            || ($record['allowed_source_ids'] ?? null) !== $allowed
            || $contractFacts !== $facts) {
            throw new FinalV11ContractException('Fact contract drift detected for '.$recordId.'.');
        }
    }

    /** @param array<int, string> $accepted */
    private static function assertVersions(array $accepted, mixed $actual, mixed $allowed): void
    {
        if (! is_array($actual) || ($actual !== [] && array_is_list($actual))
            || ! is_array($allowed) || ($allowed !== [] && array_is_list($allowed))) {
            throw new FinalV11ContractException('Accepted and allowed source versions must be objects.');
        }
        foreach ($accepted as $sourceId) {
            if (! array_key_exists($sourceId, $allowed)) {
                continue;
            }
            if (! array_key_exists($sourceId, $actual)
                || ! is_int($actual[$sourceId])
                || $actual[$sourceId] !== $allowed[$sourceId]) {
                throw new FinalV11ContractException('Source version mismatch for '.$sourceId.'.');
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<int, string>  $allowedSources
     * @param  array<string, int>  $allowedVersions
     * @param  array<int, string>  $facts
     */
    private static function appendRecordContract(
        array &$records,
        string $scope,
        string $recordId,
        string $terminal,
        ?string $domain,
        array $allowedSources,
        array $allowedVersions,
        array $facts
    ): void {
        if ($facts === []) {
            return;
        }
        $claims = [];
        foreach ($facts as $fact) {
            $claims[] = [
                'claim_id' => 'sha256:'.hash('sha256', $recordId."\0".$fact),
                'fact' => $fact,
                'required_source_ids' => self::requiredSources($domain, $allowedSources, $fact),
            ];
        }
        $records[] = [
            'record_id' => $recordId,
            'scope' => $scope,
            'terminal' => $terminal,
            'required_evidence_domain' => $domain,
            'allowed_source_ids' => $allowedSources,
            'allowed_source_versions' => $allowedVersions,
            'claims' => $claims,
        ];
    }

    /** @param array<int, string> $allowedSources @return array<int, string> */
    private static function requiredSources(?string $domain, array $allowedSources, string $fact): array
    {
        if (count($allowedSources) < 2) {
            return $allowedSources;
        }
        $normalized = strtolower($fact);
        if ($domain === 'product_inventory_and_ordering_contract') {
            return str_contains($normalized, 'chatbot')
                || str_contains($normalized, 'suggestion')
                || str_contains($normalized, 'confirm')
                ? ['shopping-guide-vi']
                : ['product-category-authority-v11'];
        }
        if ($domain === 'product_price_and_shipping_settings') {
            return str_contains($normalized, 'shipping') || str_contains($normalized, 'final total')
                ? $allowedSources
                : ['product-category-authority-v11'];
        }

        return $allowedSources;
    }
}
