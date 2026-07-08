<?php

use App\Models\Category;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Jobs\SendOrderConfirmationEmail;
use App\Mail\OrderConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createOrderProduct(array $overrides = []): Product
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

function orderPayload(Product $product, int $quantity = 2): array
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

test('placing an order creates snapshot and admin total uses line totals', function () {
    $product = createOrderProduct();

    $this->withHeader('X-Idempotency-Key', 'order-snapshot-test-0001')
        ->postJson('/api/order', orderPayload($product))
        ->assertCreated()
        ->assertJsonPath('idempotent_replay', false);

    $detail = DB::table('order_details')->first();

    expect($detail->product_name)->toBe('Cam Tươi');
    expect((float) $detail->unit_price)->toBe(45000.0);
    expect((float) $detail->line_total)->toBe(90000.0);
    expect($product->fresh()->inventory)->toBe(8);

    $product->update(['price' => 99000]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->getJson('/api/admin/orders')->assertOk();

    expect((float) $response->json('0.total'))->toBe(90000.0);
});

test('placing a new order queues confirmation email', function () {
    Queue::fake();
    $product = createOrderProduct();

    $orderId = $this->withHeader('X-Idempotency-Key', 'order-email-test-0001')
        ->postJson('/api/order', orderPayload($product))
        ->assertCreated()
        ->json('data.id');

    Queue::assertPushedOn(
        'emails',
        SendOrderConfirmationEmail::class,
        fn (SendOrderConfirmationEmail $job) => $job->orderId === $orderId
    );
});

test('order confirmation email uses stored order totals including discount and shipping', function () {
    $product = createOrderProduct();
    $order = Order::create([
        'fullname' => 'Nguyen Van A',
        'address' => 'Da Nang City',
        'phone' => '0900000000',
        'email' => 'customer@example.test',
        'status' => Order::STATUS_PENDING,
        'payment_method' => Order::PAYMENT_METHOD_COD,
        'payment_status' => Order::PAYMENT_STATUS_PENDING,
        'subtotal' => 90000,
        'discount_amount' => 10000,
        'shipping_fee' => 20000,
        'grand_total' => 100000,
    ]);
    $order->details()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 45000,
        'product_name' => $product->name,
        'line_total' => 90000,
    ]);

    $html = (new OrderConfirmation($order->load('details.product')))->render();

    expect($html)->toContain('Tạm tính')
        ->and($html)->toContain('90.000đ')
        ->and($html)->toContain('-10.000đ')
        ->and($html)->toContain('20.000đ')
        ->and($html)->toContain('100.000đ');
});

test('replaying an idempotency key returns the same order without decrementing stock twice', function () {
    $product = createOrderProduct();
    $payload = orderPayload($product);
    $headers = ['X-Idempotency-Key' => 'order-replay-test-0001'];

    $firstResponse = $this->withHeaders($headers)
        ->postJson('/api/order', $payload)
        ->assertCreated();
    $secondResponse = $this->withHeaders($headers)
        ->postJson('/api/order', $payload)
        ->assertOk()
        ->assertJsonPath('idempotent_replay', true);

    expect($secondResponse->json('data.id'))->toBe($firstResponse->json('data.id'));
    expect(Order::count())->toBe(1);
    expect($product->fresh()->inventory)->toBe(8);
});

test('reusing an idempotency key with a different payload returns conflict', function () {
    $product = createOrderProduct();
    $headers = ['X-Idempotency-Key' => 'order-conflict-test-0001'];

    $this->withHeaders($headers)
        ->postJson('/api/order', orderPayload($product, 2))
        ->assertCreated();

    $this->withHeaders($headers)
        ->postJson('/api/order', orderPayload($product, 1))
        ->assertConflict()
        ->assertJsonPath('message', 'Idempotency key đã được dùng với request khác.');

    expect(Order::count())->toBe(1);
    expect($product->fresh()->inventory)->toBe(8);
});

test('reusing an order idempotency key with a changed email returns conflict', function () {
    $product = createOrderProduct();
    $headers = ['X-Idempotency-Key' => 'order-conflict-email-0001'];
    $payload = orderPayload($product, 2);

    $this->withHeaders($headers)
        ->postJson('/api/order', $payload)
        ->assertCreated();

    $payload['email'] = 'another-customer@example.test';

    $this->withHeaders($headers)
        ->postJson('/api/order', $payload)
        ->assertConflict()
        ->assertJsonPath('message', 'Idempotency key đã được dùng với request khác.');

    expect(Order::count())->toBe(1);
    expect($product->fresh()->inventory)->toBe(8);
});

test('reusing an order idempotency key with changed customer name or note returns conflict', function () {
    $product = createOrderProduct();
    $headers = ['X-Idempotency-Key' => 'order-conflict-profile-fields-0001'];
    $payload = orderPayload($product, 2);
    $payload['note'] = 'Leave at reception';

    $this->withHeaders($headers)
        ->postJson('/api/order', $payload)
        ->assertCreated();

    $payload['customer_name'] = 'Tran Thi B';

    $this->withHeaders($headers)
        ->postJson('/api/order', $payload)
        ->assertConflict()
        ->assertJsonPath('message', 'Idempotency key đã được dùng với request khác.');

    $payload['customer_name'] = 'Nguyen Van A';
    $payload['note'] = 'Call before delivery';

    $this->withHeaders($headers)
        ->postJson('/api/order', $payload)
        ->assertConflict()
        ->assertJsonPath('message', 'Idempotency key đã được dùng với request khác.');

    expect(Order::count())->toBe(1);
    expect($product->fresh()->inventory)->toBe(8);
});

