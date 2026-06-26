<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->index('idempotency_key');
        });

        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
        });

        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        DB::table('idempotency_keys')
            ->whereNull('expires_at')
            ->update(['expires_at' => now()->addHours(24)]);

        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->unique(['idempotency_key', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key', 'user_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'expires_at']);
            $table->unique('idempotency_key');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['idempotency_key']);
            $table->unique('idempotency_key');
        });
    }
};
