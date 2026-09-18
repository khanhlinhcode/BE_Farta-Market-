<?php

use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function createSecureGuestProduct(): Product
{
    $category = Category::create(['name' => 'Security fruit']);

    return Product::create([
        'name' => 'Secure orange',
        'img' => '/orange.png',
        'price' => 45000,
        'inventory' => 10,
        'description' => 'Description',
        'sort_description' => 'Fresh',
        'facebook' => '',
        'twitter' => '',
        'instagram' => '',
        'linkedin' => '',
        'category_id' => $category->id,
    ]);
}

function secureGuestOrderPayload(Product $product, ?string $token = null): array
{
    return [
        'fullname' => 'Security Buyer',
        'address' => '123 Security Test Street',
        'phone' => '0900000000',
        'email' => 'security-buyer@example.test',
        'products' => [['product_id' => $product->id, 'quantity' => 1]],
        'turnstile_token' => $token,
    ];
}

test('production guest checkout fails closed without a valid turnstile token', function () {
    config([
        'services.turnstile.required' => true,
        'services.turnstile.secret_key' => 'configured-in-test',
    ]);
    $product = createSecureGuestProduct();

    $this->withHeader('X-Idempotency-Key', 'turnstile-missing-0001')
        ->postJson('/api/order', secureGuestOrderPayload($product))
        ->assertUnprocessable();

    Http::fake(['*' => Http::response(['success' => false])]);
    $this->withHeader('X-Idempotency-Key', 'turnstile-invalid-0001')
        ->postJson('/api/order', secureGuestOrderPayload($product, 'invalid-token'))
        ->assertUnprocessable();

    expect(Order::count())->toBe(0)
        ->and($product->fresh()->inventory)->toBe(10);
});

test('turnstile provider timeout fails closed', function () {
    config([
        'services.turnstile.required' => true,
        'services.turnstile.secret_key' => 'configured-in-test',
    ]);
    Http::fake(fn () => throw new ConnectionException('timeout'));
    $product = createSecureGuestProduct();

    $this->withHeader('X-Idempotency-Key', 'turnstile-timeout-0001')
        ->postJson('/api/order', secureGuestOrderPayload($product, 'provider-timeout'))
        ->assertUnprocessable();

    expect(Order::count())->toBe(0)
        ->and($product->fresh()->inventory)->toBe(10);
});

test('turnstile token replay is rejected while valid checkout still works', function () {
    config([
        'services.turnstile.required' => true,
        'services.turnstile.secret_key' => 'configured-in-test',
    ]);
    Http::fakeSequence()
        ->push(['success' => true])
        ->push(['success' => false]);
    $product = createSecureGuestProduct();
    $payload = secureGuestOrderPayload($product, 'one-time-token');

    $this->withHeader('X-Idempotency-Key', 'turnstile-valid-0001')
        ->postJson('/api/order', $payload)
        ->assertCreated();
    $this->withHeader('X-Idempotency-Key', 'turnstile-valid-0001')
        ->postJson('/api/order', $payload)
        ->assertUnprocessable();

    expect(Order::count())->toBe(1)
        ->and($product->fresh()->inventory)->toBe(9);
});

test('guest checkout enforces quantity boundaries before reserving inventory', function () {
    config(['services.turnstile.required' => false]);
    $products = collect(range(1, 3))->map(fn () => createSecureGuestProduct());
    $payload = secureGuestOrderPayload($products->first());
    $payload['products'] = $products->values()->map(fn (Product $product, int $index) => [
        'product_id' => $product->id,
        'quantity' => [20, 20, 11][$index],
    ])->all();

    $this->withHeader('X-Idempotency-Key', 'guest-quantity-boundary-0001')
        ->postJson('/api/order', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['products']);

    expect(Order::count())->toBe(0);
    $products->each(fn (Product $product) => expect($product->fresh()->inventory)->toBe(10));
});

