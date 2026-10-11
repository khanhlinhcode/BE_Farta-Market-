<?php

use App\Support\Vnd;

test('vnd parses legacy decimal storage without floating point arithmetic', function () {
    expect(Vnd::fromDecimal('90000.00'))->toBe(90000)
        ->and(Vnd::fromDecimal('90000.49'))->toBe(90000)
        ->and(Vnd::fromDecimal('90000.50'))->toBe(90001);
});

test('vnd calculates decimal percentage discounts as integer amounts', function () {
    expect(Vnd::percentageDiscount(100000, '10.00'))->toBe(10000)
        ->and(Vnd::percentageDiscount(99999, '12.50'))->toBe(12500);
});

test('vnd conversion rejects platform integer overflow', function () {
    expect(fn () => Vnd::fromDecimal(((string) PHP_INT_MAX).'0'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => Vnd::fromDecimal(((string) PHP_INT_MAX).'.50'))
        ->toThrow(InvalidArgumentException::class);
});
