<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function relatedTestProduct(Category $category, array $overrides = []): Product
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

function attachRelatedOrderDetail(Order $order, Product $product): void
{
    $order->details()->create([
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => $product->price,
        'product_name' => $product->name,
        'line_total' => $product->price,
    ]);
}

test('related products prioritize same category active in stock products', function () {
    $fruit = Category::create(['name' => 'Trái Cây']);
    $milk = Category::create(['name' => 'Sữa']);
    $cam = relatedTestProduct($fruit, ['name' => 'Cam Tươi']);
    $apple = relatedTestProduct($fruit, ['name' => 'Táo Úc']);
    $grape = relatedTestProduct($fruit, ['name' => 'Nho tím']);
    $outOfStock = relatedTestProduct($fruit, ['name' => 'Hết hàng', 'inventory' => 0]);
    $inactive = relatedTestProduct($fruit, ['name' => 'Ẩn', 'is_active' => false]);
    relatedTestProduct($milk, ['name' => 'Sữa Hộp']);

    $ids = collect(
        $this->getJson("/api/products/{$cam->id}/related")
            ->assertOk()
            ->json('data')
    )->pluck('id');

    expect($ids)->toContain($apple->id)
        ->and($ids)->toContain($grape->id)
        ->and($ids)->not->toContain($cam->id)
        ->and($ids)->not->toContain($outOfStock->id)
        ->and($ids)->not->toContain($inactive->id);
});

test('frequently bought with returns products from same orders by frequency', function () {
    $fruit = Category::create(['name' => 'Trái Cây']);
    $cam = relatedTestProduct($fruit, ['name' => 'Cam Tươi']);
    $apple = relatedTestProduct($fruit, ['name' => 'Táo Úc']);
    $milk = relatedTestProduct($fruit, ['name' => 'Sữa Hộp']);

    $firstOrder = Order::create([
        'fullname' => 'A',
        'address' => '123 Da Nang City',
        'phone' => '0900000000',
        'email' => 'a@example.test',
        'status' => Order::STATUS_PROCESSING,
    ]);
    $secondOrder = Order::create([
        'fullname' => 'B',
        'address' => '456 Da Nang City',
        'phone' => '0900000001',
        'email' => 'b@example.test',
        'status' => Order::STATUS_PROCESSING,
    ]);

    foreach ([
        [$firstOrder, $cam],
        [$firstOrder, $apple],
        [$firstOrder, $milk],
        [$secondOrder, $cam],
        [$secondOrder, $apple],
    ] as [$order, $product]) {
        attachRelatedOrderDetail($order, $product);
    }

    $data = $this->getJson("/api/products/{$cam->id}/frequently-bought-with")
        ->assertOk()
        ->json('data');

    expect($data[0]['id'])->toBe($apple->id)
        ->and($data[0]['freq'])->toBe(2);
});

test('frequently bought with ignores pending cancelled and failed orders', function () {
    $fruit = Category::create(['name' => 'Trái Cây']);
    $cam = relatedTestProduct($fruit, ['name' => 'Cam Tươi']);
    $pendingApple = relatedTestProduct($fruit, ['name' => 'Táo từ đơn pending']);
    $cancelledMilk = relatedTestProduct($fruit, ['name' => 'Sữa từ đơn cancelled']);
    $deliveredMango = relatedTestProduct($fruit, ['name' => 'Xoài từ đơn delivered']);

    $pendingOrder = Order::create([
        'fullname' => 'Pending',
        'address' => '123 Da Nang City',
        'phone' => '0900000000',
        'email' => 'pending@example.test',
        'status' => Order::STATUS_PENDING,
        'payment_status' => Order::PAYMENT_STATUS_PENDING,
    ]);
    $cancelledOrder = Order::create([
        'fullname' => 'Cancelled',
        'address' => '456 Da Nang City',
        'phone' => '0900000001',
        'email' => 'cancelled@example.test',
        'status' => Order::STATUS_CANCELLED,
        'payment_status' => Order::PAYMENT_STATUS_FAILED,
    ]);
    $deliveredOrder = Order::create([
        'fullname' => 'Delivered',
        'address' => '789 Da Nang City',
        'phone' => '0900000002',
        'email' => 'delivered@example.test',
        'status' => Order::STATUS_DELIVERED,
        'payment_status' => Order::PAYMENT_STATUS_PENDING,
    ]);

    foreach ([
        [$pendingOrder, $cam],
        [$pendingOrder, $pendingApple],
        [$cancelledOrder, $cam],
        [$cancelledOrder, $cancelledMilk],
        [$deliveredOrder, $cam],
        [$deliveredOrder, $deliveredMango],
    ] as [$order, $product]) {
        attachRelatedOrderDetail($order, $product);
    }

    $ids = collect(
        $this->getJson("/api/products/{$cam->id}/frequently-bought-with")
            ->assertOk()
            ->json('data')
    )->pluck('id');

    expect($ids)->toContain($deliveredMango->id)
        ->and($ids)->not->toContain($pendingApple->id)
        ->and($ids)->not->toContain($cancelledMilk->id);
});
