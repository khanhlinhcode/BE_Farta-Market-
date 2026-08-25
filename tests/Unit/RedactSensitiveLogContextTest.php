<?php

use App\Logging\RedactSensitiveLogContext;
use Illuminate\Log\Logger as LaravelLogger;
use Monolog\Handler\TestHandler;
use Monolog\Logger;

test('sensitive log context is redacted before handlers receive it', function () {
    $handler = new TestHandler();
    $monolog = new Logger('test');
    $monolog->pushHandler($handler);

    (new RedactSensitiveLogContext())(new LaravelLogger($monolog));

    $monolog->warning('Checkout failed', [
        'order_id' => 123,
        'email' => 'customer@example.test',
        'payment' => ['signature' => 'do-not-log'],
    ]);

    $context = $handler->getRecords()[0]->context;

    expect($context)->toMatchArray([
        'order_id' => 123,
        'email' => '[REDACTED]',
        'payment' => ['signature' => '[REDACTED]'],
    ]);
});
