<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->boolean('is_active')->default(true)->after('inventory')->index();
        });

        DB::table('products')->orderBy('id')->chunk(100, function ($products) {
            foreach ($products as $product) {
                $baseSlug = Str::slug($product->name) ?: 'product';
                $slug = $baseSlug;
                $suffix = 1;

                while (
                    DB::table('products')
                        ->where('slug', $slug)
                        ->where('id', '<>', $product->id)
                        ->exists()
                ) {
                    $slug = "{$baseSlug}-{$suffix}";
                    $suffix++;
                }

                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['slug' => $slug]);
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'is_active']);
        });
    }
};
