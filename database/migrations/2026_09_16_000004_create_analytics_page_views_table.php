<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_page_views', function (Blueprint $table) {
            $table->id();
            $table->char('visitor_hash', 64);
            $table->char('session_hash', 64);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path', 255);
            $table->string('referrer_host')->nullable();
            $table->string('device_type', 20);
            $table->string('browser', 40);
            $table->string('os', 40);
            $table->timestamp('occurred_at');
            $table->index('occurred_at');
            $table->index(['session_hash', 'occurred_at']);
            $table->index(['visitor_hash', 'occurred_at']);
            $table->index(['path', 'occurred_at']);
            $table->index(['device_type', 'occurred_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->char('analytics_session_hash', 64)->nullable()->index()->after('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['analytics_session_hash']);
            $table->dropColumn('analytics_session_hash');
        });
        Schema::dropIfExists('analytics_page_views');
    }
};
