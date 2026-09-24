<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->index('public_id');
            $table->unique(['product_id', 'public_id']);
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'public_id']);
            $table->dropIndex(['public_id']);
            $table->unique('public_id');
        });
    }
};
