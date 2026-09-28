<?php

namespace Tests\Support;

final class FinalV11RawCapture
{
    public const SCHEMA_VERSION = 'farta-v11-evaluator-v1-raw-capture.1.0';

    private const FORBIDDEN_KEYS = [
        'authorization', 'cookie', 'password', 'password_confirmation', 'secret',
        'token', 'access_token', 'refresh_token', 'api_key',
    ];

    /** @param array<string, mixed> $metadata */
    public static function initialize(string $path, array $metadata): void
    {
        if (file_exists($path)) {
            throw new FinalV11ContractException('Raw capture already exists; refusing overwrite: '.$path);
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
        self::append($path, 'primary', $record);
    }

    /** @param array<string, mixed> $record */
    public static function appendScenarioTurn(string $path, array $record): void
    {
        self::append($path, 'scenario_turn', $record);
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
    public static function read(string $path): array
    {
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new FinalV11ContractException('Unable to read raw capture: '.$path);
        }

        return self::deserialize($bytes);
    }

    /** @return array<string, mixed> */
    public static function deserialize(string $jsonLines): array
    {
        if ($jsonLines === '') {
            throw new FinalV11ContractException('Raw capture is empty.');
        }
        $records = [];
        $length = strlen($jsonLines);
        $offset = 0;
        $lineNumber = 1;
        while ($offset < $length) {
            $lf = strpos($jsonLines, "\n", $offset);
            $terminated = $lf !== false;
            $end = $terminated ? $lf : $length;
            $line = substr($jsonLines, $offset, $end - $offset);
            if ($terminated && str_ends_with($line, "\r")) {
                $line = substr($line, 0, -1);
            }
            $records[] = self::decodeLine($line, $lineNumber, $offset);
            $lineNumber++;
            $offset = $terminated ? $end + 1 : $length;
        }

        return self::assemble($records);
    }

    /** @param array<string, mixed> $document */
    public static function validateDocument(array $document): void
    {
        if (($document['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new FinalV11ContractException('$.schema_version: invalid V11 raw-capture schema.');
        }
        if (! is_array($document['metadata'] ?? null) || array_is_list($document['metadata'])) {
            throw new FinalV11ContractException('$.metadata must be an object.');
        }
        foreach (['primary_cases', 'scenario_turns'] as $field) {
            if (! is_array($document[$field] ?? null) || ! array_is_list($document[$field])) {
                throw new FinalV11ContractException('$.'.$field.' must be an array.');
            }
        }

        $primaryIds = [];
        foreach ($document['primary_cases'] as $index => $record) {
            self::validateRecord($record, '$.primary_cases['.$index.']');
            $id = $record['case_id'] ?? null;
            if (! is_string($id) || $id === '' || isset($primaryIds[$id])) {
                throw new FinalV11ContractException('Invalid or duplicate primary case_id at index '.$index.'.');
            }
            $primaryIds[$id] = true;
        }

        $turnIds = [];
        foreach ($document['scenario_turns'] as $index => $record) {
            self::validateRecord($record, '$.scenario_turns['.$index.']');
            $scenarioId = $record['scenario_id'] ?? null;
            $turn = $record['turn'] ?? null;
            if (! is_string($scenarioId) || $scenarioId === '' || ! is_int($turn) || $turn < 1) {
                throw new FinalV11ContractException('Invalid scenario turn identity at index '.$index.'.');
            }
            $id = $scenarioId.'/turn-'.$turn;
            if (isset($turnIds[$id])) {
                throw new FinalV11ContractException('Duplicate scenario turn '.$id.'.');
            }
            $turnIds[$id] = true;
        }
    }

    /** @param array<string, mixed> $record */
    private static function append(string $path, string $type, array $record): void
    {
        self::validateRecord($record, '$.append');
        self::writeLine($path, [
            'record_type' => $type,
            'record' => self::sanitize($record),
        ], true);
    }

    /** @return array<string, mixed> */
    private static function decodeLine(string $line, int $lineNumber, int $offset): array
    {
        if ($line === '') {
            throw new FinalV11ContractException('Blank JSONL record at line '.$lineNumber.', byte '.$offset.'.');
        }
        if (preg_match('//u', $line) !== 1) {
            throw new FinalV11ContractException('Invalid UTF-8 at line '.$lineNumber.', byte '.$offset.'.');
        }
        try {
            $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new FinalV11ContractException(
                'JSON decode failed at line '.$lineNumber.', byte '.$offset.': '.$exception->getMessage(),
                previous: $exception
            );
        }
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new FinalV11ContractException('JSONL line '.$lineNumber.' must be an object.');
        }

        return $decoded;
    }

    /** @param array<int, array<string, mixed>> $records @return array<string, mixed> */
    private static function assemble(array $records): array
    {
        $first = $records[0];
        if (($first['record_type'] ?? null) !== 'metadata'
            || ($first['schema_version'] ?? null) !== self::SCHEMA_VERSION
            || ! is_array($first['metadata'] ?? null)
            || array_is_list($first['metadata'])) {
            throw new FinalV11ContractException('JSONL line 1 must be V11 metadata.');
        }
        $document = [
            'schema_version' => self::SCHEMA_VERSION,
            'metadata' => $first['metadata'],
            'primary_cases' => [],
            'scenario_turns' => [],
        ];
        foreach (array_slice($records, 1) as $index => $decoded) {
            if (! is_array($decoded['record'] ?? null) || array_is_list($decoded['record'])) {
                throw new FinalV11ContractException('JSONL record '.($index + 2).' is missing its object payload.');
            }
            $field = match ($decoded['record_type'] ?? null) {
                'primary' => 'primary_cases',
                'scenario_turn' => 'scenario_turns',
                default => throw new FinalV11ContractException('Unknown JSONL record type at line '.($index + 2).'.'),
            };
            $document[$field][] = $decoded['record'];
        }
        self::validateDocument($document);

        return $document;
    }

    private static function validateRecord(mixed $record, string $path): void
    {
        if (! is_array($record) || array_is_list($record)) {
            throw new FinalV11ContractException($path.' must be an object.');
        }
        if (array_key_exists('prediction', $record)
            && $record['prediction'] !== null
            && (! is_array($record['prediction']) || array_is_list($record['prediction']))) {
            throw new FinalV11ContractException($path.'.prediction must be an object or null.');
        }
        if (array_key_exists('runtime_error', $record)
            && $record['runtime_error'] !== null
            && ! is_string($record['runtime_error'])) {
            throw new FinalV11ContractException($path.'.runtime_error must be string or null.');
        }
    }

    /** @param array<string, mixed> $record */
    private static function writeLine(string $path, array $record, bool $append): void
    {
        $written = file_put_contents($path, self::encode($record)."\n", ($append ? FILE_APPEND : 0) | LOCK_EX);
        if ($written === false) {
            throw new FinalV11ContractException('Unable to persist raw capture: '.$path);
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
