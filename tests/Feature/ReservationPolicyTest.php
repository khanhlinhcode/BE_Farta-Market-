<?php

use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\ReservationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function reservationProduct(array $overrides = []): Product
{
    $category = Category::firstOrCreate(['name' => 'Reservation policy']);

    return Product::create(array_merge([
        'name' => 'Reservation product',
        'img' => '/reservation-product.png',
        'price' => 100000,
        'inventory' => 100,
        'description' => 'Reservation test product',
        'sort_description' => 'Reservation test',
        'facebook' => '',
        'twitter' => '',
        'instagram' => '',
        'linkedin' => '',
        'category_id' => $category->id,
    ], $overrides));
}

function pendingReservation(?User $user, Product $product, int $quantity, int $subtotal): Order
{
    $order = Order::create([
        'user_id' => $user?->id,
        'fullname' => 'Reservation buyer',
        'address' => '123 Reservation Street',
        'phone' => '0900000000',
        'email' => 'reservation@example.test',
        'status' => Order::STATUS_PENDING,
        'payment_method' => Order::PAYMENT_METHOD_COD,
        'payment_status' => Order::PAYMENT_STATUS_PENDING,
        'subtotal' => $subtotal,
        'shipping_fee' => 0,
        'grand_total' => $subtotal,
        'reservation_expires_at' => now()->addHour(),
    ]);
    $order->details()->create([
        'product_id' => $product->id,
        'quantity' => $quantity,
        'unit_price' => intdiv($subtotal, $quantity),
        'product_name' => $product->name,
        'line_total' => $subtotal,
    ]);

    return $order;
}

function authenticatedCodPayload(Product $product, int $quantity = 1): array
{
    return [
        'fullname' => 'Reservation buyer',
        'address' => '123 Reservation Street',
        'phone' => '0900000000',
        'email' => 'reservation@example.test',
        'products' => [['product_id' => $product->id, 'quantity' => $quantity]],
    ];
}

test('authenticated pending order ceiling is enforced before mutation', function () {
    config()->set('commerce.reservations.max_pending_orders_per_user', 3);
    $user = User::factory()->customer()->create();
    $requested = reservationProduct();

    foreach (range(1, 3) as $index) {
        pendingReservation($user, reservationProduct(['name' => "Existing {$index}"]), 1, 100000);
    }

    Sanctum::actingAs($user, ['mobile:customer']);
    $this->withHeader('X-Idempotency-Key', 'reservation-pending-limit-0001')
        ->postJson('/api/order', authenticatedCodPayload($requested))
        ->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertJsonPath('error_code', 'reservation_user_limit');

    expect($requested->fresh()->inventory)->toBe(100)
        ->and(Order::count())->toBe(3);
});

test('authenticated reserved unit ceiling is enforced', function () {
    config()->set('commerce.reservations.max_reserved_units_per_user', 20);
    $user = User::factory()->customer()->create();
    $existing = reservationProduct(['inventory' => 100]);
    $requested = reservationProduct(['name' => 'Requested units']);
    pendingReservation($user, $existing, 20, 100000);

    DB::transaction(fn () => app(ReservationPolicy::class)->lockAndValidate([
        ['product_id' => $requested->id, 'quantity' => 1],
    ], $user->id));
})->throws(\Illuminate\Http\Exceptions\HttpResponseException::class);

test('authenticated reserved value ceiling returns stable error contract', function () {
    config()->set('commerce.reservations.max_reserved_value_per_user', 5_000_000);
    $user = User::factory()->customer()->create();
    $existing = reservationProduct(['price' => 4_950_000]);
    $requested = reservationProduct(['name' => 'Requested value', 'price' => 100000]);
    pendingReservation($user, $existing, 1, 4_950_000);

    try {
        DB::transaction(fn () => app(ReservationPolicy::class)->lockAndValidate([
            ['product_id' => $requested->id, 'quantity' => 1],
        ], $user->id));
        $this->fail('Expected reservation limit rejection.');
    } catch (\Illuminate\Http\Exceptions\HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(429)
            ->and($exception->getResponse()->getData(true)['error_code'])->toBe('reservation_user_limit');
    }
});

test('aggregate SKU cap blocks rotating guest identities', function () {
    $product = reservationProduct(['inventory' => 5]);
    pendingReservation(null, $product, 5, 500000);

    try {
        DB::transaction(fn () => app(ReservationPolicy::class)->lockAndValidate([
            ['product_id' => $product->id, 'quantity' => 1],
        ], null));
        $this->fail('Expected aggregate reservation rejection.');
    } catch (\Illuminate\Http\Exceptions\HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(409)
            ->and($exception->getResponse()->getData(true)['error_code'])->toBe('reservation_capacity_conflict');
    }
});