test('guest checkout limits distributed requests by hashed email phone and ip', function () {
    config(['services.turnstile.required' => false]);
    $product = createSecureGuestProduct();
    $product->update(['inventory' => 100]);

    foreach (range(1, 5) as $attempt) {
        $payload = secureGuestOrderPayload($product);
        $payload['email'] = 'distributed-email@example.test';
        $payload['phone'] = '09000000'.str_pad((string) $attempt, 2, '0', STR_PAD_LEFT);
        $this->withServerVariables(['REMOTE_ADDR' => "192.0.2.{$attempt}"])
            ->withHeader('X-Idempotency-Key', "guest-email-limit-{$attempt}")
            ->postJson('/api/order', $payload)
            ->assertCreated();
    }
    $payload = secureGuestOrderPayload($product);
    $payload['email'] = 'distributed-email@example.test';
    $payload['phone'] = '0900000099';
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.99'])
        ->withHeader('X-Idempotency-Key', 'guest-email-limit-6')
        ->postJson('/api/order', $payload)
        ->assertTooManyRequests();

    foreach (range(1, 5) as $attempt) {
        $payload = secureGuestOrderPayload($product);
        $payload['email'] = "distributed-phone-{$attempt}@example.test";
        $payload['phone'] = '0911111111';
        $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.{$attempt}"])
            ->withHeader('X-Idempotency-Key', "guest-phone-limit-{$attempt}")
            ->postJson('/api/order', $payload)
            ->assertCreated();
    }
    $payload = secureGuestOrderPayload($product);
    $payload['email'] = 'distributed-phone-6@example.test';
    $payload['phone'] = '0911111111';
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])
        ->withHeader('X-Idempotency-Key', 'guest-phone-limit-6')
        ->postJson('/api/order', $payload)
        ->assertTooManyRequests();

    foreach (range(1, 5) as $attempt) {
        $payload = secureGuestOrderPayload($product);
        $payload['email'] = "distributed-ip-{$attempt}@example.test";
        $payload['phone'] = '09222222'.str_pad((string) $attempt, 2, '0', STR_PAD_LEFT);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withHeader('X-Idempotency-Key', "guest-ip-limit-{$attempt}")
            ->postJson('/api/order', $payload)
            ->assertCreated();
    }
    $payload = secureGuestOrderPayload($product);
    $payload['email'] = 'distributed-ip-6@example.test';
    $payload['phone'] = '0922222299';
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeader('X-Idempotency-Key', 'guest-ip-limit-6')
        ->postJson('/api/order', $payload)
        ->assertTooManyRequests();
});

test('pending guest order ceiling and recipient email throttle are enforced', function () {
    config(['services.turnstile.required' => false]);
    $product = createSecureGuestProduct();
    foreach (range(1, 5) as $index) {
        Order::create([
            'fullname' => 'Pending guest',
            'address' => '123 Security Test Street',
            'phone' => '09333333'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'email' => 'pending-ceiling@example.test',
            'status' => Order::STATUS_PENDING,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
        ]);
    }

    $payload = secureGuestOrderPayload($product);
    $payload['email'] = 'pending-ceiling@example.test';
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.150'])
        ->withHeader('X-Idempotency-Key', 'guest-pending-ceiling-0001')
        ->postJson('/api/order', $payload)
        ->assertTooManyRequests();
    expect($product->fresh()->inventory)->toBe(10);

    Queue::fake();
    foreach (range(1, 2) as $attempt) {
        $mailPayload = secureGuestOrderPayload($product);
        $mailPayload['email'] = 'mail-throttle@example.test';
        $mailPayload['phone'] = '09444444'.str_pad((string) $attempt, 2, '0', STR_PAD_LEFT);
        $this->withServerVariables(['REMOTE_ADDR' => "192.0.2.{$attempt}"])
            ->withHeader('X-Idempotency-Key', "guest-mail-throttle-{$attempt}")
            ->postJson('/api/order', $mailPayload)
            ->assertCreated();
    }
    Queue::assertPushed(SendOrderConfirmationEmail::class, 1);
});

test('expired guest order restores inventory exactly once', function () {
    config(['services.turnstile.required' => false]);
    $product = createSecureGuestProduct();
    $orderId = $this->withHeader('X-Idempotency-Key', 'guest-expiration-0001')
        ->postJson('/api/order', secureGuestOrderPayload($product))
        ->assertCreated()
        ->json('data.id');

    expect($product->fresh()->inventory)->toBe(9);
    $this->travel(121)->minutes();
    $this->artisan('orders:expire-guests')->assertSuccessful();
    expect(Order::findOrFail($orderId)->status)->toBe(Order::STATUS_CANCELLED)
        ->and($product->fresh()->inventory)->toBe(10);

    $this->artisan('orders:expire-guests')->assertSuccessful();
    expect($product->fresh()->inventory)->toBe(10);
});

test('guest expiration never cancels an order that has already been confirmed', function () {
    config(['services.turnstile.required' => false]);
    $product = createSecureGuestProduct();
    $orderId = $this->withHeader('X-Idempotency-Key', 'guest-confirmed-expiration-0001')
        ->postJson('/api/order', secureGuestOrderPayload($product))
        ->assertCreated()
        ->json('data.id');
    Order::query()->whereKey($orderId)->update([
        'status' => Order::STATUS_CONFIRMED,
        'guest_expires_at' => now()->subMinute(),
    ]);

    $this->artisan('orders:expire-guests')->assertSuccessful();

    expect(Order::findOrFail($orderId)->status)->toBe(Order::STATUS_CONFIRMED)
        ->and($product->fresh()->inventory)->toBe(9);
});
