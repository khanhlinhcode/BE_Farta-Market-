<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('admin can create a product with a long short description', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $category = Category::create(['name' => 'Test category']);
    $shortDescription = str_repeat('x', 300);

    $this->postJson('/api/admin/products', [
        'name' => 'Long short description product',
        'img' => '/assets/users/images/featured/feature-1.png',
        'price' => 1000,
        'inventory' => 5,
        'description' => 'Full description',
        'sort_description' => $shortDescription,
        'category_id' => $category->id,
    ])
        ->assertCreated()
        ->assertJsonPath('sort_description', $shortDescription);
});

test('admin partial product update preserves omitted social links', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $category = Category::create(['name' => 'Test category']);
    $product = Product::create([
        'name' => 'Partial update product',
        'img' => '/assets/users/images/featured/feature-1.png',
        'price' => 1000,
        'inventory' => 5,
        'description' => 'Full description',
        'sort_description' => 'Short description',
        'facebook' => 'https://facebook.com/keep',
        'twitter' => 'https://twitter.com/keep',
        'instagram' => 'https://instagram.com/keep',
        'linkedin' => 'https://linkedin.com/keep',
        'category_id' => $category->id,
    ]);

    $this->patchJson("/api/admin/products/{$product->id}", [
        'price' => 2000,
    ])
        ->assertOk()
        ->assertJsonPath('price', 2000)
        ->assertJsonPath('facebook', 'https://facebook.com/keep')
        ->assertJsonPath('twitter', 'https://twitter.com/keep')
        ->assertJsonPath('instagram', 'https://instagram.com/keep')
        ->assertJsonPath('linkedin', 'https://linkedin.com/keep');
});

test('public products endpoint filters and paginates on the server', function () {
    $fruit = Category::create(['name' => 'Fruit']);
    $meat = Category::create(['name' => 'Meat']);

    Product::create([
        'name' => 'Cam Tươi',
        'img' => '/assets/users/images/featured/feature-1.png',
        'price' => 45000,
        'inventory' => 10,
        'description' => 'Full description',
        'sort_description' => 'Fresh orange',
        'facebook' => '',
        'twitter' => '',
        'instagram' => '',
        'linkedin' => '',
        'category_id' => $fruit->id,
    ]);

    Product::create([
        'name' => 'Cam hết hàng',
        'img' => '/assets/users/images/featured/feature-2.png',
        'price' => 30000,
        'inventory' => 0,
        'description' => 'Full description',
        'sort_description' => 'Out of stock orange',
        'facebook' => '',
        'twitter' => '',
        'instagram' => '',
        'linkedin' => '',
        'category_id' => $fruit->id,
    ]);

    Product::create([
        'name' => 'Thịt bò',
        'img' => '/assets/users/images/featured/feature-3.png',
        'price' => 200000,
        'inventory' => 5,
        'description' => 'Full description',
        'sort_description' => 'Beef',
        'facebook' => '',
        'twitter' => '',
        'instagram' => '',
        'linkedin' => '',
        'category_id' => $meat->id,
    ]);

    $this->getJson("/api/products?q=Cam&category_id={$fruit->id}&min_price=40000&max_price=50000&in_stock=1&sort=price_asc&page=1&per_page=15")
        ->assertOk()
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 1)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Cam Tươi');
});

test('product image upload returns clear validation errors', function () {
    Storage::fake('public');
    Sanctum::actingAs(User::factory()->admin()->create());

    $category = Category::create(['name' => 'Upload category']);
    $product = Product::create([
        'name' => 'Upload product',
        'img' => '/assets/users/images/featured/feature-1.png',
        'price' => 1000,
        'inventory' => 5,
        'description' => 'Full description',
        'sort_description' => 'Short description',
        'facebook' => '',
        'twitter' => '',
        'instagram' => '',
        'linkedin' => '',
        'category_id' => $category->id,
    ]);

    $this->postJson("/api/admin/products/{$product->id}/image", [
        'image' => UploadedFile::fake()->image('large.jpg')->size(2049),
    ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'errors.image.0',
            'Ảnh quá lớn. Vui lòng chọn ảnh nhỏ hơn 2MB'
        );

    $this->postJson("/api/admin/products/{$product->id}/image", [
        'image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
    ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'errors.image.0',
            'Chỉ hỗ trợ định dạng JPG, PNG, WEBP'
        );
});
