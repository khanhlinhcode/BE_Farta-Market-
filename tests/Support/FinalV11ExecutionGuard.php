<?php

namespace Tests\Support;

final class FinalV11ExecutionGuard
{
    public const VERSION = 'farta-v11-final-execution-guard.1.0';

    /**
     * @param  array<string, string>  $identities
     * @return array<string, mixed>
     */
    public static function acquire(string $path, string $runId, array $identities, string $startedAtUtc): array
    {
        if (! is_dir(dirname($path)) || ! is_writable(dirname($path))) {
            throw new FinalV11ContractException('Execution-state directory is unavailable.');
        }

        $handle = @fopen($path, 'x');
        if ($handle === false) {
            throw new FinalV11ContractException('V11 execution state already exists; refusing a second execution.');
        }

        $state = [
            'schema_version' => self::VERSION,
            'run_id' => $runId,
            'execution_state' => 'EXECUTION_LOCK_ACQUIRED',
            'started_at_utc' => $startedAtUtc,
            'completed_at_utc' => null,
            'identities' => $identities,
            'cases_started' => 0,
            'cases_completed' => 0,
            'cases_failed' => 0,
            'last_case' => null,
            'raw_capture' => null,
            'scored_results' => null,
            'failure' => null,
        ];

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new FinalV11ContractException('Unable to lock V11 execution state.');
            }
            self::writeHandle($handle, $state);
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return $state;
    }

    /** @return array<string, mixed> */
    public static function markExecuting(string $path): array
    {
        return self::transition($path, ['EXECUTION_LOCK_ACQUIRED'], function (array $state): array {
            $state['execution_state'] = 'EXECUTING';

            return $state;
        });
    }

    /** @return array<string, mixed> */
    public static function recordStarted(string $path, string $recordId): array
    {
        return self::transition($path, ['EXECUTING'], function (array $state) use ($recordId): array {
            $state['cases_started']++;
            $state['last_case'] = $recordId;

            return $state;
        });
    }

    /** @return array<string, mixed> */
    public static function recordCompleted(string $path, string $recordId): array
    {
        return self::transition($path, ['EXECUTING'], function (array $state) use ($recordId): array {
            $state['cases_completed']++;
            $state['last_case'] = $recordId;

            return $state;
        });
    }

    /** @return array<string, mixed> */
    public static function recordFailed(string $path, string $recordId, string $failure): array
    {
        return self::transition($path, ['EXECUTING'], function (array $state) use ($recordId, $failure): array {
            $state['execution_state'] = 'EXECUTION_INCOMPLETE';
            $state['cases_failed']++;
            $state['last_case'] = $recordId;
            $state['failure'] = $failure;

            return $state;
        });
    }

    /**
     * @param  array{path: string, sha256: string}  $rawCapture
     * @param  array{path: string, sha256: string}  $scoredResults
     * @return array<string, mixed>
     */
    public static function complete(
        string $path,
        array $rawCapture,
        array $scoredResults,
        string $completedAtUtc
    ): array {
        return self::transition($path, ['EXECUTING'], function (array $state) use ($rawCapture, $scoredResults, $completedAtUtc): array {
            $state['execution_state'] = 'COMPLETED';
            $state['completed_at_utc'] = $completedAtUtc;
            $state['raw_capture'] = $rawCapture;
            $state['scored_results'] = $scoredResults;

            return $state;
        });
    }

    /** @return array<string, mixed> */
    public static function read(string $path): array
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new FinalV11ContractException('V11 execution state does not exist.');
        }
        $state = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($state) || array_is_list($state)
            || ($state['schema_version'] ?? null) !== self::VERSION) {
            throw new FinalV11ContractException('Invalid V11 execution state.');
        }

        return $state;
    }

    /**
     * @param  array<int, string>  $allowedStates
     * @param  callable(array<string, mixed>): array<string, mixed>  $change
     * @return array<string, mixed>
     */
    private static function transition(string $path, array $allowedStates, callable $change): array
    {
        $handle = @fopen($path, 'c+');
        if ($handle === false || ! flock($handle, LOCK_EX)) {
            throw new FinalV11ContractException('Unable to lock V11 execution state.');
        }

        try {
            rewind($handle);
            $bytes = stream_get_contents($handle);
            $state = json_decode(is_string($bytes) ? $bytes : '', true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($state) || array_is_list($state)
                || ($state['schema_version'] ?? null) !== self::VERSION) {
                throw new FinalV11ContractException('Invalid V11 execution state.');
            }
            if (! in_array($state['execution_state'] ?? null, $allowedStates, true)) {
                throw new FinalV11ContractException('Invalid V11 execution-state transition.');
            }
            $state = $change($state);
            self::writeHandle($handle, $state);
            fflush($handle);

            return $state;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @param resource $handle @param array<string, mixed> $state */
    private static function writeHandle($handle, array $state): void
    {
        $bytes = FinalV11EvaluationV2::canonicalJson($state);
        rewind($handle);
        if (! ftruncate($handle, 0) || fwrite($handle, $bytes) !== strlen($bytes)) {
            throw new FinalV11ContractException('Unable to persist V11 execution state.');
        }
    }
}
