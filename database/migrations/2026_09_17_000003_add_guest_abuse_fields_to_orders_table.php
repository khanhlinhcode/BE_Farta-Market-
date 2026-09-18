<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->char('checkout_ip_hash', 64)->nullable()->index()->after('analytics_session_hash');
            $table->timestamp('guest_expires_at')->nullable()->index()->after('checkout_ip_hash');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['checkout_ip_hash']);
            $table->dropIndex(['guest_expires_at']);
            $table->dropColumn(['checkout_ip_hash', 'guest_expires_at']);
        });
    }
};
