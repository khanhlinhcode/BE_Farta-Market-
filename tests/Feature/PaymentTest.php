<?php

use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Category;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

function configureSepayTest(): void
{
    config()->set('services.sepay.bank_code', 'Vietcombank');
    config()->set('services.sepay.account_number', '0010000000355');
    config()->set('services.sepay.account_holder', 'FARTA MARKET');
    config()->set('services.sepay.webhook_secret', 'local-test-secret');
    config()->set('services.sepay.payment_prefix', 'FM');
    config()->set('services.sepay.payment_ttl_minutes', 30);
    config()->set('services.sepay.webhook_tolerance_seconds', 300);
    config()->set('services.sepay.qr_base_url', 'https://vietqr.app/img');
}

function sepayWebhookPayload(Order $order, array $overrides = []): array
{
    return array_merge([
        'id' => 123456,
        'accountNumber' => '0010000000355',
        'transferType' => 'in',
        'transferAmount' => (int) $order->grand_total,
        'code' => $order->payment_reference,
        'content' => $order->payment_reference.' thanh toan don hang',
    ], $overrides);
}

function sepayWebhookServer(string $rawBody, ?int $timestamp = null): array
{
    $timestamp ??= now()->timestamp;
    $signature = 'sha256='.hash_hmac(
        'sha256',
        $timestamp.'.'.$rawBody,
        'local-test-secret'
    );

    return [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SEPAY_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_SEPAY_SIGNATURE' => $signature,
    ];
}

test('authenticated customer can create a SePay QR payment', function () {
    configureSepayTest();
    $user = User::factory()->customer()->create();
    $product = createPaymentProduct();
    Sanctum::actingAs($user);

    $response = $this->withHeader('X-Idempotency-Key', 'sepay-create-test-0001')
        ->postJson('/api/payment/create', paymentPayload($product))
        ->assertCreated()
        ->assertJsonPath('data.status', Order::STATUS_PENDING)
        ->assertJsonPath('data.payment_method', Order::PAYMENT_METHOD_SEPAY)
        ->assertJsonPath('data.payment_status', Order::PAYMENT_STATUS_PENDING)
        ->assertJsonPath('data.subtotal', '90000.00')
        ->assertJsonPath('data.shipping_fee', '20000.00')
        ->assertJsonPath('data.grand_total', '110000.00')
        ->assertJsonPath('payment.amount', 110000)
        ->assertJsonPath('payment.bank_code', 'Vietcombank')
        ->assertJsonPath('payment.account_number', '0010000000355');

    expect($response->json('payment.reference'))->toStartWith('FM');
    expect($response->json('payment.qr_url'))
        ->toContain('https://vietqr.app/img?')
        ->toContain('amount=110000')
        ->toContain('des='.urlencode($response->json('payment.reference')));
    expect($product->fresh()->inventory)->toBe(8);
});

test('SePay payment requires an idempotency key header', function () {
    configureSepayTest();
    Sanctum::actingAs(User::factory()->customer()->create());

    $this->postJson('/api/payment/create', paymentPayload(createPaymentProduct()))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Header X-Idempotency-Key là bắt buộc.');
});

test('replaying a SePay idempotency key returns the same order and reference', function () {
    configureSepayTest();
    $user = User::factory()->customer()->create();
    $product = createPaymentProduct();
    $headers = ['X-Idempotency-Key' => 'sepay-replay-test-0001'];
    Sanctum::actingAs($user);

    $first = $this->withHeaders($headers)
        ->postJson('/api/payment/create', paymentPayload($product))
        ->assertCreated()
        ->assertJsonPath('idempotent_replay', false);

    $second = $this->withHeaders($headers)
        ->postJson('/api/payment/create', paymentPayload($product))
        ->assertOk()
        ->assertJsonPath('idempotent_replay', true);

    expect($second->json('data.id'))->toBe($first->json('data.id'));
    expect($second->json('payment.reference'))->toBe($first->json('payment.reference'));
    expect(Order::count())->toBe(1);
    expect($product->fresh()->inventory)->toBe(8);
});

test('reusing a SePay idempotency key with a different payload is rejected', function () {
    configureSepayTest();
    $user = User::factory()->customer()->create();
    $product = createPaymentProduct();
    $headers = ['X-Idempotency-Key' => 'sepay-conflict-test-0001'];
    Sanctum::actingAs($user);

    $this->withHeaders($headers)
        ->postJson('/api/payment/create', paymentPayload($product, 1))
        ->assertCreated();

    $this->withHeaders($headers)
        ->postJson('/api/payment/create', paymentPayload($product, 2))
        ->assertConflict()
        ->assertJsonPath('message', 'Idempotency key đã được dùng với request khác.');

    expect(Order::count())->toBe(1);
    expect($product->fresh()->inventory)->toBe(9);
});

