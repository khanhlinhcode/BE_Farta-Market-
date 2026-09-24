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
            $table->dropUnique(['public_id']);
            $table->index('public_id');
            $table->unique(['product_id', 'public_id']);
        });
    }

    public function down(): void
    {
        $hasReusedImages = DB::table('product_images')
            ->select('public_id')
            ->whereNotNull('public_id')
            ->groupBy('public_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasReusedImages) {
            throw new RuntimeException(
                'Cannot roll back reusable product images while a public_id is shared by multiple products. No schema changes were applied.'
            );
        }

        Schema::table('product_images', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'public_id']);
            $table->dropIndex(['public_id']);
            $table->unique('public_id');
        });
    }
};
