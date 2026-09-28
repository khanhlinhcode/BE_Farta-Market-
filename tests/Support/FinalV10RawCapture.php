<?php

namespace Tests\Support;

final class FinalV10RawCapture
{
    public const SCHEMA_VERSION = 'farta-v10-evaluator-v3-raw-capture.1.0';

    private const FORBIDDEN_KEYS = [
        'authorization', 'cookie', 'password', 'password_confirmation', 'secret',
        'token', 'access_token', 'refresh_token', 'api_key',
    ];

    /** @param array<string, mixed> $metadata */
    public static function initialize(string $path, array $metadata): void
    {
        if (file_exists($path)) {
            throw new FinalV10ContractException('Raw capture already exists; refusing overwrite: '.$path);
        }
        self::writeLine($path, [
            'record_type' => 'metadata',
            'schema_version' => self::SCHEMA_VERSION,
            'metadata' => self::sanitize($metadata),
        ], false);
    }

    /** @param array<string, mixed> $record */
    public static function appendPrimary(string $path, array $record): void
    {
        self::validateIdentity($record, 'case_id', '$.primary_capture');
        self::writeLine($path, [
            'record_type' => 'primary',
            'record' => self::sanitize($record),
        ], true);
    }

    /** @param array<string, mixed> $record */
    public static function appendScenarioTurn(string $path, array $record): void
    {
        self::validateIdentity($record, 'scenario_id', '$.scenario_turn_capture');
        if (! is_int($record['turn'] ?? null) || $record['turn'] < 1) {
            throw new FinalV10ContractException('$.scenario_turn_capture.turn: must be a positive integer');
        }
        self::writeLine($path, [
            'record_type' => 'scenario_turn',
            'record' => self::sanitize($record),
        ], true);
    }

    /** @param array<string, mixed> $metadata */
    public static function appendCompletion(string $path, array $metadata): void
    {
        self::writeLine($path, [
            'record_type' => 'completion',
            'metadata' => self::sanitize($metadata),
        ], true);
    }

    /** @return array<string, mixed> */
    public static function read(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new FinalV10ContractException('Unable to read raw capture: '.$path);
        }

        $records = [];
        $lineNumber = 1;
        $byteOffset = 0;
        try {
            while (($rawLine = fgets($handle)) !== false) {
                $terminatedByLf = str_ends_with($rawLine, "\n");
                $line = $terminatedByLf ? substr($rawLine, 0, -1) : $rawLine;
                $records[] = [
                    'line' => self::removeCrLfCarriageReturn($line, $terminatedByLf),
                    'line_number' => $lineNumber,
                    'byte_offset' => $byteOffset,
                ];
                $lineNumber++;
                $byteOffset += strlen($rawLine);
            }
            if (! feof($handle)) {
                throw new FinalV10ContractException('Unable to finish reading raw capture: '.$path);
            }
        } finally {
            fclose($handle);
        }

