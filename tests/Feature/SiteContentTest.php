<?php

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.cloudinary.cloud_name' => 'test-cloud',
        'services.cloudinary.api_key' => 'test-key',
        'services.cloudinary.api_secret' => 'test-secret',
    ]);
    Http::fake(function (ClientRequest $request) {
        if (str_ends_with($request->url(), '/image/upload')) {
            return Http::response([
                'secure_url' => 'https://res.cloudinary.com/test/image/upload/cms.jpg',
                'public_id' => 'farta/banners/1/cms',
            ]);
        }

        return Http::response(['result' => 'ok']);
    });
});

test('public site content returns visible scheduled banners without provider identifiers', function () {
    Banner::create([
        'placement' => 'hero', 'alt_text_vi' => 'Rau tươi', 'alt_text_en' => 'Fresh food',
        'image_url' => 'https://res.cloudinary.com/test/hero.jpg', 'image_public_id' => 'secret/provider-id',
        'is_active' => true, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(),
    ]);
    Banner::create([
        'placement' => 'home_promo', 'alt_text_vi' => 'Cũ', 'alt_text_en' => 'Old',
        'image_url' => 'https://res.cloudinary.com/test/old.jpg', 'image_public_id' => 'secret/old',
        'is_active' => true, 'ends_at' => now()->subMinute(),
    ]);

    $this->getJson('/api/site-content')->assertOk()
        ->assertJsonPath('settings.brand_name', 'FartaMarket')
        ->assertJsonCount(1, 'banners')
        ->assertJsonMissingPath('settings.id')
        ->assertJsonMissingPath('settings.updated_at')
        ->assertJsonMissingPath('banners.0.image_public_id');
});

test('staff updates settings and public categories only include active records', function () {
    $hidden = Category::create(['name' => 'Hidden', 'is_active' => false]);
    $visible = Category::create(['name' => 'Visible', 'is_active' => true, 'sort_order' => 2]);
    foreach ([true, false] as $active) {
        Product::create([
            'name' => $active ? 'Visible product' : 'Hidden product',
            'img' => '/product.jpg', 'price' => 10000, 'inventory' => 2,
            'is_active' => $active, 'description' => 'Description',
            'sort_description' => 'Description', 'category_id' => $visible->id,
            'facebook' => '', 'twitter' => '', 'instagram' => '', 'linkedin' => '',
        ]);
    }
    $staff = User::factory()->create(['role' => 'staff']);
    Sanctum::actingAs($staff);
    $this->getJson('/api/site-content')->assertJsonPath('settings.brand_name', 'FartaMarket');
    $payload = SiteSetting::current()->toArray();
    $payload['brand_name'] = 'Farta Fresh';
    $payload['shipping_fee'] = 25000;

    $this->putJson('/api/admin/site-settings', $payload)->assertOk()
        ->assertJsonPath('brand_name', 'Farta Fresh');

    auth()->forgetGuards();
    $this->getJson('/api/site-content')->assertJsonPath('settings.shipping_fee', 25000);
    $this->getJson('/api/categories')->assertOk()->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Visible')->assertJsonPath('0.products_count', 1);
    $this->getJson("/api/categories/{$visible->id}")->assertOk()
        ->assertJsonCount(1, 'products')->assertJsonPath('products.0.name', 'Visible product');
    $this->getJson("/api/categories/{$hidden->id}")->assertNotFound();
});

test('admin manages banner and category images while customer is forbidden', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::create(['name' => 'Fruit']);
    Sanctum::actingAs($admin);

    $banner = $this->postJson('/api/admin/banners', [
        'placement' => 'hero', 'alt_text_vi' => 'Rau tươi', 'alt_text_en' => 'Fresh food',
        'link_url' => '/san-pham', 'image' => UploadedFile::fake()->image('hero.jpg'),
    ])->assertCreated()->json();
    expect($banner)->not->toHaveKey('image_public_id');

    $this->postJson("/api/admin/categories/{$category->id}/image", [
        'image' => UploadedFile::fake()->image('category.webp'),
    ])->assertOk()->assertJsonPath('image_url', 'https://res.cloudinary.com/test/image/upload/cms.jpg');

    Sanctum::actingAs(User::factory()->customer()->create());
    $this->deleteJson('/api/admin/banners/'.$banner['id'])->assertForbidden();
    $this->deleteJson("/api/admin/categories/{$category->id}/image")->assertForbidden();
});

test('banner URL validation and destructive CMS actions enforce the role matrix', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    Sanctum::actingAs($staff);
    $category = Category::create(['name' => 'Vegetables']);

    $this->postJson('/api/admin/banners', [
        'placement' => 'hero',
        'alt_text_vi' => 'Rau tươi',
        'alt_text_en' => 'Fresh food',
        'link_url' => 'http://insecure.example.test',
        'image' => UploadedFile::fake()->image('hero.jpg'),
    ])->assertUnprocessable();
    $this->postJson('/api/admin/banners', [
        'placement' => 'hero',
        'alt_text_vi' => 'Rau tươi',
        'alt_text_en' => 'Fresh food',
        'link_url' => '//insecure.example.test/path',
        'image' => UploadedFile::fake()->image('hero.jpg'),
    ])->assertUnprocessable();

    $banner = $this->postJson('/api/admin/banners', [
        'placement' => 'hero',
        'alt_text_vi' => 'Rau tươi',
        'alt_text_en' => 'Fresh food',
        'link_url' => 'https://example.test/products',
        'image' => UploadedFile::fake()->image('hero.jpg'),
    ])->assertCreated()->json();
    $this->putJson("/api/admin/banners/{$banner['id']}", [
        'placement' => 'home_promo',
        'alt_text_vi' => 'Khuyến mãi',
        'alt_text_en' => 'Promotion',
        'link_url' => '/san-pham',
    ])->assertOk()->assertJsonPath('placement', 'home_promo');
    $this->deleteJson("/api/admin/banners/{$banner['id']}")->assertForbidden();
    $this->deleteJson("/api/admin/categories/{$category->id}")->assertForbidden();

    Sanctum::actingAs(User::factory()->admin()->create());
    $this->deleteJson("/api/admin/banners/{$banner['id']}")->assertNoContent();
    $this->deleteJson("/api/admin/categories/{$category->id}")->assertNoContent();
});
