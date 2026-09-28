<?php

namespace Tests\Support;

final class FinalV11ReplacementExecutionR1
{
    public const VERSION = 'farta-v11-final-r1-replacement-execution.1.0';

    public const EXECUTION_TARGET = 'LOCAL_CANDIDATE';

    public const AUTHORIZATION_ENV = 'V11_FINAL_R1_EXECUTION_AUTHORIZATION';

    public const AUTHORIZATION_VALUE = 'RUN_FROZEN_V11_R1_REPLACEMENT_ONCE';

    public const REPLACEMENT_REASON = 'EVALUATOR_INFRASTRUCTURE_DEFECT';

    public const HISTORICAL_RUN_ID = 'final-v11-20260928T060655Z';

    public const CANDIDATE_SHA256 = 'af36f24e2cebe333137ecf607000ff54dbefec699c7a50bf0b32782b84a08ce9';

    public const DATASET_SHA256 = '94bc6d3930057f7584c80baac66df1f05f98133b94fb733229fe22b49768f3cb';

    public const AUDIT_SHA256 = 'c97a9d4c1e6621cb9c18fc692887b9302de302cc73372f8469e6ca5b22bd70ea';

    public const MANIFEST_SHA256 = 'f2ccc7420918cd12957877e305e7a99eecf08b274cd0b3fa4ef63dbba9e35f4e';

    public const HISTORICAL_RAW_CAPTURE_SHA256 = '2f5f7cac8fdc0fad0f01d491b31f10423e28c0fff3aac638a090b36fe407f081';

    public const HISTORICAL_STATE_SHA256 = 'c1146a3256a3a45fada6bc12868362187dd57771fc38d4aa78bec8eee478ef51';

    public const EVALUATOR_R1_SHA256 = '0fcce11e5650340c6112372e3f879948555696ed66edb2c64ecfd60e68dee9ef';

    public const ADAPTER_R1_SHA256 = 'ff1db1e229c5498a0599a8cbf3c3223dc9c3e1b972c448107c8bd97fbcfd9ed8';

    public const SCORER_R1_SHA256 = 'adff5766ebed64125561be979c72f691240f563d54bfe3ff8b81a415fb8bf798';

    public const HARNESS_R1_SHA256 = 'b5f628d48b6adae8f52b44997a087616d64f36aeadd2cfcdd7ddc63a25f78fe2';

    public const TOOLCHAIN_R1_SHA256 = '29714cce29739581347fe36c3fd2ab179a88d8f81cf1dfdb07fba6e0ea639418';

    public const CONFIG_PATH = 'docs/evaluation/v11-independent/v11-final-r1-execution-bundle-config.json';

    public const BUNDLE_MANIFEST_PATH = 'docs/evaluation/v11-independent/v11-final-r1-execution-bundle-manifest.json';

    public const HISTORICAL_RAW_CAPTURE_PATH = 'docs/evaluation/v11-independent/v11-final-raw-capture.jsonl';

    /** @var array<int, string> */
    public const RESERVED_OUTPUT_PATHS = [
        'docs/evaluation/v11-independent/v11-final-r1-execution-state.json',
        'docs/evaluation/v11-independent/v11-final-r1-raw-capture.jsonl',
        'docs/evaluation/v11-independent/v11-final-r1-scored-results.json',
        'docs/evaluation/v11-independent/v11-final-r1-score-report.md',
        'docs/evaluation/v11-independent/v11-final-r1-execution-manifest.json',
    ];

    /** @var array<int, string> */
    private const FORBIDDEN_CANDIDATE_INPUT_KEYS = [
        'expected_intent', 'expected_entity', 'expected_handler', 'expected_business_outcome',
        'expected_claim', 'expected_label', 'gold', 'gold_label', 'gold_branch', 'gold_intent',
        'entity_gold', 'grounding_gold', 'minimum_facts_required', 'prediction', 'historical_prediction',
    ];

