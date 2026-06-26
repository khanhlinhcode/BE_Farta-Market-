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
            ! app()->environment(['local', 'testing']) ||
            ! config('app.seed_admin.enabled')
        ) {
            return;
        }

        $email = config('app.seed_admin.email');
        $password = config('app.seed_admin.password');

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('SEED_ADMIN_EMAIL must be a valid email address.');
        }

        if (! is_string($password) || Str::length($password) < 12) {
            throw new RuntimeException('SEED_ADMIN_PASSWORD must contain at least 12 characters.');
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
}
