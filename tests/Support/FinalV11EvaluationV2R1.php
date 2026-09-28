<?php

namespace Tests\Support;

final class FinalV11EvaluationV2R1
{
    public const VERSION = 'farta-final-v11-evaluator.2.0.1';

    public const SCOPE_PARENT = 'PARENT';

    public const SCOPE_BRANCH = 'BRANCH';

    public const PREDICTION_FIELDS = FinalV11EvaluationV2::PREDICTION_FIELDS;

    /** @param array<string, mixed> $value */
    public static function canonicalJson(array $value): string
    {
        return FinalV11EvaluationV2::canonicalJson($value);
    }

    /** @param array<string, mixed> $prediction */
    public static function assertPredictionSchema(array $prediction, ?string $scope = null): void
    {
        if (! in_array($scope, [self::SCOPE_PARENT, self::SCOPE_BRANCH], true)) {
            throw new FinalV11ContractException('V2-r1 prediction validation requires explicit PARENT or BRANCH scope.');
        }

        if ($scope === self::SCOPE_PARENT) {
            FinalV11EvaluationV2::assertPredictionSchema($prediction);

            return;
        }

        $decision = $prediction['security_decision'] ?? null;
        if (! is_string($decision) || ! in_array($decision, self::branchSecurityDecisions(), true)) {
            throw new FinalV11ContractException('V2-r1 branch prediction security_decision contains an unknown enum value.');
        }

        // The frozen V2 validator still owns every non-security schema rule.
        // Substitute a frozen parent value only for that compatibility check;
        // the original branch value is validated above and is never mutated.
        $structuralProbe = $prediction;
        $structuralProbe['security_decision'] = 'DENY';
        FinalV11EvaluationV2::assertPredictionSchema($structuralProbe);
    }

    /** @return array<int, string> */
    public static function branchSecurityDecisions(): array
    {
        static $values;

        if (is_array($values)) {
            return $values;
        }
        $contract = json_decode(
            (string) file_get_contents(base_path('docs/evaluation/v11-independent/v11-sanitized-schema-contract.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $values = $contract['enum_definitions']['branch_security_expectation']['values'] ?? null;
        if (! is_array($values) || ! array_is_list($values)) {
            throw new FinalV11ContractException('Missing frozen branch security enum.');
        }

        return $values;
    }
}
