<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index('category_id', 'products_category_id_idx');
            $table->index('price', 'products_price_idx');
            $table->index('name', 'products_name_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_category_id_idx');
            $table->dropIndex('products_price_idx');
            $table->dropIndex('products_name_idx');
        });
    }
};
