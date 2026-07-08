<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE users MODIFY role ENUM('admin', 'staff', 'customer') NOT NULL DEFAULT 'customer'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('users')
            ->where('role', 'customer')
            ->update(['role' => 'staff']);

        DB::statement(
            "ALTER TABLE users MODIFY role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff'"
        );
    }
};
