<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\GuestCheckoutBindingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

function bindingProduct(): Product
{
    $category = Category::create(['name' => 'Binding test']);

    return Product::create([
        'name' => 'Bound product',
        'img' => '/bound.png',
        'price' => 45000,
        'inventory' => 10,
        'description' => 'Description',
        'sort_description' => 'Summary',
        'facebook' => '',
        'twitter' => '',
        'instagram' => '',
        'linkedin' => '',
        'category_id' => $category->id,
    ]);
}

function bindingOrderPayload(Product $product): array
{
    return [
        'fullname' => 'Opaque Buyer',
        'address' => '123 Binding Test Street',
        'phone' => '0900000000',
        'email' => 'opaque@example.test',
        'products' => [['product_id' => $product->id, 'quantity' => 1]],
    ];
}

test('binding endpoint issues an opaque HttpOnly bounded cookie', function () {
    $response = $this->withCredentials()->postJson('/api/guest-checkout/binding')->assertCreated();
    $cookie = collect($response->headers->getCookies())
        ->first(fn ($candidate) => $candidate->getName() === GuestCheckoutBindingService::COOKIE);

    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->getPath())->toBe('/api')
        ->and($cookie->getValue())->toMatch('/^[A-Za-z0-9]{64}$/')
        ->and($response->getContent())->not->toContain($cookie->getValue());
});

test('two guest bindings on the same ip cannot replay each other', function () {
    config(['services.turnstile.required' => false]);
    $product = bindingProduct();
    $bindings = app(GuestCheckoutBindingService::class);
    $firstBinding = $bindings->rotate();
    $secondBinding = $bindings->rotate();
    $headers = ['X-Idempotency-Key' => 'same-ip-stolen-key-0001'];
    $payload = bindingOrderPayload($product);

    $first = $this->withCredentials()
        ->withUnencryptedCookie(GuestCheckoutBindingService::COOKIE, $firstBinding['token'])
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
        ->withHeaders($headers)
        ->postJson('/api/order', $payload)
        ->assertCreated();
    $second = $this->withCredentials()
        ->withUnencryptedCookie(GuestCheckoutBindingService::COOKIE, $secondBinding['token'])
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
        ->withHeaders($headers)
        ->postJson('/api/order', $payload)
        ->assertCreated()
        ->assertJsonPath('idempotent_replay', false);

    expect($second->json('data.id'))->not->toBe($first->json('data.id'))
        ->and(Order::count())->toBe(2);
});

test('same binding key and payload replays only a minimum receipt', function () {
    config(['services.turnstile.required' => false]);
    $product = bindingProduct();
    $issued = app(GuestCheckoutBindingService::class)->rotate();
    $payload = bindingOrderPayload($product);
    $headers = ['X-Idempotency-Key' => 'bound-replay-0001'];

    $request = fn () => $this->withCredentials()
        ->withUnencryptedCookie(GuestCheckoutBindingService::COOKIE, $issued['token'])
        ->withHeaders($headers)
        ->postJson('/api/order', $payload);

    $request()->assertCreated();
    $request()->assertOk()
        ->assertJsonPath('idempotent_replay', true)
        ->assertJsonMissingPath('data.fullname')
        ->assertJsonMissingPath('data.email')
        ->assertJsonMissingPath('data.phone')
        ->assertJsonMissingPath('data.address')
        ->assertJsonMissingPath('data.note')
        ->assertJsonMissingPath('data.payment_reference');
});

test('rotated expired and malformed bindings are rejected by the binding service', function () {
    $bindings = app(GuestCheckoutBindingService::class);
    $first = $bindings->rotate();
    $second = $bindings->rotate($first['token']);

    expect($bindings->verify($first['token']))->toBeNull()
        ->and($bindings->verify($second['token']))->toBe($second['hash'])
        ->and($bindings->verify('malformed'))->toBeNull();

    Cache::forget('guest-checkout-binding:'.$second['hash']);
    expect($bindings->verify($second['token']))->toBeNull();
});

test('compatibility fallback is observable without logging the raw binding', function () {
    config(['services.turnstile.required' => false]);
    Log::spy();
    $product = bindingProduct();

    $response = $this->withHeader('X-Idempotency-Key', 'compat-binding-0001')
        ->postJson('/api/order', bindingOrderPayload($product))
        ->assertCreated()
        ->assertHeader('Deprecation', 'true')
        ->assertHeader('Sunset', 'Fri, 30 Apr 2027 00:00:00 GMT');

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($candidate) => $candidate->getName() === GuestCheckoutBindingService::COOKIE);
    expect($response->headers->get('Link'))->toContain('/api/guest-checkout/binding');
    Log::shouldHaveReceived('notice')->once()->withArgs(
        fn (string $message, array $context): bool => $message === 'Legacy guest checkout was issued an opaque binding.'
            && $context === ['event' => 'guest_checkout_binding_compatibility_fallback']
            && ! str_contains(json_encode([$message, $context]), $cookie->getValue())
    );
});

test('missing binding fails closed after the approved sunset without mutation', function () {
    config([
        'api.legacy_sunset' => '2026-10-10',
        'services.turnstile.required' => false,
    ]);
    $product = bindingProduct();

    $this->withHeader('X-Idempotency-Key', 'post-sunset-binding-0001')
        ->postJson('/api/order', bindingOrderPayload($product))
        ->assertStatus(428)
        ->assertJsonPath('error_code', 'guest_checkout_binding_required');

    expect(Order::count())->toBe(0)
        ->and($product->fresh()->inventory)->toBe(10);
});