test('valid SePay webhook confirms payment and queues one email', function () {
    configureSepayTest();
    Queue::fake();
    $user = User::factory()->customer()->create();
    $product = createPaymentProduct();
    Sanctum::actingAs($user);
    $orderId = $this->withHeader('X-Idempotency-Key', 'sepay-paid-test-0001')
        ->postJson('/api/payment/create', paymentPayload($product))
        ->assertCreated()
        ->json('data.id');
    $order = Order::findOrFail($orderId);
    $payload = sepayWebhookPayload($order);
    $rawBody = json_encode($payload);

    $this->call(
        'POST',
        '/api/payment/sepay/webhook',
        [],
        [],
        [],
        sepayWebhookServer($rawBody),
        $rawBody
    )->assertOk()->assertExactJson(['success' => true]);

    $order->refresh();
    expect($order->status)->toBe(Order::STATUS_CONFIRMED);
    expect($order->payment_status)->toBe(Order::PAYMENT_STATUS_PAID);
    expect($order->payment_transaction_id)->toBe('123456');
    expect($product->fresh()->inventory)->toBe(8);
    Queue::assertPushed(SendOrderConfirmationEmail::class, 1);
});

test('SePay webhook replay is idempotent', function () {
    configureSepayTest();
    Queue::fake();
    $user = User::factory()->customer()->create();
    $product = createPaymentProduct();
    Sanctum::actingAs($user);
    $orderId = $this->withHeader('X-Idempotency-Key', 'sepay-webhook-replay-0001')
        ->postJson('/api/payment/create', paymentPayload($product))
        ->assertCreated()
        ->json('data.id');
    $payload = sepayWebhookPayload(Order::findOrFail($orderId));
    $rawBody = json_encode($payload);
    $server = sepayWebhookServer($rawBody);

    $this->call('POST', '/api/payment/sepay/webhook', [], [], [], $server, $rawBody)
        ->assertOk()
        ->assertExactJson(['success' => true]);
    $this->call('POST', '/api/payment/sepay/webhook', [], [], [], $server, $rawBody)
        ->assertOk()
        ->assertExactJson(['success' => true]);

    Queue::assertPushed(SendOrderConfirmationEmail::class, 1);
    expect($product->fresh()->inventory)->toBe(8);
});

test('SePay webhook can match the order reference from content when code is null', function () {
    configureSepayTest();
    Queue::fake();
    $user = User::factory()->customer()->create();
    Sanctum::actingAs($user);
    $orderId = $this->withHeader('X-Idempotency-Key', 'sepay-content-code-test-0001')
        ->postJson('/api/payment/create', paymentPayload(createPaymentProduct()))
        ->assertCreated()
        ->json('data.id');
    $order = Order::findOrFail($orderId);
    $payload = sepayWebhookPayload($order, [
        'code' => null,
        'content' => 'Chuyen tien '.$order->payment_reference.' thanh toan',
    ]);
    $rawBody = json_encode($payload);

    $this->call(
        'POST',
        '/api/payment/sepay/webhook',
        [],
        [],
        [],
        sepayWebhookServer($rawBody),
        $rawBody
    )->assertOk()->assertExactJson(['success' => true]);

    expect($order->fresh()->payment_status)->toBe(Order::PAYMENT_STATUS_PAID);
    Queue::assertPushed(SendOrderConfirmationEmail::class, 1);
});

test('invalid or stale SePay webhook signatures cannot change payment state', function () {
    configureSepayTest();
    $user = User::factory()->customer()->create();
    Sanctum::actingAs($user);
    $orderId = $this->withHeader('X-Idempotency-Key', 'sepay-signature-test-0001')
        ->postJson('/api/payment/create', paymentPayload(createPaymentProduct()))
        ->assertCreated()
        ->json('data.id');
    $rawBody = json_encode(sepayWebhookPayload(Order::findOrFail($orderId)));
    $server = sepayWebhookServer($rawBody);
    $server['HTTP_X_SEPAY_SIGNATURE'] = 'sha256='.str_repeat('0', 64);

    $this->call('POST', '/api/payment/sepay/webhook', [], [], [], $server, $rawBody)
        ->assertUnauthorized();
    $this->call(
        'POST',
        '/api/payment/sepay/webhook',
        [],
        [],
        [],
        sepayWebhookServer($rawBody, now()->subMinutes(6)->timestamp),
        $rawBody
    )->assertUnauthorized();

    expect(Order::findOrFail($orderId)->payment_status)->toBe(Order::PAYMENT_STATUS_PENDING);
});

