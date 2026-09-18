<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

final class AdminMfaService
{
    public function __construct(private readonly Google2FA $totp) {}

    public function generateSecret(): string
    {
        return $this->totp->generateSecretKey(32);
    }

    public function provisioningUri(User $user, string $secret): string
    {
        return $this->totp->getQRCodeUrl(
            (string) config('app.name', 'Farta Market'),
            $user->email,
            $secret
        );
    }

    public function consumeTotp(User $user, string $code): int|false
    {
        if (! is_string($user->mfa_secret) || $user->mfa_secret === '') {
            return false;
        }

        $timestep = $this->totp->verifyKeyNewer(
            $user->mfa_secret,
            $code,
            $user->mfa_last_used_timestep ?? 0,
            1
        );
        if ($timestep === false) {
            return false;
        }

        $claimed = User::query()
            ->whereKey($user->id)
            ->where(function ($query) use ($timestep) {
                $query->whereNull('mfa_last_used_timestep')
                    ->orWhere('mfa_last_used_timestep', '<', $timestep);
            })
            ->update(['mfa_last_used_timestep' => $timestep]);

        if ($claimed !== 1) {
            return false;
        }

        $user->setAttribute('mfa_last_used_timestep', $timestep);
        $user->syncOriginalAttribute('mfa_last_used_timestep');

        return $timestep;
    }

    /** @return array<int, string> */
    public function newRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    /** @param array<int, string> $codes */
    public function recoveryCodeHashes(array $codes): array
    {
        return array_map(fn (string $code) => $this->recoveryCodeHash($code), $codes);
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $candidate = $this->recoveryCodeHash($code);

        return DB::transaction(function () use ($user, $candidate): bool {
            $lockedUser = User::query()->lockForUpdate()->find($user->id);
            $hashes = is_array($lockedUser?->mfa_recovery_codes) ? $lockedUser->mfa_recovery_codes : [];

            foreach ($hashes as $index => $hash) {
                if (is_string($hash) && hash_equals($hash, $candidate)) {
                    unset($hashes[$index]);
                    $remaining = array_values($hashes);
                    $lockedUser->forceFill(['mfa_recovery_codes' => $remaining])->save();
                    $user->setAttribute('mfa_recovery_codes', $remaining);
                    $user->syncOriginalAttribute('mfa_recovery_codes');

                    return true;
                }
            }

            return false;
        });
    }

    private function recoveryCodeHash(string $code): string
    {
        return hash_hmac('sha256', Str::upper(trim($code)), (string) config('app.key'));
    }
}
