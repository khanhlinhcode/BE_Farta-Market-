<?php

use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function canonicalVndProduct(): Product
{
    $category = Category::create(['name' => 'Money category']);

    return Product::create([
        'name' => 'Money product', 'img' => '', 'price' => 45000, 'inventory' => 5,
        'is_active' => true, 'description' => 'Description', 'sort_description' => 'Summary',
        'facebook' => '', 'twitter' => '', 'instagram' => '', 'linkedin' => '',
        'category_id' => $category->id,
    ]);
}

test('order detail coupon and usage writes persist matching canonical VND integers', function () {
    $product = canonicalVndProduct();
    $user = User::factory()->customer()->create();
    $order = Order::create([
        'user_id' => $user->id,
        'fullname' => 'Money Test',
        'status' => Order::STATUS_PENDING,
        'payment_method' => Order::PAYMENT_METHOD_COD,
        'payment_status' => Order::PAYMENT_STATUS_PENDING,
        'subtotal' => '90000.49',
        'shipping_fee' => '20000.50',
        'discount_amount' => '10000.99',
        'grand_total' => '100000.00',
    ]);
    $detail = OrderDetail::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => '45000.49',
        'product_name' => 'Legacy snapshot',
        'line_total' => '90000.50',
    ]);
    $coupon = Coupon::create([
        'code' => 'INTEGER10',
        'type' => Coupon::TYPE_FIXED,
        'value' => '10000.50',
        'min_order_amount' => '50000.49',
        'max_discount_amount' => '10000.99',
        'max_uses_per_user' => 1,
        'active' => true,
    ]);
    $usage = CouponUsage::create([
        'coupon_id' => $coupon->id,
        'user_id' => $user->id,
        'order_id' => $order->id,
        'discount_amount' => '10000.50',
    ]);

    expect($order->getRawOriginal('subtotal_vnd'))->toBe(90000)
        ->and($order->getRawOriginal('shipping_fee_vnd'))->toBe(20001)
        ->and($order->getRawOriginal('discount_amount_vnd'))->toBe(10001)
        ->and($order->getRawOriginal('grand_total_vnd'))->toBe(100000)
        ->and($detail->getRawOriginal('unit_price_vnd'))->toBe(45000)
        ->and($detail->getRawOriginal('line_total_vnd'))->toBe(90001)
        ->and($coupon->getRawOriginal('value_vnd'))->toBe(10001)
        ->and($coupon->getRawOriginal('min_order_amount_vnd'))->toBe(50000)
        ->and($coupon->getRawOriginal('max_discount_amount_vnd'))->toBe(10001)
        ->and($usage->getRawOriginal('discount_amount_vnd'))->toBe(10001);
});

test('percentage coupon keeps percentage decimal while VND limits are canonical integers', function () {
    $coupon = Coupon::create([
        'code' => 'PERCENT10',
        'type' => Coupon::TYPE_PERCENT,
        'value' => '10.50',
        'min_order_amount' => '50000.00',
        'max_discount_amount' => '25000.00',
        'max_uses_per_user' => 1,
        'active' => true,
    ]);

    expect($coupon->getRawOriginal('value'))->toBe('10.50')
        ->and($coupon->getRawOriginal('value_vnd'))->toBeNull()
        ->and($coupon->getRawOriginal('min_order_amount_vnd'))->toBe(50000)
        ->and($coupon->getRawOriginal('max_discount_amount_vnd'))->toBe(25000);
});

test('canonical reads fail closed when a legacy decimal mismatches its VND integer', function () {
    $order = Order::create([
        'fullname' => 'Mismatch Test',
        'status' => Order::STATUS_PENDING,
        'payment_method' => Order::PAYMENT_METHOD_COD,
        'payment_status' => Order::PAYMENT_STATUS_PENDING,
        'subtotal' => 10000,
    ]);
    DB::table('orders')->where('id', $order->id)->update(['subtotal_vnd' => 9999]);

    expect(fn () => Order::findOrFail($order->id)->subtotal)
        ->toThrow(LogicException::class, 'Legacy and canonical VND amounts do not match.');
});

test('legacy decimal response type remains a two-decimal string', function () {
    $order = Order::create([
        'fullname' => 'Contract Test',
        'status' => Order::STATUS_PENDING,
        'payment_method' => Order::PAYMENT_METHOD_COD,
        'payment_status' => Order::PAYMENT_STATUS_PENDING,
        'subtotal' => 90000,
        'shipping_fee' => 20000,
        'discount_amount' => 0,
        'grand_total' => 110000,
    ]);

    expect($order->fresh()->subtotal)->toBe('90000.00')
        ->and($order->fresh()->grand_total)->toBe('110000.00');
});