    /** @var array<int, string> */
    private const NONDETERMINISTIC_KEYS = [
        'started_at_utc', 'completed_at_utc', 'recorded_at_utc', 'latency_ms',
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
            throw new FinalV11ContractException('Replacement execution authorization is missing or incorrect.');
        }
    }

    /**
     * @param  array<int, string>  $paths
     */
    public static function assertCanStart(string $authorization, array $paths, bool $synthetic = false): void
    {
        self::assertAuthorization($authorization);
        if ($synthetic) {
            self::assertTemporarySyntheticPaths($paths);
        } elseif ($paths !== array_map('base_path', self::RESERVED_OUTPUT_PATHS)) {
            throw new FinalV11ContractException('Actual execution must use the frozen R1 output namespace.');
        }
        foreach ($paths as $path) {
            if (file_exists($path)) {
                throw new FinalV11ContractException('Reserved R1 output already exists; refusing overwrite or resume: '.$path);
            }
        }
    }

    /** @param array<int, string> $paths */
    public static function assertTemporarySyntheticPaths(array $paths): void
    {
        $temporaryRoot = rtrim((string) realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if ($paths === []) {
            throw new FinalV11ContractException('Synthetic execution requires temporary paths.');
        }
        foreach ($paths as $path) {
            $parent = realpath(dirname($path));
            if (! is_string($parent)
                || ! str_starts_with(rtrim($parent, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR, $temporaryRoot)) {
                throw new FinalV11ContractException('Synthetic execution is restricted to a temporary directory.');
            }
        }
    }

    /** @return array<string, mixed> */
    public static function primaryInput(array $case): array
    {
        $caseId = self::requiredString($case['case_id'] ?? null, 'case_id');
        $input = [
            'record_type' => 'primary',
            'case_id' => $caseId,
            'session_id' => 'primary:'.$caseId,
            'language_bucket' => self::requiredString($case['language_bucket'] ?? null, 'language_bucket'),
            'actor' => self::requiredString($case['preconditions']['actor'] ?? null, 'preconditions.actor'),
            'symbolic_order_reference' => is_string($case['preconditions']['symbolic_order_reference'] ?? null)
                ? $case['preconditions']['symbolic_order_reference'] : null,
            'candidate_request' => self::candidateRequest([
                'message' => self::requiredString($case['utterance'] ?? null, 'utterance'),
            ]),
            'branch_ids' => array_values(array_map(
                fn (array $branch): string => self::requiredString($branch['branch_id'] ?? null, 'branch_id'),
                $case['multi_intent_branches'] ?? []
            )),
        ];

        return $input;
    }

    /** @return array<string, mixed> */
    public static function scenarioTurnInput(array $scenario, array $turn): array
    {
        $scenarioId = self::requiredString($scenario['scenario_id'] ?? null, 'scenario_id');
        $turnId = $turn['turn'] ?? null;
        if (! is_int($turnId) || $turnId < 1) {
            throw new FinalV11ContractException('turn must be a positive integer.');
        }

        return [
            'record_type' => 'scenario_turn',
            'scenario_id' => $scenarioId,
            'turn_id' => $turnId,
            'session_id' => 'scenario:'.$scenarioId,
            'language_bucket' => self::requiredString($scenario['language_bucket'] ?? null, 'language_bucket'),
            'actor' => self::requiredString($scenario['preconditions']['actor'] ?? null, 'preconditions.actor'),
            'context_ttl_state' => ($scenario['preconditions']['context_ttl_state'] ?? 'active') === 'expired'
                ? 'expired' : 'active',
            'candidate_request' => self::candidateRequest([
                'message' => self::requiredString($turn['utterance'] ?? null, 'utterance'),
            ]),
        ];
    }

    /** @param array<string, mixed> $request @return array{message: string} */
    public static function candidateRequest(array $request): array
    {
        self::assertGoldIsolation($request);
        if (array_keys($request) !== ['message'] || ! is_string($request['message']) || $request['message'] === '') {
            throw new FinalV11ContractException('Candidate request must contain only one non-empty message.');
        }

        return ['message' => $request['message']];
    }

    /** @param array<string, mixed> $candidateInput */
    public static function assertGoldIsolation(array $candidateInput): void
    {
        $walk = function (mixed $value) use (&$walk): void {
            if (! is_array($value)) {
                return;
            }
            foreach ($value as $key => $item) {
                if (is_string($key) && in_array(strtolower($key), self::FORBIDDEN_CANDIDATE_INPUT_KEYS, true)) {
                    throw new FinalV11ContractException('Gold-derived candidate input is forbidden: '.$key.'.');
                }
                $walk($item);
            }
        };
        $walk($candidateInput);
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
            'evaluator_v2_r1' => self::EVALUATOR_R1_SHA256,
            'runtime_adapter_v2_r1' => self::ADAPTER_R1_SHA256,
            'scorer_v2_r1' => self::SCORER_R1_SHA256,
            'harness_r1' => self::HARNESS_R1_SHA256,
            'toolchain_v2_r1' => self::TOOLCHAIN_R1_SHA256,
            'replacement_execution_bundle_r1' => self::requiredString(
                $manifest['execution_bundle_semantic_identity'] ?? null,
                'execution_bundle_semantic_identity'
            ),
        ];
    }

    /** @return array<string, mixed> */
    public static function metadata(
        string $runId,
        string $startedAtUtc,
        ?string $completedAtUtc,
        int $candidateRequestCount
    ): array {
        return [
            'replacement_run_id' => $runId,
            'replacement_reason' => self::REPLACEMENT_REASON,
            'historical_run_id' => self::HISTORICAL_RUN_ID,
            ...self::identities(),
            'runtime_adapter_version' => FinalV11RuntimeAdapterV2R1::VERSION,
            'started_at_utc' => $startedAtUtc,
            'completed_at_utc' => $completedAtUtc,
            'candidate_request_count' => $candidateRequestCount,
            'manual_retry_count' => 0,
            'resume_count' => 0,
            'historical_capture_reused' => false,
        ];
    }

    /** @param array<string, mixed> $state */
    public static function assertScoringAllowed(array $state): void
    {
        if (($state['execution_state'] ?? null) !== 'CAPTURE_COMPLETED'
            || ($state['cases_started'] ?? null) !== ($state['cases_completed'] ?? null)
            || ($state['cases_failed'] ?? null) !== 0) {
            throw new FinalV11ContractException('Scoring is forbidden until the replacement capture is complete.');
        }
    }

    public static function assertReplacementCaptureSource(string $path): void
    {
        if ($path === base_path(self::HISTORICAL_RAW_CAPTURE_PATH)
            || hash_file('sha256', $path) === self::HISTORICAL_RAW_CAPTURE_SHA256) {
            throw new FinalV11ContractException('Historical raw capture cannot be replacement input.');
        }
    }

    /** @param array<string, mixed> $value */
    public static function deterministicHash(array $value): string
    {
        $strip = function (mixed $item) use (&$strip): mixed {
            if (! is_array($item)) {
                return $item;
            }
            $result = [];
            foreach ($item as $key => $child) {
                if (is_string($key) && in_array($key, self::NONDETERMINISTIC_KEYS, true)) {
                    continue;
                }
                $result[$key] = $strip($child);
            }

            return $result;
        };

        return hash('sha256', FinalV11EvaluationV2R1::canonicalJson($strip($value)));
    }

    public static function verifyFrozenIdentities(): void
    {
        $directory = base_path('docs/evaluation/v11-independent');
        $files = [
            $directory.'/v11-final-audited-dataset.json' => self::DATASET_SHA256,
            $directory.'/v11-r3-fresh-audit-report.md' => self::AUDIT_SHA256,
            $directory.'/v11-final-manifest.json' => self::MANIFEST_SHA256,
            $directory.'/v11-final-raw-capture.jsonl' => self::HISTORICAL_RAW_CAPTURE_SHA256,
            $directory.'/v11-final-execution-state.json' => self::HISTORICAL_STATE_SHA256,
        ];
        if (! hash_equals(self::CANDIDATE_SHA256, ChatFixtureAudit::runtimeHash())) {
            throw new FinalV11ContractException('Frozen candidate identity mismatch.');
        }
        foreach ($files as $path => $expected) {
            $actual = hash_file('sha256', $path);
            if (! is_string($actual) || ! hash_equals($expected, $actual)) {
                throw new FinalV11ContractException('Frozen artifact identity mismatch: '.$path);
            }
        }

        $toolchain = self::decodeObject($directory.'/v11-toolchain-v2-r1-manifest.json');
        $expectedComponents = [
            'evaluator_v2_r1' => self::EVALUATOR_R1_SHA256,
            'runtime_adapter_v2_r1' => self::ADAPTER_R1_SHA256,
            'scorer_v2_r1' => self::SCORER_R1_SHA256,
            'harness_r1' => self::HARNESS_R1_SHA256,
            'toolchain_v2_r1' => self::TOOLCHAIN_R1_SHA256,
        ];
        foreach ($expectedComponents as $key => $expected) {
            $component = $toolchain['revised_components'][$key] ?? null;
            if (! is_array($component) || ($component['semantic_identity'] ?? null) !== $expected) {
                throw new FinalV11ContractException('Frozen Toolchain V2-r1 manifest mismatch: '.$key);
            }
            $paths = array_map(
                fn (string $path): string => base_path($path),
                $component['ordered_semantic_files'] ?? []
            );
            if (! hash_equals($expected, FinalV11Evaluation::semanticHash($paths))) {
                throw new FinalV11ContractException('Frozen Toolchain V2-r1 source mismatch: '.$key);
            }
        }
    }

    public static function verifyBundleIdentity(): string
    {
        $manifest = self::bundleManifest();
        $files = $manifest['ordered_semantic_files'] ?? null;
        $expected = $manifest['execution_bundle_semantic_identity'] ?? null;
        if (! is_array($files) || ! array_is_list($files) || ! is_string($expected)) {
            throw new FinalV11ContractException('Invalid replacement execution-bundle manifest.');
        }
        $actual = FinalV11Evaluation::semanticHash(array_map(
            fn (string $path): string => base_path($path),
            $files
        ));
        if (! hash_equals($expected, $actual)) {
            throw new FinalV11ContractException('Replacement execution-bundle identity mismatch.');
        }

        return $actual;
    }

    /** @param array<string, mixed> $value */
    public static function writeJsonExclusive(string $path, array $value): string
    {
        return self::writeExclusive($path, FinalV11EvaluationV2R1::canonicalJson($value));
    }

    public static function writeTextExclusive(string $path, string $bytes): string
    {
        return self::writeExclusive($path, $bytes);
    }

    public static function freezeArtifact(string $path): string
    {
        if (! is_file($path)) {
            throw new FinalV11ContractException('Artifact does not exist: '.$path);
        }
        $hash = hash_file('sha256', $path);
        if (! is_string($hash) || ! chmod($path, 0444)) {
            throw new FinalV11ContractException('Unable to freeze artifact: '.$path);
        }

        return $hash;
    }

    private static function writeExclusive(string $path, string $bytes): string
    {
        if (file_exists($path)) {
            throw new FinalV11ContractException('Refusing to overwrite replacement execution artifact: '.$path);
        }
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            throw new FinalV11ContractException('Unable to create replacement execution artifact: '.$path);
        }
        try {
            if (! flock($handle, LOCK_EX) || fwrite($handle, $bytes) !== strlen($bytes)) {
                throw new FinalV11ContractException('Unable to persist replacement execution artifact: '.$path);
            }
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return self::freezeArtifact($path);
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
