<?php

namespace App\Support;

final class AnalyticsIdentifier
{
    public static function hash(string $value): string
    {
        return hash_hmac('sha256', $value, 'farta-analytics|'.config('app.key'));
    }
}
