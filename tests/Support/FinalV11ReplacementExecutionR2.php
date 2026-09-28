<?php

namespace Tests\Support;

final class FinalV11ReplacementExecutionR2
{
    public const VERSION = 'farta-v11-final-r2-replacement-execution.1.0';

    public const EXECUTION_TARGET = 'LOCAL_CANDIDATE';

    public const AUTHORIZATION_ENV = 'V11_FINAL_R2_EXECUTION_AUTHORIZATION';

    public const AUTHORIZATION_VALUE = 'RUN_FROZEN_V11_R2_REPLACEMENT_ONCE';

    public const REPLACEMENT_REASON = 'EVALUATOR_EVIDENCE_DOMAIN_INFRASTRUCTURE_DEFECT';

    public const HISTORICAL_RUN_ID = 'final-v11-20260928T060655Z';

    public const R1_RUN_ID = 'final-v11-r1-20260928T070446Z';

    public const CANDIDATE_SHA256 = FinalV11ReplacementExecutionR1::CANDIDATE_SHA256;

    public const DATASET_SHA256 = FinalV11ReplacementExecutionR1::DATASET_SHA256;

    public const AUDIT_SHA256 = FinalV11ReplacementExecutionR1::AUDIT_SHA256;

    public const MANIFEST_SHA256 = FinalV11ReplacementExecutionR1::MANIFEST_SHA256;

    public const HISTORICAL_RAW_CAPTURE_SHA256 = FinalV11ReplacementExecutionR1::HISTORICAL_RAW_CAPTURE_SHA256;

    public const HISTORICAL_STATE_SHA256 = FinalV11ReplacementExecutionR1::HISTORICAL_STATE_SHA256;

    public const R1_STATE_SHA256 = '204c9f33dd890a435a0280bb91910ed9826d395a5e9cd7014eceed6a733d5ca0';

    public const TOOLCHAIN_R1_SHA256 = FinalV11ReplacementExecutionR1::TOOLCHAIN_R1_SHA256;

    public const TOOLCHAIN_R2_SHA256 = '401e530d55c8d3e2dc87885eeeff365fc59fffaaa70ca9bb2d4f60c0115726dc';

    public const HISTORICAL_RAW_CAPTURE_PATH = 'docs/evaluation/v11-independent/v11-final-raw-capture.jsonl';

    public const R1_RAW_CAPTURE_PATH = 'docs/evaluation/v11-independent/v11-final-r1-raw-capture.jsonl';

    public const CANONICALIZER_R2_SHA256 = '2d314bdd29b592a0dbc36ea4498373163c8d02466f6bbda1527d6335cdf3f75f';

    public const ADAPTER_R2_SHA256 = '8b9d2b6b4673b95b06660da24ca4884754fa2f0bb2778ff5bbef1717ce53d71f';

    public const SCORER_R2_SHA256 = 'ede3466e3216b48cc22e8a61b59684a89e8e22ba8b24af9704f2b0da1f965178';

    public const HARNESS_R2_SHA256 = 'd97c62400bf0cfa0115f4fed77e6899932bbc110b272f51211d0c59e52248de5';

    public const CONFIG_PATH = 'docs/evaluation/v11-independent/v11-final-r2-execution-bundle-config.json';

    public const BUNDLE_MANIFEST_PATH = 'docs/evaluation/v11-independent/v11-final-r2-execution-bundle-manifest.json';

    public const TOOLCHAIN_MANIFEST_PATH = 'docs/evaluation/v11-independent/v11-toolchain-v2-r2-manifest.json';

