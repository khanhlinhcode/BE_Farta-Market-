<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReencryptUserSecrets extends Command
{
    protected $signature = 'security:reencrypt-user-secrets {--dry-run : Decrypt every value without writing it back}';

    protected $description = 'Re-encrypt MFA secrets with the current application key without exposing plaintext';

    public function handle(): int
    {
        $processed = 0;
        $dryRun = (bool) $this->option('dry-run');

        try {
            $process = function () use (&$processed, $dryRun): void {
                User::query()->where(function ($query): void {
                    $query->whereNotNull('mfa_secret')->orWhereNotNull('mfa_recovery_codes');
                })->eachById(function (User $user) use (&$processed, $dryRun): void {
                    $attributes = [];

                    if ($user->getRawOriginal('mfa_secret') !== null) {
                        $attributes['mfa_secret'] = $user->mfa_secret;
                    }
                    if ($user->getRawOriginal('mfa_recovery_codes') !== null) {
                        $attributes['mfa_recovery_codes'] = $user->mfa_recovery_codes;
                    }

                    if (! $dryRun) {
                        User::withoutTimestamps(fn () => $user->forceFill($attributes)->saveQuietly());
                    }

                    $processed++;
                });
            };

            if ($dryRun) {
                $process();
            } else {
                DB::transaction($process);
            }
        } catch (Throwable $exception) {
            $this->error('Re-encryption stopped after a decryption or persistence failure; writes were rolled back.');
            $this->line('Records checked before failure: '.$processed);
            $this->line('Error type: '.$exception::class);

            return self::FAILURE;
        }

        $this->info(($dryRun ? 'Dry run checked ' : 'Re-encrypted ').$processed.' user secret record(s).');

        return self::SUCCESS;
    }
}
