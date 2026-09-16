<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('provider')->default('local')->after('product_id');
            $table->string('path')->nullable()->change();
            $table->string('public_id')->nullable()->unique()->after('path');
        });
    }

    public function down(): void
    {
        DB::table('product_images')->whereNull('path')->update(['path' => '']);

        Schema::table('product_images', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn(['provider', 'public_id']);
            $table->string('path')->nullable(false)->change();
        });
    }
};
