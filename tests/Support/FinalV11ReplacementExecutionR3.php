<?php

namespace Tests\Support;

final class FinalV11ReplacementExecutionR3
{
    public const VERSION = 'farta-v11-final-r3-replacement-execution.1.0';

    public const EXECUTION_TARGET = 'LOCAL_CANDIDATE';

    public const AUTHORIZATION_ENV = 'V11_FINAL_R3_EXECUTION_AUTHORIZATION';

    public const AUTHORIZATION_VALUE = 'RUN_FROZEN_V11_R3_REPLACEMENT_ONCE';

    public const REPLACEMENT_REASON = 'EVALUATOR_TYPED_CROSS_ACCOUNT_READ_INFRASTRUCTURE_DEFECT';

    public const HISTORICAL_RUN_ID = FinalV11ReplacementExecutionR2::HISTORICAL_RUN_ID;

    public const R1_RUN_ID = FinalV11ReplacementExecutionR2::R1_RUN_ID;

    public const R2_RUN_ID = 'final-v11-r2-20260928T073537Z';

    public const CANDIDATE_SHA256 = FinalV11ReplacementExecutionR2::CANDIDATE_SHA256;

    public const DATASET_SHA256 = FinalV11ReplacementExecutionR2::DATASET_SHA256;

    public const AUDIT_SHA256 = FinalV11ReplacementExecutionR2::AUDIT_SHA256;

    public const MANIFEST_SHA256 = FinalV11ReplacementExecutionR2::MANIFEST_SHA256;

    public const HISTORICAL_STATE_SHA256 = FinalV11ReplacementExecutionR2::HISTORICAL_STATE_SHA256;

    public const R1_STATE_SHA256 = FinalV11ReplacementExecutionR2::R1_STATE_SHA256;

    public const R2_STATE_SHA256 = '37d9dacf168cf9b00ce435790a7f0fd1df2bfa4c0edbf0e8c4457826373cf569';

    public const BUNDLE_R2_SHA256 = '873e13d70de314a015f2c68ed3b89c90952d37fc5841830c257bf46123745cd8';

    public const TOOLCHAIN_R2_SHA256 = FinalV11ReplacementExecutionR2::TOOLCHAIN_R2_SHA256;

    public const TOOLCHAIN_R3_SHA256 = 'beb1ca7c0431e3d7b2d67990b554f9713a53c0963fc15af0fc1d16baab053486';

    public const SECURITY_CANONICALIZER_R3_SHA256 = 'bb22b631778faeaa34995fa1254bd654f1b6787210a346d0490a72329cf98616';

    public const ADAPTER_R3_SHA256 = '93b28a3d8202195d48a404e6d2a7de9ddc91658a466f5965a43ff9b7a17713c4';

    public const SCORER_R3_SHA256 = '82febd17d1487e26384cd9eae90b2c388866299ee89a8dbed7a00d32e6d98d91';

    public const HARNESS_R3_SHA256 = 'f4ad6efc0f982983ef9d3e5af625b7d1a73116f0c85898ad9feecefae424b873';

    public const CONFIG_PATH = 'docs/evaluation/v11-independent/v11-final-r3-execution-bundle-config.json';

    public const BUNDLE_MANIFEST_PATH = 'docs/evaluation/v11-independent/v11-final-r3-execution-bundle-manifest.json';

    public const TOOLCHAIN_MANIFEST_PATH = 'docs/evaluation/v11-independent/v11-toolchain-v2-r3-manifest.json';

    public const HISTORICAL_RAW_CAPTURE_PATH = 'docs/evaluation/v11-independent/v11-final-raw-capture.jsonl';

    public const R1_RAW_CAPTURE_PATH = 'docs/evaluation/v11-independent/v11-final-r1-raw-capture.jsonl';

    public const R2_RAW_CAPTURE_PATH = 'docs/evaluation/v11-independent/v11-final-r2-raw-capture.jsonl';

    /** @var array<int, string> */
    public const RESERVED_OUTPUT_PATHS = [
        'docs/evaluation/v11-independent/v11-final-r3-execution-state.json',
        'docs/evaluation/v11-independent/v11-final-r3-raw-capture.jsonl',
        'docs/evaluation/v11-independent/v11-final-r3-scored-results.json',
        'docs/evaluation/v11-independent/v11-final-r3-score-report.md',
        'docs/evaluation/v11-independent/v11-final-r3-execution-manifest.json',
    ];

