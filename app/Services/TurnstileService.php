<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class TurnstileService
{
    public function verify(?string $token, ?string $ip): bool
    {
        if (! config('services.turnstile.required')) {
            return true;
        }

        $secret = config('services.turnstile.secret_key');
        if (! is_string($secret) || $secret === '' || ! is_string($token) || $token === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post((string) config('services.turnstile.verify_url'), [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]);
        } catch (ConnectionException) {
            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }
}
