<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function suggestTestProduct(Category $category, array $overrides = []): Product
{
    return Product::create(array_merge([
        'name' => fake()->unique()->word(),
        'img' => '/assets/users/images/featured/feature-1.png',
        'price' => 45000,
        'inventory' => 10,
        'is_active' => true,
        'description' => 'Full description',
        'sort_description' => 'Short description',
        'facebook' => '',
        'twitter' => '',
        'instagram' => '',
        'linkedin' => '',
        'category_id' => $category->id,
    ], $overrides));
}

test('product suggest returns active in stock matches with compact payload', function () {
    $fruit = Category::create(['name' => 'Trái Cây']);
    $cam = suggestTestProduct($fruit, ['name' => 'Cam Tươi']);
    suggestTestProduct($fruit, ['name' => 'Cam hết hàng', 'inventory' => 0]);
    suggestTestProduct($fruit, ['name' => 'Cam ẩn', 'is_active' => false]);
    suggestTestProduct($fruit, ['name' => 'Táo Úc']);

    $data = $this->getJson('/api/products/suggest?q=cam')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'price', 'image_url']]])
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['id'])->toBe($cam->id)
        ->and($data[0]['name'])->toBe('Cam Tươi');
});

test('recommended products use wishlist categories for logged in users', function () {
    $milk = Category::create(['name' => 'Sữa']);
    $fruit = Category::create(['name' => 'Trái Cây']);
    $user = User::factory()->customer()->create();
    $wishlistedMilk = suggestTestProduct($milk, ['name' => 'Sữa Hộp']);
    $recommendedMilk = suggestTestProduct($milk, ['name' => 'Sữa Tươi']);
    $fruitProduct = suggestTestProduct($fruit, ['name' => 'Cam Tươi']);

    Wishlist::create([
        'user_id' => $user->id,
        'product_id' => $wishlistedMilk->id,
    ]);

    $data = $this->withToken($user->createToken('customer')->plainTextToken)
        ->getJson('/api/products/recommended')
        ->assertOk()
        ->json('data');
    $ids = collect($data)->pluck('id');

    expect($ids)->toContain($recommendedMilk->id)
        ->and($ids)->not->toContain($wishlistedMilk->id)
        ->and($ids)->not->toContain($fruitProduct->id);
});

test('recommended products ignore pending cancelled and failed purchase history', function () {
    $milk = Category::create(['name' => 'Sữa']);
    $fruit = Category::create(['name' => 'Trái Cây']);
    $user = User::factory()->customer()->create();
    $purchasedMilk = suggestTestProduct($milk, ['name' => 'Sữa đã mua']);
    $recommendedMilk = suggestTestProduct($milk, ['name' => 'Sữa gợi ý']);
    $pendingFruit = suggestTestProduct($fruit, ['name' => 'Cam pending']);
    $recommendedFruit = suggestTestProduct($fruit, ['name' => 'Táo không nên gợi ý']);

    $pendingOrder = Order::create([
        'user_id' => $user->id,
        'fullname' => 'Pending',
        'address' => '123 Da Nang City',
        'phone' => '0900000000',
        'email' => 'pending@example.test',
        'status' => Order::STATUS_PENDING,
        'payment_status' => Order::PAYMENT_STATUS_PENDING,
    ]);
    $deliveredOrder = Order::create([
        'user_id' => $user->id,
        'fullname' => 'Delivered',
        'address' => '456 Da Nang City',
        'phone' => '0900000001',
        'email' => 'delivered@example.test',
        'status' => Order::STATUS_DELIVERED,
        'payment_status' => Order::PAYMENT_STATUS_PENDING,
    ]);

    foreach ([
        [$pendingOrder, $pendingFruit],
        [$deliveredOrder, $purchasedMilk],
    ] as [$order, $product]) {
        $order->details()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $product->price,
            'product_name' => $product->name,
            'line_total' => $product->price,
        ]);
    }

    Sanctum::actingAs($user);

    $ids = collect(
        $this->getJson('/api/products/recommended')
            ->assertOk()
            ->json('data')
    )->pluck('id');

    expect($ids)->toContain($recommendedMilk->id)
        ->and($ids)->not->toContain($pendingFruit->id)
        ->and($ids)->not->toContain($recommendedFruit->id);
});
