<?php

namespace App\Support;

use InvalidArgumentException;
use LogicException;

final class Vnd
{
    public static function fromDecimal(int|string $value): int
    {
        $normalized = trim((string) $value);
        if (! preg_match('/^(\d+)(?:\.(\d+))?$/', $normalized, $matches)) {
            throw new InvalidArgumentException('Invalid VND amount.');
        }

        $whole = ltrim($matches[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        $max = (string) PHP_INT_MAX;
        if (strlen($whole) > strlen($max)
            || (strlen($whole) === strlen($max) && strcmp($whole, $max) > 0)) {
            throw new InvalidArgumentException('VND amount exceeds platform integer range.');
        }

        $amount = (int) $whole;
        $fraction = $matches[2] ?? '';

        if ($fraction !== '' && (int) $fraction[0] >= 5 && $amount === PHP_INT_MAX) {
            throw new InvalidArgumentException('VND amount exceeds platform integer range.');
        }

        return $amount + ($fraction !== '' && (int) $fraction[0] >= 5 ? 1 : 0);
    }

    public static function decimal(int $amount): string
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('VND amount cannot be negative.');
        }

        return $amount.'.00';
    }

    public static function canonicalDecimal(int|string|null $legacy, int|string|null $canonical): ?string
    {
        if ($canonical === null) {
            return $legacy === null ? null : self::decimal(self::fromDecimal($legacy));
        }

        $amount = self::fromDecimal($canonical);
        if ($legacy !== null && self::fromDecimal($legacy) !== $amount) {
            throw new LogicException('Legacy and canonical VND amounts do not match.');
        }

        return self::decimal($amount);
    }

    public static function percentageDiscount(int $amount, int|string $percentage): int
    {
        $normalized = trim((string) $percentage);
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $normalized, $matches)) {
            throw new InvalidArgumentException('Invalid percentage.');
        }

        $basisPoints = ((int) $matches[1] * 100)
            + (int) str_pad($matches[2] ?? '', 2, '0');

        return intdiv(($amount * $basisPoints) + 5000, 10000);
    }
}
