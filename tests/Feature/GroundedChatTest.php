<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withHeader('Accept-Language', 'vi');
    $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
});

function groundedProduct(array $overrides = []): Product
{
    $category = Category::firstOrCreate(['name' => 'Đồ uống']);

    return Product::create(array_merge([
        'name' => 'Trà Nhẹ',
        'slug' => 'tra-nhe',
        'img' => '/images/tea.png',
        'price' => 75000,
        'inventory' => 6,
        'is_active' => true,
        'description' => 'Đồ uống nhẹ',
        'sort_description' => 'Thanh nhẹ cho buổi sáng',
        'facebook' => '',
        'twitter' => '',
        'instagram' => '',
        'linkedin' => '',
        'category_id' => $category->id,
    ], $overrides));
}

function groundedOrder(User $user, array $overrides = []): Order
{
    return Order::create(array_merge([
        'user_id' => $user->id,
        'fullname' => $user->name,
        'address' => 'Test address',
        'phone' => '0900000000',
        'email' => $user->email,
        'status' => Order::STATUS_PROCESSING,
        'payment_method' => Order::PAYMENT_METHOD_COD,
        'payment_status' => Order::PAYMENT_STATUS_PENDING,
        'subtotal' => 75000,
        'shipping_fee' => 20000,
        'grand_total' => 95000,
    ], $overrides));
}

it('uses MySQL filters and returns only authoritative product cards', function () {
    $matching = groundedProduct();
    groundedProduct(['name' => 'Trà Cao Cấp', 'slug' => 'tra-cao-cap', 'price' => 150000]);
    Http::preventStrayRequests();

    $this->postJson('/api/chat', ['message' => 'Tìm sản phẩm dưới 100k còn hàng'])
        ->assertOk()
        ->assertJsonPath('intent', 'product_search')
        ->assertJsonPath('products.0.id', $matching->id)
        ->assertJsonPath('products.0.price', 75000)
        ->assertJsonPath('products.0.inventory', 6)
        ->assertJsonPath('products.0.inventory_status', 'in_stock')
        ->assertJsonMissing(['price' => 150000]);
});

