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
            DB::statement("ALTER TABLE orders MODIFY status VARCHAR(40) NOT NULL DEFAULT 'pending'");
        } elseif ($driver !== 'sqlite') {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('status', 40)->default('pending')->change();
            });
        }

        DB::table('orders')
            ->where('status', 'ORDERED')
            ->update(['status' => 'pending']);
        DB::table('orders')
            ->where('status', 'PENDING_PAYMENT')
            ->update(['status' => 'pending']);
        DB::table('orders')
            ->where('status', 'PREPARING')
            ->update(['status' => 'confirmed']);
        DB::table('orders')
            ->where('status', 'DELIVERING')
            ->update(['status' => 'shipped']);
        DB::table('orders')
            ->whereIn('status', ['CANCELLED', 'PAYMENT_FAILED'])
            ->update(['status' => 'cancelled']);

        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE orders MODIFY status ENUM('pending','confirmed','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending'"
            );
        }

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY status VARCHAR(40) NOT NULL DEFAULT 'ORDERED'");
        }

        DB::table('orders')
            ->where('status', 'pending')
            ->update(['status' => 'ORDERED']);
        DB::table('orders')
            ->whereIn('status', ['confirmed', 'processing'])
            ->update(['status' => 'PREPARING']);
        DB::table('orders')
            ->whereIn('status', ['shipped', 'delivered'])
            ->update(['status' => 'DELIVERING']);
        DB::table('orders')
            ->where('status', 'cancelled')
            ->update(['status' => 'CANCELLED']);
    }
};