        return self::deserializeRecords($records);
    }

    /** @param array<string, mixed> $document */
    public static function serialize(array $document): string
    {
        self::validateDocument($document);

        $lines = [self::encode([
            'record_type' => 'metadata',
            'schema_version' => self::SCHEMA_VERSION,
            'metadata' => self::sanitize($document['metadata']),
        ])];
        foreach ($document['primary_cases'] as $record) {
            $lines[] = self::encode(['record_type' => 'primary', 'record' => self::sanitize($record)]);
        }
        foreach ($document['scenario_turns'] as $record) {
            $lines[] = self::encode(['record_type' => 'scenario_turn', 'record' => self::sanitize($record)]);
        }

        return implode("\n", $lines)."\n";
    }

    /** @return array<string, mixed> */
    public static function deserialize(string $jsonLines): array
    {
        $records = [];
        $length = strlen($jsonLines);
        $lineNumber = 1;
        $byteOffset = 0;
        while ($byteOffset < $length) {
            $lfOffset = strpos($jsonLines, "\n", $byteOffset);
            $terminatedByLf = $lfOffset !== false;
            $lineEnd = $terminatedByLf ? $lfOffset : $length;
            $line = substr($jsonLines, $byteOffset, $lineEnd - $byteOffset);
            $records[] = [
                'line' => self::removeCrLfCarriageReturn($line, $terminatedByLf),
                'line_number' => $lineNumber,
                'byte_offset' => $byteOffset,
            ];
            $lineNumber++;
            $byteOffset = $terminatedByLf ? $lineEnd + 1 : $length;
        }

        return self::deserializeRecords($records);
    }

    /**
     * @param  array<int, array{line: string, line_number: int, byte_offset: int}>  $records
     * @return array<string, mixed>
     */
    private static function deserializeRecords(array $records): array
    {
        if ($records === []) {
            throw new FinalV10ContractException('Raw capture is empty.');
        }
        $document = [
            'schema_version' => self::SCHEMA_VERSION,
            'metadata' => null,
            'primary_cases' => [],
            'scenario_turns' => [],
        ];
        foreach ($records as $recordIndex => $record) {
            $line = $record['line'];
            $lineNumber = $record['line_number'];
            $byteOffset = $record['byte_offset'];
            if ($line === '') {
                throw new FinalV10ContractException(
                    'Raw capture record index '.$recordIndex.' (line '.$lineNumber.', byte offset '.$byteOffset.') is blank.'
                );
            }
            if (preg_match('//u', $line) !== 1) {
                throw new FinalV10ContractException(
                    'Raw capture record index '.$recordIndex.' (line '.$lineNumber.', byte offset '.$byteOffset.') UTF-8 validation failed [INVALID_UTF8].'
                );
            }
            try {
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new FinalV10ContractException(
                    'Raw capture record index '.$recordIndex.' (line '.$lineNumber.', byte offset '.$byteOffset.') JSON decode failed ['.$exception::class.' code '.$exception->getCode().']: '.$exception->getMessage(),
                    previous: $exception
                );
            }
            if (! is_array($decoded) || array_is_list($decoded)) {
                throw new FinalV10ContractException('Raw capture line '.$lineNumber.' must be a JSON object.');
            }
            $type = $decoded['record_type'] ?? null;
            if ($recordIndex === 0) {
                if ($type !== 'metadata' || ($decoded['schema_version'] ?? null) !== self::SCHEMA_VERSION || ! is_array($decoded['metadata'] ?? null)) {
                    throw new FinalV10ContractException('Raw capture line 1 must be the V3 metadata record.');
                }
                $document['metadata'] = $decoded['metadata'];

                continue;
            }
            if (! is_array($decoded['record'] ?? null)) {
                if ($type === 'completion' && is_array($decoded['metadata'] ?? null)) {
                    $document['metadata'] = [...$document['metadata'], ...$decoded['metadata']];

                    continue;
                }
                throw new FinalV10ContractException('Raw capture line '.$lineNumber.' is missing its record object.');
            }
            if ($type === 'primary') {
                $document['primary_cases'][] = $decoded['record'];
            } elseif ($type === 'scenario_turn') {
                $document['scenario_turns'][] = $decoded['record'];
            } else {
                throw new FinalV10ContractException('Raw capture line '.$lineNumber.' has unknown record_type.');
            }
        }
        self::validateDocument($document);

        return $document;
    }

    private static function removeCrLfCarriageReturn(string $line, bool $terminatedByLf): string
    {
        return $terminatedByLf && str_ends_with($line, "\r") ? substr($line, 0, -1) : $line;
    }

    /** @param array<string, mixed> $document */
    public static function validateDocument(array $document): void
    {
        if (($document['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new FinalV10ContractException('$.schema_version: invalid raw-capture schema');
        }
        if (! is_array($document['metadata'] ?? null) || array_is_list($document['metadata'])) {
            throw new FinalV10ContractException('$.metadata: must be an object');
        }
        foreach (['primary_cases', 'scenario_turns'] as $field) {
            if (! is_array($document[$field] ?? null) || ! array_is_list($document[$field])) {
                throw new FinalV10ContractException('$.'.$field.': must be an array');
            }
        }
        $primaryIds = [];
        foreach ($document['primary_cases'] as $index => $record) {
            if (! is_array($record) || array_is_list($record)) {
                throw new FinalV10ContractException('$.primary_cases['.$index.']: must be an object');
            }
            self::validateIdentity($record, 'case_id', '$.primary_cases['.$index.']');
            if (isset($primaryIds[$record['case_id']])) {
                throw new FinalV10ContractException('$.primary_cases['.$index.'].case_id: duplicate capture ID');
            }
            $primaryIds[$record['case_id']] = true;
            self::validateCapturePayload($record, '$.primary_cases['.$index.']');
        }
        $turnIds = [];
        foreach ($document['scenario_turns'] as $index => $record) {
            if (! is_array($record) || array_is_list($record)) {
                throw new FinalV10ContractException('$.scenario_turns['.$index.']: must be an object');
            }
            self::validateIdentity($record, 'scenario_id', '$.scenario_turns['.$index.']');
            if (! is_int($record['turn'] ?? null) || $record['turn'] < 1) {
                throw new FinalV10ContractException('$.scenario_turns['.$index.'].turn: must be a positive integer');
            }
            $id = $record['scenario_id'].'/turn-'.$record['turn'];
            if (isset($turnIds[$id])) {
                throw new FinalV10ContractException('$.scenario_turns['.$index.']: duplicate turn capture ID');
            }
            $turnIds[$id] = true;
            self::validateCapturePayload($record, '$.scenario_turns['.$index.']');
        }
    }

    /** @param array<string, mixed> $record */
    private static function validateCapturePayload(array $record, string $path): void
    {
        if (array_key_exists('prediction', $record) && $record['prediction'] !== null && ! is_array($record['prediction'])) {
            throw new FinalV10ContractException($path.'.prediction: must be object or null');
        }
        if (array_key_exists('runtime_error', $record) && $record['runtime_error'] !== null && ! is_string($record['runtime_error'])) {
            throw new FinalV10ContractException($path.'.runtime_error: must be string or null');
        }
        if (array_key_exists('latency_ms', $record) && (! is_array($record['latency_ms']) || array_is_list($record['latency_ms']))) {
            throw new FinalV10ContractException($path.'.latency_ms: must be an object');
        }
    }

    /** @param array<string, mixed> $record */
    private static function validateIdentity(array $record, string $key, string $path): void
    {
        if (! is_string($record[$key] ?? null) || $record[$key] === '') {
            throw new FinalV10ContractException($path.'.'.$key.': must be a non-empty string');
        }
    }

    /** @param array<string, mixed> $record */
    private static function writeLine(string $path, array $record, bool $append): void
    {
        $bytes = file_put_contents(
            $path,
            self::encode($record)."\n",
            ($append ? FILE_APPEND : 0) | LOCK_EX
        );
        if ($bytes === false) {
            throw new \RuntimeException('Unable to persist raw capture: '.$path);
        }
    }

    /** @param array<string, mixed> $record */
    private static function encode(array $record): string
    {
        return json_encode(
            $record,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );
    }

    private static function sanitize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return is_object($value) ? self::sanitize((array) $value) : $value;
        }
        $sanitized = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && in_array(strtolower($key), self::FORBIDDEN_KEYS, true)) {
                continue;
            }
            $sanitized[$key] = self::sanitize($item);
        }

        return $sanitized;
    }
}
