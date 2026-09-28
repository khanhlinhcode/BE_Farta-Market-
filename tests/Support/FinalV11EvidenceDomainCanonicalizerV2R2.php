<?php

namespace Tests\Support;

final class FinalV11EvidenceDomainCanonicalizerV2R2
{
    public const VERSION = 'farta-final-v11-evidence-domain-canonicalizer.2.0.2';

    /** @var array<string, string> */
    private const STRUCTURAL_DOMAINS = [
        'product_search' => 'product_catalog',
        'product_detail' => 'product_catalog',
        'price' => 'product_price',
        'stock_availability' => 'product_inventory',
        'cart_action_request' => 'product_inventory_and_ordering_contract',
        'order_read' => 'owned_order_data',
        'payment_status_read' => 'owned_order_payment_status',
        'shipping_current_value' => 'shipping_settings',
        'shipping_calculation' => 'product_price_and_shipping_settings',
        'store_contact' => 'store_contact_settings',
    ];

    /** @var array<string, array<int, string>> */
    private const ALLOWED_INTERNAL_ALIASES = [
        'shipping_current_value' => ['shipping'],
        'shipping_calculation' => ['shipping'],
        'store_contact' => ['contact'],
    ];

    public static function canonicalize(?string $domain, string $semanticIntent, string $scope): ?string
    {
        self::assertScope($scope);
        $expected = self::STRUCTURAL_DOMAINS[$semanticIntent] ?? null;
        if ($expected !== null) {
            if ($domain === $expected
                || in_array($domain, self::ALLOWED_INTERNAL_ALIASES[$semanticIntent] ?? [], true)) {
                return $expected;
            }

            throw new FinalV11ContractException(
                'V2-r2 evidence domain does not match typed semantic intent '.$semanticIntent.'.'
            );
        }
        if ($domain === null) {
            return null;
        }
        if (! in_array($domain, self::frozenEvidenceDomains(), true)) {
            throw new FinalV11ContractException('V2-r2 encountered an unmapped internal evidence domain.');
        }

        return $domain;
    }

    /** @param array<int, string> $internalDomains @return array<string, string> */
    public static function audit(array $internalDomains): array
    {
        $schema = self::frozenEvidenceDomains();
        $aliases = array_values(array_unique(array_merge(...array_values(self::ALLOWED_INTERNAL_ALIASES))));
        $result = [];
        foreach ($internalDomains as $domain) {
            $result[$domain] = match (true) {
                in_array($domain, $schema, true) => 'SCHEMA_CANONICAL',
                in_array($domain, $aliases, true) => 'STRUCTURAL_ALIAS',
                default => 'FAIL_CLOSED_UNMAPPED',
            };
        }
        ksort($result, SORT_STRING);

        return $result;
    }

    /** @return array<int, string> */
    public static function frozenEvidenceDomains(): array
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
        $values = $contract['enum_definitions']['evidence_domain']['values'] ?? null;
        if (! is_array($values) || ! array_is_list($values)) {
            throw new FinalV11ContractException('Missing frozen V11 evidence-domain enum.');
        }

        return $values;
    }

    private static function assertScope(string $scope): void
    {
        if (! in_array($scope, [
            FinalV11EvaluationV2R1::SCOPE_PARENT,
            FinalV11EvaluationV2R1::SCOPE_BRANCH,
        ], true)) {
            throw new FinalV11ContractException('V2-r2 evidence-domain canonicalization requires structural scope.');
        }
    }
}
