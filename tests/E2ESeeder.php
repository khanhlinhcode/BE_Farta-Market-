<?php

namespace Tests;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Seeder;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;

class E2ESeeder extends Seeder
{
    public function run(): void
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');
        $testTarget = config('database.default') === 'mysql'
            ? preg_match('/^sivi_e2e_[a-z0-9_]+$/', $database)
            : str_starts_with(basename(dirname($database)), 'sivi-e2e-');
        if (! app()->environment('testing') || ! $testTarget) {
            throw new RuntimeException('Refusing to seed outside an isolated E2E database.');
        }
        $this->call(DatabaseSeeder::class);
        $totp = app(Google2FA::class);
        foreach (['customer', 'second-customer', 'staff', 'admin'] as $name) {
            $isAdminAccount = in_array($name, ['staff', 'admin'], true);
            User::updateOrCreate(['email' => 'qa.'.$name.'@example.test'], [
                'name' => 'QA '.$name,
                'password' => 'SiviE2EPass123!',
                'role' => str_contains($name, 'customer') ? 'customer' : $name,
                'email_verified_at' => now(),
                'mfa_secret' => $isAdminAccount ? $totp->generateSecretKey() : null,
                'mfa_confirmed_at' => $isAdminAccount ? now() : null,
                'mfa_recovery_codes' => $isAdminAccount
                    ? [$this->recoveryHash($this->recoveryCode($name))]
                    : null,
            ]);
        }
        foreach (range(1, 16) as $index) {
            $name = "admin-{$index}";
            User::updateOrCreate(['email' => "qa.admin-{$index}@example.test"], [
                'name' => "QA admin {$index}",
                'password' => 'SiviE2EPass123!',
                'role' => 'admin',
                'email_verified_at' => now(),
                'mfa_secret' => $totp->generateSecretKey(),
                'mfa_confirmed_at' => now(),
                'mfa_recovery_codes' => [$this->recoveryHash($this->recoveryCode($name))],
            ]);
        }
        Product::query()->where('name', 'Cam Tươi')->update(['inventory' => 1000]);
    }

    private function recoveryCode(string $name): string
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $name));
        $value = str_pad(substr($normalized, 0, 10), 10, 'X');

        return substr($value, 0, 5).'-'.substr($value, 5, 5);
    }

    private function recoveryHash(string $code): string
    {
        return hash_hmac('sha256', strtoupper($code), (string) config('app.key'));
    }
}