    /** @return array<string, mixed> */
    public static function config(): array
    {
        return self::decodeObject(base_path(self::CONFIG_PATH));
    }

    /** @return array<string, mixed> */
    public static function bundleManifest(): array
    {
        return self::decodeObject(base_path(self::BUNDLE_MANIFEST_PATH));
    }

    public static function assertAuthorization(string $authorization): void
    {
        if (! hash_equals(self::AUTHORIZATION_VALUE, $authorization)) {
            throw new FinalV11ContractException('R3 execution authorization is missing or incorrect.');
        }
    }

    /** @param array<int, string> $paths */
    public static function assertCanStart(string $authorization, array $paths, bool $synthetic = false): void
    {
        self::assertAuthorization($authorization);
        if ($synthetic) {
            FinalV11ReplacementExecutionR1::assertTemporarySyntheticPaths($paths);
        } elseif ($paths !== array_map('base_path', self::RESERVED_OUTPUT_PATHS)) {
            throw new FinalV11ContractException('Actual R3 execution must use the frozen R3 output namespace.');
        }
        foreach ($paths as $path) {
            if (file_exists($path)) {
                throw new FinalV11ContractException(
                    'Reserved R3 output already exists; refusing overwrite or resume: '.$path
                );
            }
        }
    }

    /** @return array<string, mixed> */
    public static function primaryInput(array $case): array
    {
        return FinalV11ReplacementExecutionR2::primaryInput($case);
    }

    /** @return array<string, mixed> */
    public static function scenarioTurnInput(array $scenario, array $turn): array
    {
        return FinalV11ReplacementExecutionR2::scenarioTurnInput($scenario, $turn);
    }

    /** @param array<string, mixed> $request @return array{message: string} */
    public static function candidateRequest(array $request): array
    {
        return FinalV11ReplacementExecutionR2::candidateRequest($request);
    }

    /** @return array<string, string> */
    public static function identities(): array
    {
        $manifest = self::bundleManifest();

        return [
            'candidate' => self::CANDIDATE_SHA256,
            'dataset' => self::DATASET_SHA256,
            'audit' => self::AUDIT_SHA256,
            'final_manifest' => self::MANIFEST_SHA256,
            'evaluator_v2_r1' => FinalV11ReplacementExecutionR1::EVALUATOR_R1_SHA256,
            'evidence_domain_canonicalizer_v2_r2' => FinalV11ReplacementExecutionR2::CANONICALIZER_R2_SHA256,
            'security_intent_canonicalizer_v2_r3' => self::SECURITY_CANONICALIZER_R3_SHA256,
            'runtime_adapter_v2_r3' => self::ADAPTER_R3_SHA256,
            'scorer_v2_r3' => self::SCORER_R3_SHA256,
            'harness_r3' => self::HARNESS_R3_SHA256,
            'toolchain_v2_r3' => self::TOOLCHAIN_R3_SHA256,
            'replacement_execution_bundle_r3' => self::requiredString(
                $manifest['execution_bundle_semantic_identity'] ?? null,
                'execution_bundle_semantic_identity'
            ),
        ];
    }

    /** @return array<string, mixed> */
    public static function metadata(string $runId, string $startedAtUtc, ?string $completedAtUtc, int $requestCount): array
    {
        return [
            'replacement_run_id' => $runId,
            'replacement_reason' => self::REPLACEMENT_REASON,
            'historical_run_id' => self::HISTORICAL_RUN_ID,
            'r1_run_id' => self::R1_RUN_ID,
            'r2_run_id' => self::R2_RUN_ID,
            ...self::identities(),
            'runtime_adapter_version' => FinalV11RuntimeAdapterV2R3::VERSION,
            'started_at_utc' => $startedAtUtc,
            'completed_at_utc' => $completedAtUtc,
            'candidate_request_count' => $requestCount,
            'manual_retry_count' => 0,
            'resume_count' => 0,
            'historical_capture_reused' => false,
            'r1_capture_reused' => false,
            'r2_capture_reused' => false,
        ];
    }

    /** @param array<string, mixed> $state */
    public static function assertScoringAllowed(array $state): void
    {
        if (($state['execution_state'] ?? null) !== 'CAPTURE_COMPLETED'
            || ($state['cases_started'] ?? null) !== ($state['cases_completed'] ?? null)
            || ($state['cases_failed'] ?? null) !== 0) {
            throw new FinalV11ContractException('Scoring is forbidden until the R3 capture is complete.');
        }
    }

