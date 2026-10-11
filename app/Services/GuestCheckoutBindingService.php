<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

final class GuestCheckoutBindingService
{
    public const COOKIE = 'farta_guest_checkout';

    /** @return array{token: string, hash: string, expires_at: string} */
    public function rotate(?string $currentToken = null): array
    {
        if (is_string($currentToken) && $currentToken !== '') {
            Cache::forget($this->cacheKey($this->hash($currentToken)));
        }

        $token = Str::random(64);
        $hash = $this->hash($token);
        $expiresAt = now()->addMinutes($this->ttlMinutes());
        Cache::put($this->cacheKey($hash), true, $expiresAt);

        return [
            'token' => $token,
            'hash' => $hash,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function verify(?string $token): ?string
    {
        if (! is_string($token) || ! preg_match('/^[A-Za-z0-9]{64}$/', $token)) {
            return null;
        }

        $hash = $this->hash($token);

        return Cache::has($this->cacheKey($hash)) ? $hash : null;
    }

    public function cookie(string $token): SymfonyCookie
    {
        return Cookie::make(
            self::COOKIE,
            $token,
            $this->ttlMinutes(),
            '/api',
            null,
            app()->isProduction() ? true : null,
            true,
            false,
            'lax'
        );
    }

    private function hash(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    private function cacheKey(string $hash): string
    {
        return 'guest-checkout-binding:'.$hash;
    }

    private function ttlMinutes(): int
    {
        return max(5, (int) config('api.guest_binding_ttl_minutes', 1440));
    }
}
