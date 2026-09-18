<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

final class AnalyticsSessionService
{
    /** @return array{token: string, visitor_id: string, session_id: string, expires_at: string} */
    public function issue(string $browserSessionId): array
    {
        $expiresAt = now()->addMinutes(max(5, (int) config('services.analytics.token_ttl_minutes', 30)));
        $payload = [
            'visitor_id' => (string) Str::uuid(),
            'session_id' => (string) Str::uuid(),
            'session_binding' => $this->sessionBinding($browserSessionId),
            'expires_at' => $expiresAt->timestamp,
        ];

        return [
            'token' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
            'visitor_id' => $payload['visitor_id'],
            'session_id' => $payload['session_id'],
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /** @return array{visitor_id: string, session_id: string, expires_at: int}|null */
    public function verify(?string $token, string $browserSessionId): ?array
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        if (! is_array($payload)
            || ! Str::isUuid($payload['visitor_id'] ?? null)
            || ! Str::isUuid($payload['session_id'] ?? null)
            || ! is_int($payload['expires_at'] ?? null)
            || $payload['expires_at'] < now()->timestamp
            || ! is_string($payload['session_binding'] ?? null)
            || ! hash_equals($payload['session_binding'], $this->sessionBinding($browserSessionId))) {
            return null;
        }

        return [
            'visitor_id' => $payload['visitor_id'],
            'session_id' => $payload['session_id'],
            'expires_at' => $payload['expires_at'],
        ];
    }

    private function sessionBinding(string $browserSessionId): string
    {
        return hash_hmac('sha256', $browserSessionId, (string) config('app.key'));
    }
}
