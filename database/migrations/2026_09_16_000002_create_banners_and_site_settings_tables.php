<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('placement', 30);
            $table->string('title_vi', 160)->nullable();
            $table->string('title_en', 160)->nullable();
            $table->string('subtitle_vi', 300)->nullable();
            $table->string('subtitle_en', 300)->nullable();
            $table->string('button_label_vi', 80)->nullable();
            $table->string('button_label_en', 80)->nullable();
            $table->string('alt_text_vi', 160);
            $table->string('alt_text_en', 160);
            $table->string('link_url')->nullable();
            $table->string('image_url')->default('');
            $table->string('image_public_id')->default('');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['placement', 'is_active', 'sort_order']);
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('brand_name')->default('FartaMarket');
            $table->string('contact_email')->default('FartaMarket@gmail.com');
            $table->string('contact_phone', 30)->default('0977232232');
            $table->string('support_phone', 30)->default('0393886668');
            $table->string('address_vi')->default('213 Trương Định, Nghệ An');
            $table->string('address_en')->default('213 Truong Dinh, Nghe An');
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('featured_title_vi')->default('Sản phẩm nổi bật');
            $table->string('featured_title_en')->default('Featured products');
            $table->string('recommended_title_vi')->default('Gợi ý cho bạn');
            $table->string('recommended_title_en')->default('Recommended for you');
            $table->text('footer_description_vi')->nullable();
            $table->text('footer_description_en')->nullable();
            $table->unsignedBigInteger('free_shipping_threshold')->default(200000);
            $table->unsignedBigInteger('shipping_fee')->default(20000);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('site_settings')->insert([
            'brand_name' => 'FartaMarket',
            'contact_email' => 'FartaMarket@gmail.com',
            'contact_phone' => '0977232232',
            'support_phone' => '0393886668',
            'address_vi' => '213 Trương Định, Nghệ An',
            'address_en' => '213 Truong Dinh, Nghe An',
            'facebook_url' => 'https://www.facebook.com',
            'instagram_url' => 'https://www.instagram.com',
            'linkedin_url' => 'https://www.linkedin.com',
            'twitter_url' => 'https://www.twitter.com',
            'featured_title_vi' => 'Sản phẩm nổi bật',
            'featured_title_en' => 'Featured products',
            'recommended_title_vi' => 'Gợi ý cho bạn',
            'recommended_title_en' => 'Recommended for you',
            'free_shipping_threshold' => 200000,
            'shipping_fee' => 20000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('banners');
    }
};
