<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createPaymentProduct(array $overrides = []): Product
{
    $category = Category::firstOrCreate(['name' => 'Fruit']);

    return Product::create(array_merge([
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
        'category_id' => $category->id,
    ], $overrides));
}

function paymentPayload(Product $product, int $quantity = 2): array
{
    return [
        'fullname' => 'Nguyen Van A',
        'address' => 'Da Nang City',
        'phone' => '0900000000',
        'email' => 'customer@example.test',
        'products' => [
            [
                'product_id' => $product->id,
                'quantity' => $quantity,
            ],
        ],
    ];
}

function configureVnpayTest(): void
{
    config()->set('services.vnpay.tmn_code', 'TESTCODE');
    config()->set('services.vnpay.hash_secret', 'test-secret');
    config()->set('services.vnpay.url', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
    config()->set('services.vnpay.return_url', 'http://127.0.0.1:8000/api/payment/vnpay-return');
    config()->set('services.vnpay.frontend_url', 'http://127.0.0.1:5173');
}

function signedVnpayReturnParams(array $params): array
{
    ksort($params);
    $hashData = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $params['vnp_SecureHash'] = hash_hmac('sha512', $hashData, 'test-secret');

    return $params;
}

test('authenticated user can create a vnpay payment url', function () {
    configureVnpayTest();
    $user = User::factory()->create();
    $product = createPaymentProduct();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/payment/create', paymentPayload($product))
        ->assertCreated()
        ->assertJsonPath('data.status', Order::STATUS_PENDING_PAYMENT)
        ->assertJsonPath('data.payment_method', Order::PAYMENT_METHOD_VNPAY)
        ->assertJsonPath('data.payment_status', Order::PAYMENT_STATUS_PENDING);

    $paymentUrl = $response->json('payment_url');
    expect($paymentUrl)->toContain('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
    expect($paymentUrl)->toContain('vnp_SecureHash=');

    parse_str(parse_url($paymentUrl, PHP_URL_QUERY), $query);
    expect($query['vnp_TmnCode'])->toBe('TESTCODE');
    expect((int) $query['vnp_Amount'])->toBe(9000000);
    expect($product->fresh()->inventory)->toBe(8);
});

test('vnpay return marks order as paid when signature and response are valid', function () {
    configureVnpayTest();
    $user = User::factory()->create();
    $product = createPaymentProduct();

    Sanctum::actingAs($user);

    $orderId = $this->postJson('/api/payment/create', paymentPayload($product))
        ->assertCreated()
        ->json('data.id');

    $params = signedVnpayReturnParams([
        'vnp_Amount' => '9000000',
        'vnp_ResponseCode' => '00',
        'vnp_TransactionStatus' => '00',
        'vnp_TmnCode' => 'TESTCODE',
        'vnp_TxnRef' => (string) $orderId,
    ]);

    $this->get('/api/payment/vnpay-return?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986))
        ->assertRedirect("http://127.0.0.1:5173/dat-hang-thanh-cong?orderId={$orderId}&payment=vnpay");

    $order = Order::findOrFail($orderId);
    expect($order->status)->toBe(Order::STATUS_PREPARING);
    expect($order->payment_status)->toBe(Order::PAYMENT_STATUS_PAID);
    expect($product->fresh()->inventory)->toBe(8);
});

test('vnpay return marks order as failed and restores inventory on failed payment', function () {
    configureVnpayTest();
    $user = User::factory()->create();
    $product = createPaymentProduct();

    Sanctum::actingAs($user);

    $orderId = $this->postJson('/api/payment/create', paymentPayload($product))
        ->assertCreated()
        ->json('data.id');

    $params = signedVnpayReturnParams([
        'vnp_Amount' => '9000000',
        'vnp_ResponseCode' => '24',
        'vnp_TransactionStatus' => '02',
        'vnp_TmnCode' => 'TESTCODE',
        'vnp_TxnRef' => (string) $orderId,
    ]);

    $this->get('/api/payment/vnpay-return?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986))
        ->assertRedirect("http://127.0.0.1:5173/thanh-toan?error=payment_failed&orderId={$orderId}");

    $order = Order::findOrFail($orderId);
    expect($order->status)->toBe(Order::STATUS_PAYMENT_FAILED);
    expect($order->payment_status)->toBe(Order::PAYMENT_STATUS_FAILED);
    expect($product->fresh()->inventory)->toBe(10);
});
