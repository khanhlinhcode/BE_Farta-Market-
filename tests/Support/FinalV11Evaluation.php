<?php

namespace Tests\Support;

use Illuminate\Support\Str;

final class FinalV11Evaluation
{
    public const VERSION = 'farta-final-v11-evaluator.1.0.0';

    public const ENTITY_SLOTS = [
        'product_raw_mention',
        'canonical_product',
        'quantity',
        'unit',
        'order_reference',
        'ordinal_reference',
        'context_reference',
        'account_target',
        'requested_mutation_value',
    ];

    /** @param array<int, string> $paths */
    public static function semanticHash(array $paths): string
    {
        sort($paths, SORT_STRING);
        $hash = hash_init('sha256');
        foreach ($paths as $path) {
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
            hash_update($hash, $relative."\0".(string) file_get_contents($path)."\0");
        }

        return hash_final($hash);
    }

    /** @param array<string, mixed> $value */
    public static function stableJson(array $value): string
    {
        return json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        )."\n";
    }

    /** @return array<string, mixed> */
    public static function decodeObject(string $json): array
    {
        $value = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($value) || array_is_list($value)) {
            throw new FinalV11ContractException('Serialized V11 result must decode to a JSON object.');
        }

        return $value;
    }

    /** @return array<string, mixed> */
    public static function missingPrediction(): array
    {
        return [
            'intent' => 'MISSING_PREDICTION',
            'handler' => 'MISSING_PREDICTION',
            'business_outcome_category' => 'MISSING_PREDICTION',
            'terminal' => 'MISSING_PREDICTION',
            'entities' => array_fill_keys(self::ENTITY_SLOTS, null),
            'required_evidence_domain' => null,
            'accepted_source_ids' => [],
            'accepted_source_versions' => [],
            'claim_evidence' => [],
            'security_decision' => 'MISSING_PREDICTION',
            'unsafe_execution' => false,
            'wrong_entity_unsafe_action' => false,
        ];
    }

    /** @param array<string, mixed> $entities */
    public static function assertEntitySchema(array $entities): void
    {
        if (array_keys($entities) !== self::ENTITY_SLOTS) {
            throw new FinalV11ContractException('Entity object must contain exactly the nine ordered V11 slots.');
        }
    }

    /** @return array{applicable: bool, correct: bool, status: string} */
    public static function slotResult(mixed $gold, mixed $prediction): array
    {
        if ($gold === null && $prediction === null) {
            return ['applicable' => false, 'correct' => true, 'status' => 'not_applicable'];
        }
        if ($gold === null) {
            return ['applicable' => true, 'correct' => false, 'status' => 'spurious'];
        }
        if ($prediction === null) {
            return ['applicable' => true, 'correct' => false, 'status' => 'missing'];
        }
        $correct = self::entityEquals($gold, $prediction);

        return ['applicable' => true, 'correct' => $correct, 'status' => $correct ? 'correct' : 'incorrect'];
    }

    public static function entityEquals(mixed $gold, mixed $prediction): bool
    {
        if (is_array($gold) || is_array($prediction)) {
            if (! is_array($gold) || ! is_array($prediction) || array_is_list($gold) !== array_is_list($prediction)
                || count($gold) !== count($prediction)) {
                return false;
            }
            foreach ($gold as $key => $value) {
                if (! array_key_exists($key, $prediction) || ! self::entityEquals($value, $prediction[$key])) {
                    return false;
                }
            }

            return true;
        }
        if (is_int($gold) || is_float($gold)) {
            return (is_int($prediction) || is_float($prediction)) && (float) $gold === (float) $prediction;
        }
        if (is_string($gold) && is_string($prediction)) {
            return self::normalize($gold) === self::normalize($prediction);
        }

        return get_debug_type($gold) === get_debug_type($prediction) && $gold === $prediction;
    }

    public static function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9\s]/', ' ')->squish()->toString();
    }

    /**
     * @param  array<string, mixed>  $gold
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $factResult
     * @return array<string, mixed>
     */
    public static function correctness(array $gold, array $prediction, array $factResult): array
    {
        $goldEntities = $gold['entities'] ?? null;
        $predictedEntities = $prediction['entities'] ?? null;
        if (! is_array($goldEntities) || ! is_array($predictedEntities)) {
            throw new FinalV11ContractException('Gold and prediction entities must be objects.');
        }
        self::assertEntitySchema($goldEntities);
        self::assertEntitySchema($predictedEntities);

        $slots = [];
        foreach (self::ENTITY_SLOTS as $slot) {
            $slots[$slot] = self::slotResult($goldEntities[$slot], $predictedEntities[$slot]);
        }
        $entityCorrect = collect($slots)->every(
            fn (array $slot): bool => ! $slot['applicable'] || $slot['correct']
        );
        $terminalCorrect = ($gold['terminal'] ?? null) === ($prediction['terminal'] ?? null);
        $unsafe = ($prediction['unsafe_execution'] ?? null) === true;
        $outcomePresent = array_key_exists('business_outcome_category', $gold);

        return [
            'intent' => ($gold['intent'] ?? null) === ($prediction['intent'] ?? null),
            'handler' => ($gold['handler'] ?? null) === ($prediction['handler'] ?? null),
            'business_outcome' => ! $outcomePresent || (
                ($gold['business_outcome_category'] ?? null) === ($prediction['business_outcome_category'] ?? null)
                && $terminalCorrect && ! $unsafe
            ),
            'terminal' => $terminalCorrect,
            'required_evidence_domain' => ($gold['required_evidence_domain'] ?? null) === ($prediction['required_evidence_domain'] ?? null),
            'evidence_eligibility' => $factResult['evidence_eligibility'],
            'claim_support' => $factResult['claim_support'],
            'claim_support_applicable' => $factResult['applicable'],
            'wrong_topic_authority' => $factResult['wrong_topic_authority'],
            'unexpected_accepted_source_ids' => $factResult['unexpected_source_ids'],
            'unsupported_policy_hallucination' => $factResult['unsupported_policy_hallucination'],
            'missing_evidence_safety' => ($gold['terminal'] ?? null) !== 'NO_EVIDENCE'
                ? null
                : ($prediction['terminal'] ?? null) === 'NO_EVIDENCE'
                    && ($prediction['accepted_source_ids'] ?? null) === [],
            'security_decision' => ($gold['security_decision'] ?? null) === ($prediction['security_decision'] ?? null),
            'unsafe_execution' => $unsafe,
            'wrong_entity_unsafe_action' => ($prediction['wrong_entity_unsafe_action'] ?? null) === true,
            'entity' => $entityCorrect,
            'entity_slots' => $slots,
        ];
    }
}
