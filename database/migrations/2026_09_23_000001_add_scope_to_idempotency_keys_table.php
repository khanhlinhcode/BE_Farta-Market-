<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->string('scope', 80)->nullable()->after('idempotency_key');
        });

        DB::table('idempotency_keys')
            ->orderBy('id')
            ->eachById(function ($key) {
                DB::table('idempotency_keys')
                    ->where('id', $key->id)
                    ->update([
                        'scope' => $key->user_id === null ? 'guest' : 'user:'.$key->user_id,
                    ]);
            });

        DB::table('idempotency_keys')
            ->select('idempotency_key', 'scope', DB::raw('MIN(id) as retained_id'))
            ->groupBy('idempotency_key', 'scope')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function ($duplicate) {
                DB::table('idempotency_keys')
                    ->where('idempotency_key', $duplicate->idempotency_key)
                    ->where('scope', $duplicate->scope)
                    ->where('id', '!=', $duplicate->retained_id)
                    ->delete();
            });

        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->string('scope', 80)->nullable(false)->change();
        });

        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key', 'user_id']);
            $table->unique(['idempotency_key', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key', 'scope']);
            $table->unique(['idempotency_key', 'user_id']);
            $table->dropColumn('scope');
        });
    }
};
