<?php

namespace Tests\Support;

use App\Enums\ChatIntent;
use App\Services\Chat\ChatEvidenceDomain;
use App\Services\Chat\ChatRouteFrame;

final class FinalV11RuntimeAdapterV2R3
{
    public const VERSION = 'farta-final-v11-runtime-adapter.2.0.3';

    /** @param array<string, mixed> $runtime @return array<string, mixed> */
    public static function normalize(
        ChatRouteFrame|array $route,
        array $runtime,
        ?string $scope = null
    ): array {
        self::assertScope($scope);
        $frame = self::frame($route);

        return FinalV11RuntimeAdapterV2R2::normalize(
            self::canonicalRoute($frame, $scope),
            self::canonicalRuntime($frame, $runtime, $scope),
            $scope
        );
    }

    /** @return array<string, mixed> */
    public static function canonicalRoute(ChatRouteFrame|array $route, ?string $scope = null): array
    {
        self::assertScope($scope);
        $frame = self::frame($route);
        $branches = $frame['subrequests'] ?? $frame['branches'] ?? [];
        if (! is_array($branches) || ! array_is_list($branches)) {
            throw new FinalV11ContractException('V2-r3 structural branches must be an ordered array.');
        }
        $frame['subrequests'] = array_map(
            fn (mixed $branch): array => self::canonicalBranch($branch),
            $branches
        );
        unset($frame['branches']);
        $frame = FinalV11SecurityIntentCanonicalizerV2R3::canonicalize($frame, $scope);

        return FinalV11RuntimeAdapterV2R2::canonicalRoute($frame, $scope);
    }

    /** @param array<string, mixed> $observation @return array<string, mixed> */
    public static function prediction(array $observation, ?string $scope = null): array
    {
        return FinalV11RuntimeAdapterV2R2::prediction($observation, $scope);
    }

    public static function claimId(string $claimKey): string
    {
        return FinalV11RuntimeAdapterV2R2::claimId($claimKey);
    }

    public static function claimKeyFromProvenance(
        string $sourceId,
        string $evidenceRef,
        string $evidenceType
    ): string {
        return FinalV11RuntimeAdapterV2R2::claimKeyFromProvenance($sourceId, $evidenceRef, $evidenceType);
    }

    /** @param array<string, mixed> $frame @param array<string, mixed> $runtime @return array<string, mixed> */
    private static function canonicalRuntime(array $frame, array $runtime, string $scope): array
    {
        $branches = $frame['subrequests'] ?? $frame['branches'] ?? [];
        $runtimeBranches = $runtime['branch_observations'] ?? null;
        if (! is_array($branches) || ! array_is_list($branches)
            || ! is_array($runtimeBranches) || ! array_is_list($runtimeBranches)
            || count($branches) !== count($runtimeBranches)) {
            throw new FinalV11ContractException(
                'V2-r3 branch frames and runtime observations must be ordered arrays of equal length.'
            );
        }
        foreach ($branches as $index => $branch) {
            if (! $branch instanceof ChatRouteFrame && (! is_array($branch) || array_is_list($branch))) {
                throw new FinalV11ContractException('V2-r3 branch route must be an object.');
            }
            if (! is_array($runtimeBranches[$index]) || array_is_list($runtimeBranches[$index])) {
                throw new FinalV11ContractException('V2-r3 branch runtime observation must be an object.');
            }
            $runtime['branch_observations'][$index] = self::canonicalRuntime(
                self::frame($branch),
                $runtimeBranches[$index],
                FinalV11EvaluationV2R1::SCOPE_BRANCH
            );
        }

        return FinalV11SecurityIntentCanonicalizerV2R3::canonicalizeRuntime($frame, $runtime, $scope);
    }

    /** @return array<string, mixed> */
    private static function canonicalBranch(mixed $branch): array
    {
        if (! $branch instanceof ChatRouteFrame && (! is_array($branch) || array_is_list($branch))) {
            throw new FinalV11ContractException('V2-r3 branch route must be an object.');
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
            throw new FinalV11ContractException('V2-r3 route must be an object.');
        }
        if (($route['intent'] ?? null) instanceof ChatIntent) {
            $route['intent'] = $route['intent']->value;
        }
        if (($route['required_evidence_domain'] ?? null) instanceof ChatEvidenceDomain) {
            $route['required_evidence_domain'] = $route['required_evidence_domain']->topic;
        }

        return $route;
    }

    private static function assertScope(?string $scope): void
    {
        if (! in_array($scope, [
            FinalV11EvaluationV2R1::SCOPE_PARENT,
            FinalV11EvaluationV2R1::SCOPE_BRANCH,
        ], true)) {
            throw new FinalV11ContractException('V2-r3 adapter requires explicit PARENT or BRANCH scope.');
        }
    }
}