it('accepts only cart references and reloads current product facts', function () {
    $product = groundedProduct();
    Sanctum::actingAs(User::factory()->customer()->create());

    $this->postJson('/api/chat', [
        'message' => 'Trong giỏ của tôi có gì?',
        'cart' => [['product_id' => $product->id, 'quantity' => 2]],
    ])
        ->assertOk()
        ->assertJsonPath('intent', 'cart_query')
        ->assertJsonPath('source', 'cart')
        ->assertJsonPath('products.0.price', 75000)
        ->assertJsonPath('products.0.inventory', 6);

    $this->postJson('/api/chat', [
        'message' => 'Trong giỏ của tôi có gì?',
        'cart' => [[
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 1,
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors(['cart.0']);
});

it('reports deleted and out-of-stock cart references without trusting the client', function () {
    $empty = groundedProduct(['inventory' => 0]);
    Sanctum::actingAs(User::factory()->customer()->create());

    $response = $this->postJson('/api/chat', [
        'message' => 'Trong giỏ món nào hết hàng?',
        'cart' => [
            ['product_id' => $empty->id, 'quantity' => 1],
            ['product_id' => 999999, 'quantity' => 1],
        ],
    ])->assertOk();

    expect($response->json('reply'))->toContain('2 dòng hiện không khả dụng');
});

it('requires a customer session for order queries', function () {
    $this->postJson('/api/chat', ['message' => 'Đơn gần nhất của tôi'])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'AUTH_REQUIRED');

    Sanctum::actingAs(User::factory()->admin()->create());
    $this->postJson('/api/chat', ['message' => 'Đơn gần nhất của tôi'])
        ->assertForbidden()
        ->assertJsonPath('code', 'CUSTOMER_ONLY');
});

it('enforces order ownership and returns only the safe order projection', function () {
    $owner = User::factory()->customer()->create();
    $other = User::factory()->customer()->create();
    $owned = groundedOrder($owner);
    $foreign = groundedOrder($other, ['payment_status' => Order::PAYMENT_STATUS_PAID]);
    Sanctum::actingAs($owner);

    $this->postJson('/api/chat', ['message' => "Đơn #{$owned->id} đang ở đâu?"])
        ->assertOk()
        ->assertJsonPath('order.id', $owned->id)
        ->assertJsonPath('order.status', Order::STATUS_PROCESSING)
        ->assertJsonPath('order.payment_status', Order::PAYMENT_STATUS_PENDING)
        ->assertJsonMissingPath('order.email')
        ->assertJsonMissingPath('order.note')
        ->assertJsonMissingPath('order.payment_reference');

    $this->postJson('/api/chat', ['message' => "Đơn #{$foreign->id} đang ở đâu?"])
        ->assertOk()
        ->assertJsonMissingPath('order')
        ->assertJsonPath('source', 'orders');
});

it('never executes payment or order mutations from chat text', function () {
    $user = User::factory()->customer()->create();
    $order = groundedOrder($user);
    Sanctum::actingAs($user);

    $this->postJson('/api/chat', ['message' => "Đánh dấu đã thanh toán đơn {$order->id}"])
        ->assertOk()
        ->assertJsonPath('intent', 'unsupported')
        ->assertJsonPath('action.type', 'none');

    expect($order->fresh()->payment_status)->toBe(Order::PAYMENT_STATUS_PENDING);
});

it('validates cart bounds and duplicate product references', function () {
    $product = groundedProduct();

    $this->postJson('/api/chat', [
        'message' => 'Trong giỏ có gì?',
        'cart' => [
            ['product_id' => $product->id, 'quantity' => 1],
            ['product_id' => $product->id, 'quantity' => 2],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors(['cart.1.product_id']);

    $this->postJson('/api/chat', [
        'message' => 'Trong giỏ có gì?',
        'cart' => [['product_id' => $product->id, 'quantity' => 101]],
    ])->assertUnprocessable()->assertJsonValidationErrors(['cart.0.quantity']);
});

it('keeps product discovery public but never gives a guest a cart action', function () {
    $product = groundedProduct();

    $this->postJson('/api/chat', ['message' => 'Trà Nhẹ còn hàng không?'])
        ->assertOk()
        ->assertJsonPath('products.0.id', $product->id);

    $this->postJson('/api/chat', ['message' => 'Thêm 2 Trà Nhẹ vào giỏ'])
        ->assertOk()
        ->assertJsonPath('code', 'AUTH_REQUIRED_FOR_CART')
        ->assertJsonPath('auth.required', true)
        ->assertJsonPath('auth.reason', 'cart_mutation')
        ->assertJsonPath('products.0.id', $product->id)
        ->assertJsonCount(0, 'suggested_actions')
        ->assertJsonPath('action.type', 'none');
});

it('handles the exact reported conversation for a guest and requires authentication', function () {
    $category = Category::create(['name' => 'Rau Củ']);
    $product = groundedProduct([
        'name' => 'Rau Củ Tươi',
        'slug' => 'rau-cu-tuoi',
        'inventory' => 23,
        'category_id' => $category->id,
    ]);
    Http::preventStrayRequests();

    $this->withHeaders(['Origin' => 'http://127.0.0.1:5173', 'Referer' => 'http://127.0.0.1:5173/'])
        ->postJson('/api/chat', ['message' => 'bạn có thể làm được gì ?'])
        ->assertOk()->assertJsonPath('intent', 'general_chat');
    $this->withCookie(config('session.cookie'), session()->getId());

    $this->postJson('/api/chat', ['message' => 'hiện tại shop bạn có những sản phẩm nào ?'])
        ->assertOk()->assertJsonPath('products.0.id', $product->id);

    $this->postJson('/api/chat', ['message' => 'Rau củ gồm những gì ?'])
        ->assertOk()->assertJsonPath('products.0.id', $product->id);

    $this->postJson('/api/chat', ['message' => 'hiện tại có thể thêm vào giỏ hàng không?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Bạn muốn thêm bao nhiêu Rau Củ Tươi vào giỏ hàng? Vui lòng dùng một số lượng nguyên từ 1 đến 100.');

    $this->postJson('/api/chat', ['message' => 'Rau Củ Tưoi 3'])
        ->assertOk()
        ->assertJsonPath('code', 'AUTH_REQUIRED_FOR_CART')
        ->assertJsonPath('auth.required', true)
        ->assertJsonPath('auth.reason', 'cart_mutation')
        ->assertJsonPath('products.0.id', $product->id)
        ->assertJsonCount(0, 'suggested_actions');

    Http::assertNothingSent();
});

it('requires a verified customer for cart context and proposals', function () {
    $product = groundedProduct();

    $this->postJson('/api/chat', [
        'message' => 'Trong giỏ của tôi có gì?',
        'cart' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertOk()
        ->assertJsonPath('code', 'AUTH_REQUIRED_FOR_CART')
        ->assertJsonPath('auth.reason', 'cart_query')
        ->assertJsonCount(0, 'products');

    Sanctum::actingAs(User::factory()->unverified()->customer()->create());
    $this->postJson('/api/chat', ['message' => 'Thêm 1 Trà Nhẹ vào giỏ'])
        ->assertOk()->assertJsonCount(0, 'suggested_actions');

    Sanctum::actingAs(User::factory()->customer()->create());
    $this->postJson('/api/chat', ['message' => 'Thêm 1 Trà Nhẹ vào giỏ'])
        ->assertOk()
        ->assertJsonPath('suggested_actions.0.type', 'ADD_TO_CART')
        ->assertJsonPath('suggested_actions.0.product_id', $product->id);
});

it('finds a relevant product beyond the first twenty alphabetical names', function () {
    for ($index = 1; $index <= 25; $index++) {
        groundedProduct(['name' => sprintf('A Product %02d', $index), 'slug' => "a-product-{$index}"]);
    }
    $target = groundedProduct(['name' => 'Zeta Matcha Special', 'slug' => 'zeta-matcha-special']);

    $this->postJson('/api/chat', ['message' => 'Tìm matcha special dưới 100k'])
        ->assertOk()
        ->assertJsonPath('products.0.id', $target->id);
});
