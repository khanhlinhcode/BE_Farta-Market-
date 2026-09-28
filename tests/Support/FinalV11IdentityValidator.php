<?php

namespace Tests\Support;

final class FinalV11IdentityValidator
{
    /** @param array<string, mixed> $metadata @param array<string, mixed> $manifest */
    public static function validate(array $metadata, array $manifest): void
    {
        $expected = $manifest['execution_identity'] ?? null;
        if (! is_array($expected) || array_is_list($expected)) {
            throw new FinalV11ContractException('Toolchain manifest execution_identity must be an object.');
        }
        foreach (['candidate_hash', 'dataset_hash', 'evaluator_hash', 'scorer_hash'] as $field) {
            if (! is_string($expected[$field] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $expected[$field])) {
                throw new FinalV11ContractException('Toolchain manifest '.$field.' must be SHA-256.');
            }
            if (! is_string($metadata[$field] ?? null) || ! hash_equals($expected[$field], $metadata[$field])) {
                throw new FinalV11ContractException('Execution identity mismatch: '.$field.'.');
            }
        }
    }
}