test('guest and authenticated COD receive approved reservation expiry', function () {
    config([
        'services.turnstile.required' => false,
        'commerce.reservations.guest_cod_ttl_minutes' => 60,
        'commerce.reservations.authenticated_cod_ttl_minutes' => 120,
    ]);
    $guestProduct = reservationProduct();
    $guestOrder = $this->withHeader('X-Idempotency-Key', 'reservation-guest-ttl-0001')
        ->postJson('/api/order', authenticatedCodPayload($guestProduct))
        ->assertCreated()
        ->json('data');

    $user = User::factory()->customer()->create();
    Sanctum::actingAs($user, ['mobile:customer']);
    $authProduct = reservationProduct(['name' => 'Authenticated TTL']);
    $authOrder = $this->withHeader('X-Idempotency-Key', 'reservation-auth-ttl-0001')
        ->postJson('/api/order', authenticatedCodPayload($authProduct))
        ->assertCreated()
        ->json('data');

    expect(now()->diffInMinutes($guestOrder['reservation_expires_at'], true))->toBeBetween(59.0, 60.0)
        ->and(now()->diffInMinutes($authOrder['reservation_expires_at'], true))->toBeBetween(119.0, 120.0);
});

test('expiration restores inventory and coupon exactly once', function () {
    $user = User::factory()->customer()->create();
    $product = reservationProduct(['inventory' => 9]);
    $coupon = Coupon::create([
        'code' => 'EXPIRE10',
        'type' => Coupon::TYPE_FIXED,
        'value' => 10000,
        'min_order_amount' => 0,
        'max_uses' => 100,
        'used_count' => 1,
        'max_uses_per_user' => 1,
        'active' => true,
    ]);
    $order = pendingReservation($user, $product, 1, 100000);
    $order->update([
        'coupon_id' => $coupon->id,
        'reservation_expires_at' => now()->subMinute(),
    ]);
    CouponUsage::create([
        'coupon_id' => $coupon->id,
        'user_id' => $user->id,
        'order_id' => $order->id,
        'discount_amount' => 10000,
        'created_at' => now(),
    ]);

    $this->artisan('orders:expire-guests')->assertSuccessful();
    $this->artisan('orders:expire-guests')->assertSuccessful();

    try {
        app(\App\Services\OrderStatusService::class)->transition(
            $order->fresh(),
            Order::STATUS_CANCELLED,
            'Second cancellation must not restore twice.'
        );
    } catch (\InvalidArgumentException) {
        // Expected: the expired order is already cancelled.
    }

    expect($order->fresh()->status)->toBe(Order::STATUS_CANCELLED)
        ->and($product->fresh()->inventory)->toBe(10)
        ->and($coupon->fresh()->used_count)->toBe(0)
        ->and(CouponUsage::where('order_id', $order->id)->count())->toBe(0);
});

test('SePay expiration restores coupon and inventory exactly once', function () {
    $user = User::factory()->customer()->create();
    $product = reservationProduct(['inventory' => 9]);
    $coupon = Coupon::create([
        'code' => 'SEPAY10',
        'type' => Coupon::TYPE_FIXED,
        'value' => 10000,
        'min_order_amount' => 0,
        'max_uses' => 100,
        'used_count' => 1,
        'max_uses_per_user' => 1,
        'active' => true,
    ]);
    $order = pendingReservation($user, $product, 1, 100000);
    $order->update([
        'coupon_id' => $coupon->id,
        'payment_method' => Order::PAYMENT_METHOD_SEPAY,
        'payment_expires_at' => now()->subMinute(),
        'reservation_expires_at' => now()->subMinute(),
    ]);
    CouponUsage::create([
        'coupon_id' => $coupon->id,
        'user_id' => $user->id,
        'order_id' => $order->id,
        'discount_amount' => 10000,
        'created_at' => now(),
    ]);

    $this->artisan('payments:expire-pending')->assertSuccessful();
    $this->artisan('payments:expire-pending')->assertSuccessful();

    expect($order->fresh()->status)->toBe(Order::STATUS_CANCELLED)
        ->and($order->fresh()->payment_status)->toBe(Order::PAYMENT_STATUS_FAILED)
        ->and($product->fresh()->inventory)->toBe(10)
        ->and($coupon->fresh()->used_count)->toBe(0)
        ->and(CouponUsage::where('order_id', $order->id)->count())->toBe(0);
});

test('paid or confirmed orders are never expired', function () {
    $user = User::factory()->customer()->create();
    $product = reservationProduct(['inventory' => 9]);
    $order = pendingReservation($user, $product, 1, 100000);
    $order->update([
        'status' => Order::STATUS_CONFIRMED,
        'payment_status' => Order::PAYMENT_STATUS_PAID,
        'reservation_expires_at' => now()->subMinute(),
    ]);

    $this->artisan('orders:expire-guests')->assertSuccessful();
    $this->artisan('payments:expire-pending')->assertSuccessful();

    expect($order->fresh()->status)->toBe(Order::STATUS_CONFIRMED)
        ->and($product->fresh()->inventory)->toBe(9);
});

test('reservation expiration commands remain scheduled', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('payments:expire-pending')
        ->expectsOutputToContain('orders:expire-guests')
        ->assertSuccessful();
});