    /** @var array<int, string> */
    public const RESERVED_OUTPUT_PATHS = [
        'docs/evaluation/v11-independent/v11-final-r2-execution-state.json',
        'docs/evaluation/v11-independent/v11-final-r2-raw-capture.jsonl',
        'docs/evaluation/v11-independent/v11-final-r2-scored-results.json',
        'docs/evaluation/v11-independent/v11-final-r2-score-report.md',
        'docs/evaluation/v11-independent/v11-final-r2-execution-manifest.json',
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
            throw new FinalV11ContractException('R2 execution authorization is missing or incorrect.');
        }
    }

    /** @param array<int, string> $paths */
    public static function assertCanStart(string $authorization, array $paths, bool $synthetic = false): void
    {
        self::assertAuthorization($authorization);
        if ($synthetic) {
            FinalV11ReplacementExecutionR1::assertTemporarySyntheticPaths($paths);
        } elseif ($paths !== array_map('base_path', self::RESERVED_OUTPUT_PATHS)) {
            throw new FinalV11ContractException('Actual R2 execution must use the frozen R2 output namespace.');
        }
        foreach ($paths as $path) {
            if (file_exists($path)) {
                throw new FinalV11ContractException('Reserved R2 output already exists; refusing overwrite or resume: '.$path);
            }
        }
    }

    /** @return array<string, mixed> */
    public static function primaryInput(array $case): array
    {
        return FinalV11ReplacementExecutionR1::primaryInput($case);
    }

    /** @return array<string, mixed> */
    public static function scenarioTurnInput(array $scenario, array $turn): array
    {
        return FinalV11ReplacementExecutionR1::scenarioTurnInput($scenario, $turn);
    }

    /** @param array<string, mixed> $request @return array{message: string} */
    public static function candidateRequest(array $request): array
    {
        return FinalV11ReplacementExecutionR1::candidateRequest($request);
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
            'evidence_domain_canonicalizer_v2_r2' => self::CANONICALIZER_R2_SHA256,
            'runtime_adapter_v2_r2' => self::ADAPTER_R2_SHA256,
            'scorer_v2_r2' => self::SCORER_R2_SHA256,
            'harness_r2' => self::HARNESS_R2_SHA256,
            'toolchain_v2_r2' => self::TOOLCHAIN_R2_SHA256,
            'replacement_execution_bundle_r2' => self::requiredString(
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
            ...self::identities(),
            'runtime_adapter_version' => FinalV11RuntimeAdapterV2R2::VERSION,
            'started_at_utc' => $startedAtUtc,
            'completed_at_utc' => $completedAtUtc,
            'candidate_request_count' => $requestCount,
            'manual_retry_count' => 0,
            'resume_count' => 0,
            'historical_capture_reused' => false,
            'r1_capture_reused' => false,
        ];
    }

    /** @param array<string, mixed> $state */
    public static function assertScoringAllowed(array $state): void
    {
        if (($state['execution_state'] ?? null) !== 'CAPTURE_COMPLETED'
            || ($state['cases_started'] ?? null) !== ($state['cases_completed'] ?? null)
            || ($state['cases_failed'] ?? null) !== 0) {
            throw new FinalV11ContractException('Scoring is forbidden until the R2 capture is complete.');
        }
    }

    public static function assertReplacementCaptureSource(string $path): void
    {
        if ($path === base_path(self::HISTORICAL_RAW_CAPTURE_PATH)
            || $path === base_path(self::R1_RAW_CAPTURE_PATH)
            || (is_file($path) && hash_file('sha256', $path) === self::HISTORICAL_RAW_CAPTURE_SHA256)) {
            throw new FinalV11ContractException('Historical and R1 raw captures cannot be R2 replacement input.');
        }
    }

    /** @param array<string, mixed> $value */
    public static function deterministicHash(array $value): string
    {
        return FinalV11ReplacementExecutionR1::deterministicHash($value);
    }

    public static function verifyFrozenIdentities(): void
    {
        FinalV11ReplacementExecutionR1::verifyFrozenIdentities();
        $r1State = base_path('docs/evaluation/v11-independent/v11-final-r1-execution-state.json');
        if (! hash_equals(self::R1_STATE_SHA256, (string) hash_file('sha256', $r1State))) {
            throw new FinalV11ContractException('Frozen R1 incomplete state identity mismatch.');
        }

        $manifest = self::decodeObject(base_path(self::TOOLCHAIN_MANIFEST_PATH));
        $expected = [
            'evidence_domain_canonicalizer_v2_r2' => self::CANONICALIZER_R2_SHA256,
            'runtime_adapter_v2_r2' => self::ADAPTER_R2_SHA256,
            'scorer_v2_r2' => self::SCORER_R2_SHA256,
            'harness_r2' => self::HARNESS_R2_SHA256,
            'toolchain_v2_r2' => self::TOOLCHAIN_R2_SHA256,
        ];
        foreach ($expected as $key => $identity) {
            $component = $manifest['revised_components'][$key] ?? null;
            if (! is_array($component) || ($component['semantic_identity'] ?? null) !== $identity) {
                throw new FinalV11ContractException('Frozen Toolchain V2-r2 manifest mismatch: '.$key);
            }
            $actual = FinalV11Evaluation::semanticHash(array_map(
                fn (string $path): string => base_path($path),
                $component['ordered_semantic_files'] ?? []
            ));
            if (! hash_equals($identity, $actual)) {
                throw new FinalV11ContractException('Frozen Toolchain V2-r2 source mismatch: '.$key);
            }
        }
    }

    public static function verifyBundleIdentity(): string
    {
        $manifest = self::bundleManifest();
        $files = $manifest['ordered_semantic_files'] ?? null;
        $expected = $manifest['execution_bundle_semantic_identity'] ?? null;
        if (! is_array($files) || ! array_is_list($files) || ! is_string($expected)) {
            throw new FinalV11ContractException('Invalid R2 execution-bundle manifest.');
        }
        $actual = FinalV11Evaluation::semanticHash(array_map(
            fn (string $path): string => base_path($path),
            $files
        ));
        if (! hash_equals($expected, $actual)) {
            throw new FinalV11ContractException('R2 execution-bundle identity mismatch.');
        }

        return $actual;
    }

    /** @param array<string, mixed> $value */
    public static function writeJsonExclusive(string $path, array $value): string
    {
        return FinalV11ReplacementExecutionR1::writeJsonExclusive($path, $value);
    }

    public static function writeTextExclusive(string $path, string $bytes): string
    {
        return FinalV11ReplacementExecutionR1::writeTextExclusive($path, $bytes);
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
