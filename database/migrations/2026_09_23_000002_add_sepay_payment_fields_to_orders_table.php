<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('cod','vnpay','sepay') NOT NULL DEFAULT 'cod'");
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_reference', 40)->nullable()->unique()->after('payment_status');
            $table->timestamp('payment_expires_at')->nullable()->after('payment_reference');
            $table->string('payment_transaction_id', 100)->nullable()->unique()->after('payment_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['payment_reference']);
            $table->dropUnique(['payment_transaction_id']);
            $table->dropColumn(['payment_reference', 'payment_expires_at', 'payment_transaction_id']);
        });

        if (DB::getDriverName() === 'mysql'
            && ! DB::table('orders')->where('payment_method', 'sepay')->exists()) {
            DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('cod','vnpay') NOT NULL DEFAULT 'cod'");
        }
    }
};