test('SePay webhook rejects a wrong account or amount', function () {
    configureSepayTest();
    $user = User::factory()->customer()->create();
    Sanctum::actingAs($user);
    $orderId = $this->withHeader('X-Idempotency-Key', 'sepay-mismatch-test-0001')
        ->postJson('/api/payment/create', paymentPayload(createPaymentProduct()))
        ->assertCreated()
        ->json('data.id');
    $order = Order::findOrFail($orderId);

    foreach ([
        ['accountNumber' => '9999999999'],
        ['transferAmount' => 1, 'id' => 123457],
    ] as $overrides) {
        $rawBody = json_encode(sepayWebhookPayload($order, $overrides));
        $this->call(
            'POST',
            '/api/payment/sepay/webhook',
            [],
            [],
            [],
            sepayWebhookServer($rawBody),
            $rawBody
        )->assertUnprocessable();
    }

    expect($order->fresh()->payment_status)->toBe(Order::PAYMENT_STATUS_PENDING);
});

test('a paid webhook cannot revive a customer-cancelled SePay order', function () {
    configureSepayTest();
    Queue::fake();
    $user = User::factory()->customer()->create();
    $product = createPaymentProduct();
    Sanctum::actingAs($user);
    $orderId = $this->withHeader('X-Idempotency-Key', 'sepay-cancelled-test-0001')
        ->postJson('/api/payment/create', paymentPayload($product))
        ->assertCreated()
        ->json('data.id');
    $order = Order::findOrFail($orderId);

    $this->patchJson("/api/my-orders/{$orderId}/cancel")
        ->assertOk()
        ->assertJsonPath('status', Order::STATUS_CANCELLED);
    $rawBody = json_encode(sepayWebhookPayload($order));
    $this->call(
        'POST',
        '/api/payment/sepay/webhook',
        [],
        [],
        [],
        sepayWebhookServer($rawBody),
        $rawBody
    )->assertConflict();

    expect($order->fresh()->payment_status)->toBe(Order::PAYMENT_STATUS_FAILED);
    expect($product->fresh()->inventory)->toBe(10);
    Queue::assertNothingPushed();
});

test('expired pending SePay payments restore inventory once', function () {
    configureSepayTest();
    $user = User::factory()->customer()->create();
    $product = createPaymentProduct();
    Sanctum::actingAs($user);
    $orderId = $this->withHeader('X-Idempotency-Key', 'sepay-expire-test-0001')
        ->postJson('/api/payment/create', paymentPayload($product))
        ->assertCreated()
        ->json('data.id');
    Order::query()->whereKey($orderId)->update([
        'created_at' => now()->subMinutes(31),
        'payment_expires_at' => now()->subMinute(),
    ]);

    $this->artisan('payments:expire-pending')->assertSuccessful();
    $this->artisan('payments:expire-pending')->assertSuccessful();

    $order = Order::findOrFail($orderId);
    expect($order->status)->toBe(Order::STATUS_CANCELLED);
    expect($order->payment_status)->toBe(Order::PAYMENT_STATUS_FAILED);
    expect($product->fresh()->inventory)->toBe(10);
});

test('missing SePay configuration rolls back order reservation and idempotency record', function () {
    configureSepayTest();
    config()->set('services.sepay.account_number', '');
    $user = User::factory()->customer()->create();
    $product = createPaymentProduct();
    Sanctum::actingAs($user);

    $this->withHeader('X-Idempotency-Key', 'sepay-config-test-0001')
        ->postJson('/api/payment/create', paymentPayload($product))
        ->assertServiceUnavailable();

    expect(Order::count())->toBe(0);
    expect(IdempotencyKey::count())->toBe(0);
    expect($product->fresh()->inventory)->toBe(10);
});

test('customers can only read their own SePay payment status', function () {
    configureSepayTest();
    $owner = User::factory()->customer()->create();
    Sanctum::actingAs($owner);
    $orderId = $this->withHeader('X-Idempotency-Key', 'sepay-status-test-0001')
        ->postJson('/api/payment/create', paymentPayload(createPaymentProduct()))
        ->assertCreated()
        ->json('data.id');

    $this->getJson("/api/payment/{$orderId}/status")
        ->assertOk()
        ->assertJsonPath('data.id', $orderId)
        ->assertJsonPath('payment.account_number', '0010000000355');

    Sanctum::actingAs(User::factory()->customer()->create());
    $this->getJson("/api/payment/{$orderId}/status")->assertNotFound();
});
