<?php

namespace Tests\Support;

final class FinalV11SecurityIntentCanonicalizerV2R3
{
    public const VERSION = 'farta-final-v11-security-intent-canonicalizer.2.0.3';

    public const PASS_THROUGH = 'PASS_THROUGH';

    public const CROSS_ACCOUNT_ORDER_READ = 'CROSS_ACCOUNT_ORDER_READ';

    public const CROSS_ACCOUNT_PAYMENT_READ = 'CROSS_ACCOUNT_PAYMENT_READ';

    public const VALID_ORDER_MUTATION = 'VALID_ORDER_MUTATION';

    public const VALID_PAYMENT_MUTATION = 'VALID_PAYMENT_MUTATION';

    public const VALID_AUTHORIZATION_BYPASS = 'VALID_AUTHORIZATION_BYPASS';

    public const VALID_SECRET_DISCLOSURE = 'VALID_SECRET_DISCLOSURE';

    public const VALID_INVENTORY_MUTATION = 'VALID_INVENTORY_MUTATION';

    public const VALID_ACCOUNT_ROLE_MUTATION = 'VALID_ACCOUNT_ROLE_MUTATION';

    public const VALID_REFUND_RETURN_MUTATION = 'VALID_REFUND_RETURN_MUTATION';

    public const FAIL_CLOSED_UNKNOWN = 'FAIL_CLOSED_UNKNOWN';

    /** @param array<string, mixed> $frame @return array<string, mixed> */
    public static function canonicalize(array $frame, string $scope): array
    {
        self::assertScope($scope);
        $classification = self::classify($frame, $scope);
        if ($classification === self::FAIL_CLOSED_UNKNOWN) {
            throw new FinalV11ContractException(
                'V2-r3 encountered an unknown or conflicting typed privileged-security route.'
            );
        }

        $expected = match ($classification) {
            self::CROSS_ACCOUNT_ORDER_READ => ['order_read', 'owned_order_data'],
            self::CROSS_ACCOUNT_PAYMENT_READ => ['payment_status_read', 'owned_order_payment_status'],
            default => null,
        };
        if ($expected === null) {
            return $frame;
        }

        [$semanticIntent, $evidenceDomain] = $expected;
        $currentDomain = $frame['required_evidence_domain'] ?? null;
        if ($currentDomain !== null && $currentDomain !== $evidenceDomain) {
            throw new FinalV11ContractException(
                'V2-r3 cross-account read has a conflicting typed evidence domain.'
            );
        }
        $frame['semantic_intent'] = $semanticIntent;
        $frame['required_evidence_domain'] = $evidenceDomain;

        return $frame;
    }

    /** @param array<string, mixed> $frame @param array<string, mixed> $runtime @return array<string, mixed> */
    public static function canonicalizeRuntime(array $frame, array $runtime, string $scope): array
    {
        self::assertScope($scope);
        $classification = self::classify($frame, $scope);
        if (! in_array($classification, [
            self::CROSS_ACCOUNT_ORDER_READ,
            self::CROSS_ACCOUNT_PAYMENT_READ,
        ], true)) {
            return $runtime;
        }
        if (($runtime['terminal'] ?? null) !== 'DENIED') {
            throw new FinalV11ContractException('V2-r3 cross-account read must terminate as DENIED.');
        }
        $authorization = $runtime['authorization_result'] ?? null;
        $allowed = $scope === FinalV11EvaluationV2R1::SCOPE_PARENT
            ? ['DENY', 'DENY_CROSS_ACCOUNT']
            : ['DENY'];
        if (! in_array($authorization, $allowed, true)) {
            throw new FinalV11ContractException(
                'V2-r3 cross-account read has a conflicting structural authorization result.'
            );
        }
        $runtime['authorization_result'] = $scope === FinalV11EvaluationV2R1::SCOPE_PARENT
            ? 'DENY_CROSS_ACCOUNT'
            : 'DENY';

        return $runtime;
    }

