<?php

namespace Tests\Support;

final class FinalV11EvaluationV2
{
    public const VERSION = 'farta-final-v11-evaluator.2.0.0';

    public const PREDICTION_FIELDS = [
        'intent',
        'handler',
        'business_outcome_category',
        'terminal',
        'entities',
        'required_evidence_domain',
        'accepted_source_ids',
        'accepted_source_versions',
        'claim_evidence',
        'security_decision',
        'unsafe_execution',
        'wrong_entity_unsafe_action',
    ];

    /** @param array<string, mixed> $value */
    public static function canonicalJson(array $value): string
    {
        return json_encode(
            self::canonicalize($value),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        )."\n";
    }

    /** @param array<string, mixed> $prediction */
    public static function assertPredictionSchema(array $prediction): void
    {
        if (array_keys($prediction) !== self::PREDICTION_FIELDS) {
            throw new FinalV11ContractException('V2 prediction fields do not match the frozen ordered schema.');
        }

        $enums = self::enums();
        foreach ([
            'intent' => 'primary_intent',
            'handler' => 'handler',
            'business_outcome_category' => 'business_outcome_category',
            'terminal' => 'terminal',
            'security_decision' => 'security_decision',
        ] as $field => $enum) {
            if (! is_string($prediction[$field]) || ! in_array($prediction[$field], $enums[$enum], true)) {
                throw new FinalV11ContractException('V2 prediction '.$field.' contains an unknown enum value.');
            }
        }

        if (! is_array($prediction['entities']) || array_is_list($prediction['entities'])) {
            throw new FinalV11ContractException('V2 prediction entities must be an object.');
        }
        FinalV11Evaluation::assertEntitySchema($prediction['entities']);

        $domain = $prediction['required_evidence_domain'];
        if ($domain !== null && (! is_string($domain) || ! in_array($domain, $enums['evidence_domain'], true))) {
            throw new FinalV11ContractException('V2 prediction required_evidence_domain contains an unknown enum value.');
        }

        self::assertStringList($prediction['accepted_source_ids'], 'accepted_source_ids');
        $versions = $prediction['accepted_source_versions'];
        if (! is_array($versions) || ($versions !== [] && array_is_list($versions))) {
            throw new FinalV11ContractException('V2 accepted_source_versions must be an object.');
        }
        if (array_keys($versions) !== $prediction['accepted_source_ids']) {
            throw new FinalV11ContractException('V2 accepted source IDs and source-version keys must match exactly.');
        }
        foreach ($versions as $sourceId => $version) {
            if (! is_string($sourceId) || ! is_int($version) || $version < 1) {
                throw new FinalV11ContractException('V2 source versions must be positive integers.');
            }
        }

        $evidence = $prediction['claim_evidence'];
        if (! is_array($evidence) || ! array_is_list($evidence)) {
            throw new FinalV11ContractException('V2 claim_evidence must be an array.');
        }
        $claimIds = [];
        foreach ($evidence as $index => $claim) {
            if (! is_array($claim) || array_is_list($claim)
                || array_keys($claim) !== ['claim_id', 'source_ids']
                || ! is_string($claim['claim_id'] ?? null)
                || preg_match('/^sha256:[a-f0-9]{64}$/', $claim['claim_id']) !== 1) {
                throw new FinalV11ContractException('V2 claim_evidence['.$index.'] has an invalid shape or claim ID.');
            }
            if (isset($claimIds[$claim['claim_id']])) {
                throw new FinalV11ContractException('V2 claim evidence contains a duplicate claim ID.');
            }
            $claimIds[$claim['claim_id']] = true;
            self::assertStringList($claim['source_ids'], 'claim_evidence['.$index.'].source_ids');
            if (array_diff($claim['source_ids'], $prediction['accepted_source_ids']) !== []) {
                throw new FinalV11ContractException('V2 claim evidence references an unaccepted source.');
            }
        }

        foreach (['unsafe_execution', 'wrong_entity_unsafe_action'] as $field) {
            if (! is_bool($prediction[$field])) {
                throw new FinalV11ContractException('V2 prediction '.$field.' must be boolean.');
            }
        }
    }

    /** @return array<string, array<int, string>> */
    private static function enums(): array
    {
        static $enums;

        if (is_array($enums)) {
            return $enums;
        }
        $contract = json_decode(
            (string) file_get_contents(base_path('docs/evaluation/v11-independent/v11-sanitized-schema-contract.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $definitions = $contract['enum_definitions'] ?? null;
        if (! is_array($definitions)) {
            throw new FinalV11ContractException('Missing frozen sanitized enum definitions.');
        }
        foreach (['primary_intent', 'handler', 'business_outcome_category', 'terminal', 'security_decision', 'evidence_domain'] as $name) {
            $values = $definitions[$name]['values'] ?? null;
            if (! is_array($values) || ! array_is_list($values)) {
                throw new FinalV11ContractException('Missing frozen enum '.$name.'.');
            }
            $enums[$name] = $values;
        }

        return $enums;
    }

    private static function assertStringList(mixed $value, string $path): void
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new FinalV11ContractException('V2 '.$path.' must be an array.');
        }
        $seen = [];
        foreach ($value as $item) {
            if (! is_string($item) || $item === '' || isset($seen[$item])) {
                throw new FinalV11ContractException('V2 '.$path.' must contain unique non-empty strings.');
            }
            $seen[$item] = true;
        }
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(self::canonicalize(...), $value);
        }
        ksort($value, SORT_STRING);

        return array_map(self::canonicalize(...), $value);
    }
}