test('the same idempotency key is scoped per authenticated user', function () {
    $product = createOrderProduct(['inventory' => 10]);
    $key = 'order-user-scope-test-0001';
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $this->withToken($userA->createToken('customer')->plainTextToken)
        ->withHeader('X-Idempotency-Key', $key)
        ->postJson('/api/order', orderPayload($product, 2))
        ->assertCreated();

    $this->withToken($userB->createToken('customer')->plainTextToken)
        ->withHeader('X-Idempotency-Key', $key)
        ->postJson('/api/order', orderPayload($product, 1))
        ->assertCreated();

    expect(Order::count())->toBe(2);
    expect(IdempotencyKey::count())->toBe(2);
    expect($product->fresh()->inventory)->toBe(7);
});

test('expired idempotency keys are not replayed', function () {
    $product = createOrderProduct(['inventory' => 10]);
    $headers = ['X-Idempotency-Key' => 'order-expired-test-0001'];

    $this->withHeaders($headers)
        ->postJson('/api/order', orderPayload($product, 2))
        ->assertCreated();

    IdempotencyKey::query()->update(['expires_at' => now()->subMinute()]);

    $this->withHeaders($headers)
        ->postJson('/api/order', orderPayload($product, 2))
        ->assertCreated()
        ->assertJsonPath('idempotent_replay', false);

    expect(Order::count())->toBe(2);
    expect(IdempotencyKey::count())->toBe(1);
    expect($product->fresh()->inventory)->toBe(6);
});

test('prune command deletes expired idempotency keys', function () {
    $product = createOrderProduct();

    $this->withHeader('X-Idempotency-Key', 'order-prune-test-0001')
        ->postJson('/api/order', orderPayload($product))
        ->assertCreated();

    IdempotencyKey::query()->update(['expires_at' => now()->subMinute()]);

    $this->artisan('idempotency:prune')
        ->expectsOutput('Deleted 1 expired idempotency key(s).')
        ->assertExitCode(0);

    expect(IdempotencyKey::count())->toBe(0);
});

test('order creation handles multiple products while locking them by sorted id', function () {
    $firstProduct = createOrderProduct(['name' => 'Cam Tươi', 'inventory' => 10]);
    $secondProduct = createOrderProduct(['name' => 'Táo Úc', 'inventory' => 10]);

    $payload = orderPayload($firstProduct);
    $payload['products'] = [
        [
            'product_id' => $secondProduct->id,
            'quantity' => 3,
        ],
        [
            'product_id' => $firstProduct->id,
            'quantity' => 2,
        ],
    ];

    $this->withHeader('X-Idempotency-Key', 'order-lock-sort-test-0001')
        ->postJson('/api/order', $payload)
        ->assertCreated();

    expect($firstProduct->fresh()->inventory)->toBe(8);
    expect($secondProduct->fresh()->inventory)->toBe(7);
});

test('invalid order request does not decrement inventory', function () {
    $product = createOrderProduct();

    $this->postJson('/api/order', orderPayload($product))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Header X-Idempotency-Key là bắt buộc.');

    expect(Order::count())->toBe(0);
    expect($product->fresh()->inventory)->toBe(10);
});

test('order idempotency key is accepted from header only', function () {
    $product = createOrderProduct();

    $payload = orderPayload($product);
    $payload['idempotency_key'] = 'body-key-should-not-work';

    $this->postJson('/api/order', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Header X-Idempotency-Key là bắt buộc.');

    expect(Order::count())->toBe(0);
    expect($product->fresh()->inventory)->toBe(10);
});

test('order requires customer email to match checkout validation', function () {
    $product = createOrderProduct();
    $payload = orderPayload($product);
    unset($payload['email']);

    $this->withHeader('X-Idempotency-Key', 'order-email-required-0001')
        ->postJson('/api/order', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect(Order::count())->toBe(0);
    expect($product->fresh()->inventory)->toBe(10);
});

test('insufficient inventory rolls back the entire order', function () {
    $product = createOrderProduct(['inventory' => 1]);

    $this->withHeader('X-Idempotency-Key', 'order-stock-test-0001')
        ->postJson('/api/order', orderPayload($product, 2))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Sản phẩm Cam Tươi không đủ tồn kho.');

    expect(Order::count())->toBe(0);
    expect($product->fresh()->inventory)->toBe(1);
});

test('guest order creation is rate limited after five requests per minute', function () {
    $product = createOrderProduct(['inventory' => 100]);
    $server = ['REMOTE_ADDR' => '203.0.113.55'];

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->withServerVariables($server)
            ->withHeader('X-Idempotency-Key', "order-rate-test-000{$attempt}")
            ->postJson('/api/order', orderPayload($product, 1))
            ->assertCreated();
    }

    $this->withServerVariables($server)
        ->withHeader('X-Idempotency-Key', 'order-rate-test-0006')
        ->postJson('/api/order', orderPayload($product, 1))
        ->assertTooManyRequests();

    expect(Order::count())->toBe(5);
    expect($product->fresh()->inventory)->toBe(95);
});

test('cancelling an order repeatedly restores inventory only once', function () {
    $product = createOrderProduct();

    $orderId = $this->withHeader('X-Idempotency-Key', 'order-cancel-test-0001')
        ->postJson('/api/order', orderPayload($product))
        ->assertCreated()
        ->json('data.id');

    expect($product->fresh()->inventory)->toBe(8);

    Sanctum::actingAs(User::factory()->admin()->create());

    $this->patchJson("/api/admin/orders/{$orderId}/status", [
        'status' => Order::STATUS_CANCELLED,
    ])->assertOk();
    $this->patchJson("/api/admin/orders/{$orderId}/status", [
        'status' => Order::STATUS_CANCELLED,
    ])->assertOk();

    expect($product->fresh()->inventory)->toBe(10);
});
