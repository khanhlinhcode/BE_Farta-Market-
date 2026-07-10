<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedConfiguredAdmin();

        $this->call([
            CategorySeeder::class,
            ProductSeeder::class
        ]);
    }

    private function seedConfiguredAdmin(): void
    {
        if (
            app()->environment('production') ||
            ! app()->environment(['local', 'testing']) ||
            ! config('app.seed_admin.enabled')
        ) {
            return;
        }

        $email = config('app.seed_admin.email');
        $password = config('app.seed_admin.password');

        $this->seedQaAccounts();

        if ($email === null && $password === null) {
            return;
        }

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('SEED_ADMIN_EMAIL must be a valid email address when custom admin seeding is configured.');
        }

        if (! is_string($password) || Str::length($password) < 12) {
            throw new RuntimeException('SEED_ADMIN_PASSWORD must contain at least 12 characters when custom admin seeding is configured.');
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => config('app.seed_admin.name', 'Local Admin'),
                'password' => $password,
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );
    }

    private function seedQaAccounts(): void
    {
        $password = 'FartaQa12345';
        $accounts = [
            ['name' => 'QA Admin', 'email' => 'qa.admin@example.test', 'role' => 'admin'],
            ['name' => 'QA Staff', 'email' => 'qa.staff@example.test', 'role' => 'staff'],
            ['name' => 'QA Customer', 'email' => 'qa.customer@example.test', 'role' => 'customer'],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => $password,
                    'role' => $account['role'],
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