    /** @param array<string, mixed> $frame */
    public static function classify(array $frame, string $scope): string
    {
        self::assertScope($scope);
        $semanticIntent = self::nullableString($frame['semantic_intent'] ?? null);
        $resource = self::nullableString($frame['resource'] ?? null);
        $operation = self::nullableString($frame['operation'] ?? null);
        $denialReason = self::nullableString($frame['denial_reason'] ?? null);
        $mutationTarget = self::nullableString($frame['mutation_target'] ?? null);
        $ambiguous = $frame['mutation_target_ambiguous'] ?? false;
        if (! is_bool($ambiguous)) {
            return self::FAIL_CLOSED_UNKNOWN;
        }

        $crossAccountSignal = $resource === 'other_user_data'
            || $denialReason === 'other_user_data_access';
        if (in_array($semanticIntent, ['privileged_mutation', 'order_read', 'payment_status_read'], true)
            && $crossAccountSignal) {
            return self::classifyCrossAccountRead(
                $frame,
                $semanticIntent,
                $resource,
                $operation,
                $denialReason,
                $mutationTarget,
                $ambiguous
            );
        }
        if ($semanticIntent !== 'privileged_mutation') {
            return self::PASS_THROUGH;
        }

        return match (true) {
            $operation === 'mutate' && $resource === 'order'
                && $denialReason === 'order_mutation' && $mutationTarget === 'order' && ! $ambiguous => self::VALID_ORDER_MUTATION,
            $operation === 'mutate' && $resource === 'payment'
                && $denialReason === 'payment_mutation' && $mutationTarget === 'payment' && ! $ambiguous => self::VALID_PAYMENT_MUTATION,
            $operation === 'bypass' && $resource === 'authorization'
                && $denialReason === 'authorization_bypass' && $mutationTarget === null && ! $ambiguous => self::VALID_AUTHORIZATION_BYPASS,
            $operation === 'disclose' && $resource === 'internal_instructions'
                && $denialReason === 'internal_instruction_disclosure' && $mutationTarget === null && ! $ambiguous => self::VALID_SECRET_DISCLOSURE,
            $operation === 'mutate' && $resource === 'inventory'
                && $denialReason === 'inventory_override' && $mutationTarget === null && ! $ambiguous => self::VALID_INVENTORY_MUTATION,
            $operation === 'mutate' && $resource === 'account_role'
                && $denialReason === 'account_or_role_mutation' && $mutationTarget === null && ! $ambiguous => self::VALID_ACCOUNT_ROLE_MUTATION,
            $operation === 'mutate' && $resource === 'returns'
                && $denialReason === 'refund_or_return_mutation' && $mutationTarget === null && ! $ambiguous => self::VALID_REFUND_RETURN_MUTATION,
            default => self::FAIL_CLOSED_UNKNOWN,
        };
    }

    /** @param array<string, array<string, mixed>> $frames @return array<string, string> */
    public static function audit(array $frames, string $scope): array
    {
        self::assertScope($scope);
        $result = [];
        foreach ($frames as $name => $frame) {
            $result[$name] = is_array($frame) && ! array_is_list($frame)
                ? self::classify($frame, $scope)
                : self::FAIL_CLOSED_UNKNOWN;
        }
        ksort($result, SORT_STRING);

        return $result;
    }

    /** @param array<string, mixed> $frame */
    private static function classifyCrossAccountRead(
        array $frame,
        ?string $semanticIntent,
        ?string $resource,
        ?string $operation,
        ?string $denialReason,
        ?string $mutationTarget,
        bool $ambiguous
    ): string {
        if ($resource !== 'other_user_data'
            || $operation !== 'read'
            || $denialReason !== 'other_user_data_access'
            || $mutationTarget !== null
            || $ambiguous) {
            return self::FAIL_CLOSED_UNKNOWN;
        }
        $entities = $frame['entities'] ?? null;
        if (! is_array($entities) || array_is_list($entities)
            || self::nullableString($entities['account_target'] ?? null) === null
            || self::nullableString($entities['topic'] ?? null) !== 'order') {
            return self::FAIL_CLOSED_UNKNOWN;
        }
        $mentioned = $frame['mentioned_resources'] ?? null;
        if (! is_array($mentioned) || ! array_is_list($mentioned)
            || array_filter($mentioned, fn (mixed $item): bool => ! is_string($item)) !== []) {
            return self::FAIL_CLOSED_UNKNOWN;
        }
        $hasOrder = in_array('order', $mentioned, true);
        $hasPayment = in_array('payment', $mentioned, true);
        if ($semanticIntent === 'payment_status_read') {
            return $hasOrder && $hasPayment
                ? self::CROSS_ACCOUNT_PAYMENT_READ
                : self::FAIL_CLOSED_UNKNOWN;
        }
        if ($semanticIntent === 'order_read') {
            return $hasOrder && ! $hasPayment
                ? self::CROSS_ACCOUNT_ORDER_READ
                : self::FAIL_CLOSED_UNKNOWN;
        }
        if ($hasPayment) {
            return $hasOrder
                ? self::CROSS_ACCOUNT_PAYMENT_READ
                : self::FAIL_CLOSED_UNKNOWN;
        }

        return $hasOrder
            ? self::CROSS_ACCOUNT_ORDER_READ
            : self::FAIL_CLOSED_UNKNOWN;
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function assertScope(string $scope): void
    {
        if (! in_array($scope, [
            FinalV11EvaluationV2R1::SCOPE_PARENT,
            FinalV11EvaluationV2R1::SCOPE_BRANCH,
        ], true)) {
            throw new FinalV11ContractException(
                'V2-r3 security-intent canonicalization requires structural scope.'
            );
        }
    }
}
