<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE orders MODIFY status ENUM('ORDERED','PREPARING','DELIVERING','CANCELLED','PENDING_PAYMENT','PAYMENT_FAILED') NOT NULL DEFAULT 'ORDERED'"
            );
        } elseif ($driver !== 'sqlite') {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('status', 40)->default('ORDERED')->change();
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('payment_method', ['cod', 'vnpay'])
                ->default('cod')
                ->after('idempotency_key');
            $table->enum('payment_status', ['pending', 'paid', 'failed'])
                ->default('pending')
                ->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_status']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE orders MODIFY status ENUM('ORDERED','PREPARING','DELIVERING','CANCELLED') NOT NULL DEFAULT 'ORDERED'"
            );
        }
    }
};
