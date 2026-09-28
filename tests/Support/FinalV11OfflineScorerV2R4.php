<?php

namespace Tests\Support;

final class FinalV11OfflineScorerV2R4
{
    public const VERSION = 'farta-final-v11-offline-scorer.2.0.4';

    /** @param array<string, mixed> $contract @param array<string, mixed> $capture @param array<string, mixed> $factContract @return array<string, mixed> */
    public static function score(array $contract, array $capture, array $factContract): array
    {
        if (($capture['metadata']['runtime_adapter_version'] ?? null) !== FinalV11RuntimeAdapterV2R4::VERSION) {
            throw new FinalV11ContractException('V2-r4 scorer requires a V2-r4 runtime adapter capture.');
        }
        self::assertCapturePredictionSchemas($capture);

        return FinalV11OfflineScorerV2::score($contract, $capture, $factContract);
    }

    /** @param array<string, mixed> $capture */
    public static function assertCapturePredictionSchemas(array $capture): void
    {
        FinalV11OfflineScorerV2R3::assertCapturePredictionSchemas($capture);
    }

    /** @param array<string, mixed> $prediction @param array<string, mixed> $factContract @return array<string, mixed> */
    public static function reconcilePrediction(string $recordId, array $prediction, array $factContract): array
    {
        return FinalV11OfflineScorerV2R3::reconcilePrediction($recordId, $prediction, $factContract);
    }

    /** @param array<string, mixed> $observation */
    public static function verifyCanonicalEvidence(array $observation, ?string $scope = null): void
    {
        FinalV11OfflineScorerV2R3::verifyCanonicalEvidence($observation, $scope);
    }
}
