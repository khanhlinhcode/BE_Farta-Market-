<?php

use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createCouponProduct(array $overrides = []): Product
{
    $category = Category::firstOrCreate(['name' => 'Fruit']);

    return Product::create(array_merge([
        'name' => 'Cam Tươi',
        'img' => '/assets/users/images/featured/feature-1.png',
        'price' => 100000,
        'inventory' => 20,
        'description' => 'Full description',
        'sort_description' => 'Fresh orange',
        'facebook' => '',
        'twitter' => '',
        'instagram' => '',
        'linkedin' => '',
        'category_id' => $category->id,
    ], $overrides));
}

function couponOrderPayload(Product $product, string $couponCode = 'SUMMER10', int $quantity = 2): array
{
    return [
        'fullname' => 'Nguyen Van A',
        'address' => '123 Duong Test, Da Nang City',
        'phone' => '0900000000',
        'email' => 'customer@example.test',
        'coupon_code' => $couponCode,
        'products' => [
            [
                'product_id' => $product->id,
                'quantity' => $quantity,
            ],
        ],
    ];
}

test('expired coupon validation fails with clear message', function () {
    $user = User::factory()->customer()->create();
    Coupon::create([
        'code' => 'OLD10',
        'type' => Coupon::TYPE_PERCENT,
        'value' => 10,
        'expires_at' => now()->subDay(),
        'active' => true,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/coupons/validate', [
        'code' => 'OLD10',
        'order_amount' => 200000,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('valid', false)
        ->assertJsonPath('message', 'Mã giảm giá không hợp lệ hoặc đã hết hạn.');
});

test('coupon min order amount is enforced', function () {
    $user = User::factory()->customer()->create();
    Coupon::create([
        'code' => 'MIN200',
        'type' => Coupon::TYPE_FIXED,
        'value' => 20000,
        'min_order_amount' => 200000,
        'active' => true,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/coupons/validate', [
        'code' => 'MIN200',
        'order_amount' => 150000,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Đơn hàng tối thiểu 200,000đ để dùng mã này.');
});

test('coupon can only be used once per user by default', function () {
    $user = User::factory()->customer()->create();
    $product = createCouponProduct();
    Coupon::create([
        'code' => 'ONCE',
        'type' => Coupon::TYPE_FIXED,
        'value' => 15000,
        'active' => true,
    ]);

    Sanctum::actingAs($user);

    $this->withHeader('X-Idempotency-Key', 'coupon-once-order-0001')
        ->postJson('/api/order', couponOrderPayload($product, 'ONCE'))
        ->assertCreated();

    $this->postJson('/api/coupons/validate', [
        'code' => 'ONCE',
        'order_amount' => 200000,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Bạn đã sử dụng mã này rồi.');
});

test('order stores coupon discount and final total from server calculation', function () {
    $user = User::factory()->customer()->create();
    $product = createCouponProduct(['price' => 100000]);
    $coupon = Coupon::create([
        'code' => 'SUMMER10',
        'type' => Coupon::TYPE_PERCENT,
        'value' => 10,
        'max_discount_amount' => 20000,
        'active' => true,
    ]);

    Sanctum::actingAs($user);

    $orderId = $this->withHeader('X-Idempotency-Key', 'coupon-order-total-0001')
        ->postJson('/api/order', couponOrderPayload($product, 'SUMMER10', 3))
        ->assertCreated()
        ->assertJsonPath('data.coupon_id', $coupon->id)
        ->assertJsonPath('data.subtotal', '300000.00')
        ->assertJsonPath('data.discount_amount', '20000.00')
        ->assertJsonPath('data.grand_total', '280000.00')
        ->json('data.id');

    $order = Order::findOrFail($orderId);
    expect((float) $order->discount_amount)->toBe(20000.0);
    expect((float) $order->grand_total)->toBe(280000.0);
    expect($coupon->fresh()->used_count)->toBe(1);
    expect(CouponUsage::where('order_id', $orderId)->count())->toBe(1);
});

test('coupon max uses prevents a second user from using a consumed coupon', function () {
    $firstUser = User::factory()->customer()->create();
    $secondUser = User::factory()->customer()->create();
    $product = createCouponProduct(['inventory' => 20]);
    Coupon::create([
        'code' => 'LIMIT1',
        'type' => Coupon::TYPE_FIXED,
        'value' => 20000,
        'max_uses' => 1,
        'active' => true,
    ]);

    Sanctum::actingAs($firstUser);
    $this->withHeader('X-Idempotency-Key', 'coupon-limit-one-0001')
        ->postJson('/api/order', couponOrderPayload($product, 'LIMIT1'))
        ->assertCreated();

    Sanctum::actingAs($secondUser);
    $this->withHeader('X-Idempotency-Key', 'coupon-limit-one-0002')
        ->postJson('/api/order', couponOrderPayload($product, 'LIMIT1'))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Mã giảm giá đã hết lượt sử dụng.');

    expect(Order::count())->toBe(1);
    expect(Coupon::where('code', 'LIMIT1')->first()->used_count)->toBe(1);
});

test('admin can create summer percent coupon and view usage stats', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $couponId = $this->postJson('/api/admin/coupons', [
        'code' => 'summer10',
        'type' => Coupon::TYPE_PERCENT,
        'value' => 10,
        'min_order_amount' => 0,
        'max_discount_amount' => 20000,
        'max_uses' => null,
        'max_uses_per_user' => 1,
        'active' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('code', 'SUMMER10')
        ->json('id');

    $this->getJson("/api/admin/coupons/{$couponId}/usage-stats")
        ->assertOk()
        ->assertJsonPath('total_used', 0)
        ->assertJsonPath('total_discount_amount', 0);
});
