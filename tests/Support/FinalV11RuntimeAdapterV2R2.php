<?php

namespace Tests\Support;

use App\Enums\ChatIntent;
use App\Services\Chat\ChatEvidenceDomain;
use App\Services\Chat\ChatRouteFrame;

final class FinalV11RuntimeAdapterV2R2
{
    public const VERSION = 'farta-final-v11-runtime-adapter.2.0.2';

    /** @param array<string, mixed> $runtime @return array<string, mixed> */
    public static function normalize(
        ChatRouteFrame|array $route,
        array $runtime,
        ?string $scope = null
    ): array {
        self::assertScope($scope);

        return FinalV11RuntimeAdapterV2R1::normalize(
            self::canonicalRoute($route, $scope),
            $runtime,
            $scope
        );
    }

    /** @return array<string, mixed> */
    public static function canonicalRoute(ChatRouteFrame|array $route, ?string $scope = null): array
    {
        self::assertScope($scope);
        $frame = self::frame($route);
        $semanticIntent = $frame['semantic_intent'] ?? null;
        if (! is_string($semanticIntent) || $semanticIntent === '') {
            throw new FinalV11ContractException('V2-r2 route requires a typed semantic_intent.');
        }
        $domain = $frame['required_evidence_domain'] ?? null;
        if ($domain instanceof ChatEvidenceDomain) {
            $domain = $domain->topic;
        }
        if ($domain !== null && ! is_string($domain)) {
            throw new FinalV11ContractException('V2-r2 route evidence domain must be string or null.');
        }
        $frame['required_evidence_domain'] = FinalV11EvidenceDomainCanonicalizerV2R2::canonicalize(
            $domain,
            $semanticIntent,
            $scope
        );

        $branches = $frame['subrequests'] ?? $frame['branches'] ?? [];
        if (! is_array($branches) || ! array_is_list($branches)) {
            throw new FinalV11ContractException('V2-r2 structural branches must be an ordered array.');
        }
        $frame['subrequests'] = array_map(
            fn (mixed $branch): array => self::canonicalBranch($branch),
            $branches
        );
        unset($frame['branches']);

        return $frame;
    }

    /** @param array<string, mixed> $observation @return array<string, mixed> */
    public static function prediction(array $observation, ?string $scope = null): array
    {
        return FinalV11RuntimeAdapterV2R1::prediction($observation, $scope);
    }

    public static function claimId(string $claimKey): string
    {
        return FinalV11RuntimeAdapterV2R1::claimId($claimKey);
    }

    public static function claimKeyFromProvenance(
        string $sourceId,
        string $evidenceRef,
        string $evidenceType
    ): string {
        return FinalV11RuntimeAdapterV2R1::claimKeyFromProvenance($sourceId, $evidenceRef, $evidenceType);
    }

    /** @return array<string, mixed> */
    private static function canonicalBranch(mixed $branch): array
    {
        if (! $branch instanceof ChatRouteFrame && (! is_array($branch) || array_is_list($branch))) {
            throw new FinalV11ContractException('V2-r2 branch route must be an object.');
        }

        return self::canonicalRoute($branch, FinalV11EvaluationV2R1::SCOPE_BRANCH);
    }

    /** @return array<string, mixed> */
    private static function frame(ChatRouteFrame|array $route): array
    {
        if ($route instanceof ChatRouteFrame) {
            return [
                ...$route->toArray(),
                'intent' => $route->intent->value,
                'required_evidence_domain' => $route->requiredEvidenceDomain?->topic,
                'subrequests' => $route->branches,
            ];
        }
        if (array_is_list($route)) {
            throw new FinalV11ContractException('V2-r2 route must be an object.');
        }
        if (($route['intent'] ?? null) instanceof ChatIntent) {
            $route['intent'] = $route['intent']->value;
        }

        return $route;
    }

    private static function assertScope(?string $scope): void
    {
        if (! in_array($scope, [
            FinalV11EvaluationV2R1::SCOPE_PARENT,
            FinalV11EvaluationV2R1::SCOPE_BRANCH,
        ], true)) {
            throw new FinalV11ContractException('V2-r2 adapter requires explicit PARENT or BRANCH scope.');
        }
    }
}
