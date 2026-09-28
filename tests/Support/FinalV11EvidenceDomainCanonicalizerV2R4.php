<?php

namespace Tests\Support;

final class FinalV11EvidenceDomainCanonicalizerV2R4
{
    public const VERSION = 'farta-final-v11-evidence-domain-canonicalizer.2.0.4';

    /** @var array<string, array<string, string>> */
    private const REACHABLE_ALIASES = [
        'knowledge_query' => [
            'contact' => 'store_contact_settings',
            'returns' => 'returns_policy',
        ],
    ];

    public static function canonicalize(?string $domain, string $semanticIntent, string $scope): ?string
    {
        self::assertScope($scope);
        $mapped = self::REACHABLE_ALIASES[$semanticIntent][$domain ?? ''] ?? null;

        return $mapped ?? FinalV11EvidenceDomainCanonicalizerV2R2::canonicalize(
            $domain,
            $semanticIntent,
            $scope
        );
    }

    /** @param array<int, string> $internalDomains @return array<string, string> */
    public static function audit(array $internalDomains): array
    {
        $result = FinalV11EvidenceDomainCanonicalizerV2R2::audit($internalDomains);
        if (array_key_exists('returns', $result)) {
            $result['returns'] = 'STRUCTURAL_ALIAS';
        }
        ksort($result, SORT_STRING);

        return $result;
    }

    private static function assertScope(string $scope): void
    {
        if (! in_array($scope, [
            FinalV11EvaluationV2R1::SCOPE_PARENT,
            FinalV11EvaluationV2R1::SCOPE_BRANCH,
        ], true)) {
            throw new FinalV11ContractException(
                'V2-r4 evidence-domain canonicalization requires structural scope.'
            );
        }
    }
}
