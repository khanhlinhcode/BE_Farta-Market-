<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

class RedactSensitiveLogContext
{
    private const SENSITIVE_KEY_PATTERN = '/(?:address|api[_-]?key|authorization|cookie|csrf|email|name|note|password|phone|recipient|request|secret|signature|token|user)/i';

    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(function (LogRecord $record): LogRecord {
            $extra = $this->redact($record->extra);
            $requestId = app()->bound('request')
                ? app('request')->attributes->get('request_id')
                : null;

            if (is_string($requestId) && $requestId !== '') {
                $extra['request_id'] = $requestId;
            }

            return $record->with(
                context: $this->redact($record->context),
                extra: $extra,
            );
        });
    }

    private function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (preg_match(self::SENSITIVE_KEY_PATTERN, (string) $key) === 1) {
                $context[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $context[$key] = $this->redact($value);
            }
        }

        return $context;
    }
}
