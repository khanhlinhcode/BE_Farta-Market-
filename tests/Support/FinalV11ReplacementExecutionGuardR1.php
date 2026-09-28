<?php

namespace Tests\Support;

final class FinalV11ReplacementExecutionGuardR1
{
    public const VERSION = 'farta-v11-final-r1-replacement-execution-guard.1.0';

    /**
     * @param  array<string, string>  $identities
     * @return array<string, mixed>
     */
    public static function acquire(string $path, string $runId, array $identities, string $startedAtUtc): array
    {
        if ($runId === FinalV11ReplacementExecutionR1::HISTORICAL_RUN_ID) {
            throw new FinalV11ContractException('Replacement run ID must differ from the historical run ID.');
        }
        if (! is_dir(dirname($path)) || ! is_writable(dirname($path))) {
            throw new FinalV11ContractException('Replacement execution-state directory is unavailable.');
        }
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            throw new FinalV11ContractException('Replacement state already exists; retry and resume are forbidden.');
        }
        $state = [
            'schema_version' => self::VERSION,
            'run_id' => $runId,
            'replacement_reason' => FinalV11ReplacementExecutionR1::REPLACEMENT_REASON,
            'historical_run_id' => FinalV11ReplacementExecutionR1::HISTORICAL_RUN_ID,
            'execution_state' => 'EXECUTION_LOCK_ACQUIRED',
            'started_at_utc' => $startedAtUtc,
            'completed_at_utc' => null,
            'identities' => $identities,
            'cases_started' => 0,
            'cases_completed' => 0,
            'cases_failed' => 0,
            'candidate_request_count' => 0,
            'manual_retry_count' => 0,
            'resume_count' => 0,
            'historical_capture_reused' => false,
            'first_record' => null,
            'last_record' => null,
            'raw_capture' => null,
            'scored_results' => null,
            'failure' => null,
        ];
        try {
            if (! flock($handle, LOCK_EX)) {
                throw new FinalV11ContractException('Unable to lock replacement execution state.');
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
            $state['candidate_request_count']++;
            $state['first_record'] ??= $recordId;
            $state['last_record'] = $recordId;

            return $state;
        });
    }

    /** @return array<string, mixed> */
    public static function recordCompleted(string $path, string $recordId): array
    {
        return self::transition($path, ['EXECUTING'], function (array $state) use ($recordId): array {
            $state['cases_completed']++;
            $state['last_record'] = $recordId;

            return $state;
        });
    }

    /** @return array<string, mixed> */
    public static function recordFailed(string $path, string $recordId, string $failure): array
    {
        return self::transition($path, ['EXECUTING', 'CAPTURE_COMPLETED'], function (array $state) use ($recordId, $failure): array {
            $state['execution_state'] = 'EXECUTION_INCOMPLETE';
            $state['cases_failed']++;
            $state['last_record'] = $recordId;
            $state['failure'] = $failure;

            return $state;
        });
    }

    /** @param array{path: string, sha256: string} $rawCapture @return array<string, mixed> */
    public static function markCaptureCompleted(
        string $path,
        array $rawCapture,
        string $completedAtUtc
    ): array {
        return self::transition($path, ['EXECUTING'], function (array $state) use ($rawCapture, $completedAtUtc): array {
            if ($state['cases_started'] < 1
                || $state['cases_started'] !== $state['cases_completed']
                || $state['cases_failed'] !== 0) {
                throw new FinalV11ContractException('Cannot complete an incomplete replacement capture.');
            }
            $state['execution_state'] = 'CAPTURE_COMPLETED';
            $state['completed_at_utc'] = $completedAtUtc;
            $state['raw_capture'] = $rawCapture;

            return $state;
        });
    }

    /** @param array{path: string, sha256: string} $scoredResults @return array<string, mixed> */
    public static function completeScoring(string $path, array $scoredResults): array
    {
        return self::transition($path, ['CAPTURE_COMPLETED'], function (array $state) use ($scoredResults): array {
            $state['execution_state'] = 'COMPLETED';
            $state['scored_results'] = $scoredResults;

            return $state;
        });
    }

    /** @return array<string, mixed> */
    public static function read(string $path): array
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new FinalV11ContractException('Replacement execution state does not exist.');
        }
        $state = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($state) || array_is_list($state)
            || ($state['schema_version'] ?? null) !== self::VERSION
            || ($state['resume_count'] ?? null) !== 0
            || ($state['manual_retry_count'] ?? null) !== 0
            || ($state['historical_capture_reused'] ?? null) !== false) {
            throw new FinalV11ContractException('Invalid or resumable replacement execution state.');
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
            throw new FinalV11ContractException('Unable to lock replacement execution state.');
        }
        try {
            rewind($handle);
            $bytes = stream_get_contents($handle);
            $state = json_decode(is_string($bytes) ? $bytes : '', true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($state) || array_is_list($state)
                || ($state['schema_version'] ?? null) !== self::VERSION
                || ! in_array($state['execution_state'] ?? null, $allowedStates, true)) {
                throw new FinalV11ContractException('Invalid replacement execution-state transition; resume is forbidden.');
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
        $bytes = FinalV11EvaluationV2R1::canonicalJson($state);
        rewind($handle);
        if (! ftruncate($handle, 0) || fwrite($handle, $bytes) !== strlen($bytes)) {
            throw new FinalV11ContractException('Unable to persist replacement execution state.');
        }
    }
}
