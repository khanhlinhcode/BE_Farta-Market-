<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createStatusProduct(array $overrides = []): Product
{
    $category = Category::firstOrCreate(['name' => 'Fruit']);

    return Product::create(array_merge([
        'name' => 'Cam Tươi',
        'img' => '/assets/users/images/featured/feature-1.png',
        'price' => 45000,
        'inventory' => 8,
        'description' => 'Full description',
        'sort_description' => 'Fresh orange',
        'facebook' => '',
        'twitter' => '',
        'instagram' => '',
        'linkedin' => '',
        'category_id' => $category->id,
    ], $overrides));
}

function createStatusOrder(User $user, Product $product, array $overrides = []): Order
{
    $order = Order::create(array_merge([
        'user_id' => $user->id,
        'fullname' => 'Nguyen Van A',
        'address' => 'Da Nang City',
        'phone' => '0900000000',
        'email' => 'customer@example.test',
        'status' => Order::STATUS_PENDING,
        'payment_method' => Order::PAYMENT_METHOD_COD,
        'payment_status' => Order::PAYMENT_STATUS_PENDING,
        'subtotal' => 90000,
        'shipping_fee' => 20000,
        'grand_total' => 110000,
    ], $overrides));

    $order->details()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 45000,
        'product_name' => $product->name,
        'line_total' => 90000,
    ]);

    return $order;
}

test('admin cannot skip order status transitions', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->customer()->create();
    $product = createStatusProduct();
    $order = createStatusOrder($customer, $product);

    Sanctum::actingAs($admin);

    $this->patchJson("/api/admin/orders/{$order->id}/status", [
        'status' => Order::STATUS_SHIPPED,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Không thể chuyển từ pending sang shipped');

    expect($order->fresh()->status)->toBe(Order::STATUS_PENDING);
    expect(OrderStatusHistory::count())->toBe(0);
});

test('admin can move order through the full status machine', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->customer()->create();
    $product = createStatusProduct();
    $order = createStatusOrder($customer, $product);

    Sanctum::actingAs($admin);

    foreach ([
        Order::STATUS_CONFIRMED,
        Order::STATUS_PROCESSING,
        Order::STATUS_SHIPPED,
        Order::STATUS_DELIVERED,
    ] as $status) {
        $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => $status,
            'note' => "Move to {$status}",
        ])
            ->assertOk()
            ->assertJsonPath('status', $status);
    }

    expect($order->fresh()->status)->toBe(Order::STATUS_DELIVERED);
    expect(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(4);
});

test('cancelling a pending order restores inventory once', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->customer()->create();
    $product = createStatusProduct(['inventory' => 8]);
    $order = createStatusOrder($customer, $product);

    Sanctum::actingAs($admin);

    $this->patchJson("/api/admin/orders/{$order->id}/status", [
        'status' => Order::STATUS_CANCELLED,
        'note' => 'Customer requested cancellation',
    ])
        ->assertOk()
        ->assertJsonPath('status', Order::STATUS_CANCELLED);

    expect($product->fresh()->inventory)->toBe(10);

    $this->patchJson("/api/admin/orders/{$order->id}/status", [
        'status' => Order::STATUS_PENDING,
    ])->assertUnprocessable();

    expect($product->fresh()->inventory)->toBe(10);
});

test('review is allowed only after delivered status', function () {
    $customer = User::factory()->customer()->create();
    $product = createStatusProduct();
    $order = createStatusOrder($customer, $product);

    Sanctum::actingAs($customer);

    $this->postJson("/api/products/{$product->id}/reviews", [
        'rating' => 5,
        'comment' => 'Sản phẩm rất tốt.',
    ])->assertForbidden();

    $order->update(['status' => Order::STATUS_DELIVERED]);

    $this->postJson("/api/products/{$product->id}/reviews", [
        'rating' => 5,
        'comment' => 'Sản phẩm rất tốt.',
    ])->assertCreated();
});

test('admin dashboard summary chart and export use delivered order data', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->customer()->create();
    $product = createStatusProduct();
    createStatusOrder($customer, $product, [
        'status' => Order::STATUS_DELIVERED,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Sanctum::actingAs($admin);

    $this->getJson('/api/admin/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('revenue_today', 110000)
        ->assertJsonPath('orders_today', 1);

    $this->getJson('/api/admin/dashboard/revenue-chart?range=7d')
        ->assertOk()
        ->assertJsonFragment(['revenue' => 110000]);

    $this->getJson('/api/admin/dashboard/top-products?limit=5')
        ->assertOk()
        ->assertJsonPath('0.product_name', 'Cam Tươi')
        ->assertJsonPath('0.quantity_sold', 2);

    $this->get('/api/admin/orders/export.csv')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $this->get('/api/admin/orders/export?status=delivered&from='.now()->toDateString().'&to='.now()->toDateString())
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});
