<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('image_url')->nullable()->after('description');
            $table->string('image_public_id')->nullable()->after('image_url');
            $table->unsignedInteger('sort_order')->default(0)->after('image_public_id');
            $table->boolean('is_active')->default(true)->after('sort_order');
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'sort_order']);
            $table->dropColumn(['description', 'image_url', 'image_public_id', 'sort_order', 'is_active']);
        });
    }
};
