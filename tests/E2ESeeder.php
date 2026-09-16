<?php

namespace Tests;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Seeder;
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
        foreach (['customer', 'second-customer', 'staff', 'admin'] as $name) {
            User::updateOrCreate(['email' => 'qa.'.$name.'@example.test'], [
                'name' => 'QA '.$name,
                'password' => 'SiviE2EPass123!',
                'role' => str_contains($name, 'customer') ? 'customer' : $name,
            ]);
        }
        foreach (range(1, 16) as $index) {
            User::updateOrCreate(['email' => "qa.admin-{$index}@example.test"], [
                'name' => "QA admin {$index}",
                'password' => 'SiviE2EPass123!',
                'role' => 'admin',
            ]);
        }
        Product::query()->where('name', 'Cam Tươi')->update(['inventory' => 1000]);
    }
}