    public static function assertReplacementCaptureSource(string $path): void
    {
        if (in_array($path, [
            base_path(self::HISTORICAL_RAW_CAPTURE_PATH),
            base_path(self::R1_RAW_CAPTURE_PATH),
            base_path(self::R2_RAW_CAPTURE_PATH),
        ], true) || (is_file($path)
            && hash_file('sha256', $path) === FinalV11ReplacementExecutionR2::HISTORICAL_RAW_CAPTURE_SHA256)) {
            throw new FinalV11ContractException('Historical, R1, and R2 captures cannot be R3 replacement input.');
        }
    }

    /** @param array<string, mixed> $value */
    public static function deterministicHash(array $value): string
    {
        return FinalV11ReplacementExecutionR2::deterministicHash($value);
    }

    public static function verifyFrozenIdentities(): void
    {
        FinalV11ReplacementExecutionR2::verifyFrozenIdentities();
        if (! hash_equals(
            self::R2_STATE_SHA256,
            (string) hash_file('sha256', base_path('docs/evaluation/v11-independent/v11-final-r2-execution-state.json'))
        )) {
            throw new FinalV11ContractException('Frozen R2 incomplete state identity mismatch.');
        }
        if (! hash_equals(self::BUNDLE_R2_SHA256, FinalV11ReplacementExecutionR2::verifyBundleIdentity())) {
            throw new FinalV11ContractException('Frozen R2 execution-bundle identity mismatch.');
        }

        $manifest = self::decodeObject(base_path(self::TOOLCHAIN_MANIFEST_PATH));
        $expected = [
            'security_intent_canonicalizer_v2_r3' => self::SECURITY_CANONICALIZER_R3_SHA256,
            'runtime_adapter_v2_r3' => self::ADAPTER_R3_SHA256,
            'scorer_v2_r3' => self::SCORER_R3_SHA256,
            'harness_r3' => self::HARNESS_R3_SHA256,
            'toolchain_v2_r3' => self::TOOLCHAIN_R3_SHA256,
        ];
        foreach ($expected as $key => $identity) {
            $component = $manifest['revised_components'][$key] ?? null;
            if (! is_array($component) || ($component['semantic_identity'] ?? null) !== $identity) {
                throw new FinalV11ContractException('Frozen Toolchain V2-r3 manifest mismatch: '.$key);
            }
            $actual = FinalV11Evaluation::semanticHash(array_map(
                fn (string $path): string => base_path($path),
                $component['ordered_semantic_files'] ?? []
            ));
            if (! hash_equals($identity, $actual)) {
                throw new FinalV11ContractException('Frozen Toolchain V2-r3 source mismatch: '.$key);
            }
        }
    }

    public static function verifyBundleIdentity(): string
    {
        $manifest = self::bundleManifest();
        $files = $manifest['ordered_semantic_files'] ?? null;
        $expected = $manifest['execution_bundle_semantic_identity'] ?? null;
        if (! is_array($files) || ! array_is_list($files) || ! is_string($expected)) {
            throw new FinalV11ContractException('Invalid R3 execution-bundle manifest.');
        }
        $actual = FinalV11Evaluation::semanticHash(array_map(
            fn (string $path): string => base_path($path),
            $files
        ));
        if (! hash_equals($expected, $actual)) {
            throw new FinalV11ContractException('R3 execution-bundle identity mismatch.');
        }

        return $actual;
    }

    /** @param array<string, mixed> $value */
    public static function writeJsonExclusive(string $path, array $value): string
    {
        return FinalV11ReplacementExecutionR2::writeJsonExclusive($path, $value);
    }

    public static function writeTextExclusive(string $path, string $bytes): string
    {
        return FinalV11ReplacementExecutionR2::writeTextExclusive($path, $bytes);
    }

    /** @return array<string, mixed> */
    private static function decodeObject(string $path): array
    {
        $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($value) || array_is_list($value)) {
            throw new FinalV11ContractException('Expected JSON object: '.$path);
        }

        return $value;
    }

    private static function requiredString(mixed $value, string $path): string
    {
        if (! is_string($value) || $value === '') {
            throw new FinalV11ContractException($path.' must be a non-empty string.');
        }

        return $value;
    }
}
