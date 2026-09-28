<?php

use Tests\Support\FinalV10ContractException;
use Tests\Support\FinalV10DatasetContract;
use Tests\Support\FinalV10Evaluation;
use Tests\Support\FinalV10Metrics;
use Tests\Support\FinalV10OfflineScorer;
use Tests\Support\FinalV10PerfectMirror;
use Tests\Support\FinalV10RawCapture;

/** @param array<string, mixed> $metadata */
function v4MetadataLine(array $metadata): string
{
    return json_encode([
        'record_type' => 'metadata',
        'schema_version' => FinalV10RawCapture::SCHEMA_VERSION,
        'metadata' => $metadata,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
}

/** @return array<string, mixed> */
function v4FrozenContract(): array
{
    static $contract;

    return $contract ??= FinalV10DatasetContract::parse(json_decode(
        (string) file_get_contents(dirname(__DIR__, 2).'/docs/evaluation/v10-independent/v10-final-audited-dataset.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    ));
}

it('preserves a valid UTF-8 continuation byte 0x85 inside a JSONL record', function () {
    $text = 'ą';
    $jsonl = json_encode([
        'record_type' => 'metadata',
        'schema_version' => FinalV10RawCapture::SCHEMA_VERSION,
        'metadata' => ['text' => $text],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";

    expect(bin2hex($text))->toBe('c485')
        ->and(str_contains($jsonl, chr(0x85)))->toBeTrue()
        ->and(FinalV10RawCapture::deserialize($jsonl)['metadata']['text'])->toBe($text);
})->group('v10-scorer-v4');

it('implements strict LF JSONL framing without mutating scalar values', function (string $separator, bool $finalSeparator) {
    $metadata = [
        'ascii' => 'plain ASCII',
        'vietnamese' => 'Tiếng Việt có dấu',
        'english' => 'English text',
        'mixed' => 'VN và EN mixed',
        'emoji' => '🧪',
        'continuation_0x85' => 'ą',
        'escaped_newline' => "line one\nline two",
        'escaped_carriage_return' => "left\rright",
        'literal_unicode' => "NEL \u{0085}; separator \u{2028}",
        'empty_string' => '',
        'numeric_zero' => 0,
        'boolean_false' => false,
        'null_field' => null,
    ];
    $line = v4MetadataLine($metadata);
    $jsonl = $line.($finalSeparator ? $separator : '');

    expect($line)->toContain('\\n', '\\r')
        ->and(substr_count($jsonl, "\n"))->toBe($separator === "\r\n" && $finalSeparator ? 1 : ($separator === "\n" && $finalSeparator ? 1 : 0))
        ->and(FinalV10RawCapture::deserialize($jsonl)['metadata'])->toBe($metadata);
})->with([
    'LF with final separator' => ["\n", true],
    'CRLF with final separator' => ["\r\n", true],
    'legal final record without LF' => ["\n", false],
])->group('v10-scorer-v4');

it('rejects a blank JSONL record instead of skipping it', function () {
    $jsonl = v4MetadataLine(['run_id' => 'blank-line'])."\n\n";

    expect(fn () => FinalV10RawCapture::deserialize($jsonl))
        ->toThrow(FinalV10ContractException::class, 'record index 1 (line 2');
})->group('v10-scorer-v4');

it('rejects invalid UTF-8 before JSON decoding with record and byte location', function () {
    $valid = v4MetadataLine(['text' => 'marker']);
    $invalid = str_replace('marker', "bad\xFFbyte", $valid)."\n";

    expect(fn () => FinalV10RawCapture::deserialize($invalid))
        ->toThrow(FinalV10ContractException::class, 'record index 0 (line 1, byte offset 0) UTF-8 validation failed [INVALID_UTF8]');
})->group('v10-scorer-v4');

it('reports malformed JSON with record line byte offset and error type', function () {
    $first = v4MetadataLine(['run_id' => 'malformed-json']);
    $jsonl = $first."\n".'{"record_type":'."\n";

    expect(fn () => FinalV10RawCapture::deserialize($jsonl))
        ->toThrow(
            FinalV10ContractException::class,
            'record index 1 (line 2, byte offset '.(strlen($first) + 1).') JSON decode failed [JsonException'
        );
})->group('v10-scorer-v4');

it('parses all 372 frozen records in order with unique execution identities and semantic round trips', function () {
    $path = dirname(__DIR__, 2).'/docs/evaluation/v10-independent/v10-final-v3-raw-capture.jsonl';
    $bytes = (string) file_get_contents($path);
    $physicalLines = explode("\n", $bytes);
    expect(array_pop($physicalLines))->toBe('');

    $decodedLines = [];
    foreach ($physicalLines as $line) {
        $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        $deterministic = json_encode(
            $decoded,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );
        $decodedLines[] = json_decode($deterministic, true, 512, JSON_THROW_ON_ERROR);
    }

    $streamed = FinalV10RawCapture::read($path);
    $inMemory = FinalV10RawCapture::deserialize($bytes);
    $primaryIds = array_column($streamed['primary_cases'], 'case_id');
    $turnIds = array_map(
        fn (array $turn): string => $turn['scenario_id'].'/turn-'.$turn['turn'],
        $streamed['scenario_turns']
    );

    expect(hash('sha256', $bytes))->toBe('bcf07fa6a9c0e0554237fffad7e7806cef5271c0837a92fd197b61ca59ffe6d3')
        ->and($physicalLines)->toHaveCount(372)
        ->and($decodedLines)->toHaveCount(372)
        ->and($streamed)->toBe($inMemory)
        ->and($streamed['primary_cases'])->toHaveCount(292)
        ->and($streamed['scenario_turns'])->toHaveCount(78)
        ->and(array_unique($primaryIds))->toHaveCount(292)
        ->and(array_unique($turnIds))->toHaveCount(78)
        ->and($primaryIds[0])->toBe('V10-ST-0099')
        ->and($primaryIds[array_key_last($primaryIds)])->toBe('V10-ST-0190')
        ->and($turnIds[0])->toBe('V10-MT-035/turn-1')
        ->and($turnIds[array_key_last($turnIds)])->toBe('V10-MT-005/turn-2');
})->group('v10-scorer-v4');

it('keeps the perfect mirror at 307 of 307 claim-bearing records', function () {
    $contract = v4FrozenContract();
    $metrics = FinalV10Metrics::calculate(
        FinalV10OfflineScorer::score($contract, FinalV10PerfectMirror::capture($contract))
    );

    expect($metrics['claim_support_accuracy'])->toBe(['correct' => 307, 'total' => 307, 'value' => 1.0])
        ->and($metrics['intent']['accuracy']['value'])->toBe(1.0)
        ->and($metrics['handler_accuracy']['value'])->toBe(1.0)
        ->and($metrics['business_outcome_accuracy']['value'])->toBe(1.0)
        ->and($metrics['release_decision'])->toBe('BACKEND GO FOR CONTROLLED STAGING');
})->group('v10-scorer-v4');

it('rescoring one fixed offline artifact is byte and metric deterministic', function () {
    $contract = v4FrozenContract();
    $capture = FinalV10RawCapture::deserialize(
        FinalV10RawCapture::serialize(FinalV10PerfectMirror::capture($contract))
    );

    $firstScored = FinalV10Evaluation::serializeRawResults(FinalV10OfflineScorer::score($contract, $capture));
    $secondScored = FinalV10Evaluation::serializeRawResults(FinalV10OfflineScorer::score($contract, $capture));
    $firstMetrics = FinalV10Evaluation::serializeRawResults(FinalV10Metrics::calculate(
        FinalV10Evaluation::deserializeRawResults($firstScored)
    ));
    $secondMetrics = FinalV10Evaluation::serializeRawResults(FinalV10Metrics::calculate(
        FinalV10Evaluation::deserializeRawResults($secondScored)
    ));

    expect($secondScored)->toBe($firstScored)
        ->and($secondMetrics)->toBe($firstMetrics);
})->group('v10-scorer-v4');
