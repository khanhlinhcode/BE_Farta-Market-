<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createCommerceProduct(array $overrides = []): Product
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

function createReviewOrder(User $user, Product $product, array $overrides = []): Order
{
    $order = Order::create(array_merge([
        'user_id' => $user->id,
        'fullname' => 'Nguyen Van A',
        'address' => 'Da Nang City',
        'phone' => '0900000000',
        'email' => 'customer@example.test',
        'status' => Order::STATUS_ORDERED,
        'payment_method' => Order::PAYMENT_METHOD_COD,
        'payment_status' => Order::PAYMENT_STATUS_PENDING,
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

test('authenticated user can add list and remove wishlist products', function () {
    $user = User::factory()->create();
    $product = createCommerceProduct();

    Sanctum::actingAs($user);

    $this->postJson("/api/wishlist/{$product->id}")
        ->assertCreated()
        ->assertJsonPath('data.id', $product->id);

    $this->getJson('/api/wishlist')
        ->assertOk()
        ->assertJsonPath('ids.0', $product->id)
        ->assertJsonPath('data.0.name', 'Cam Tươi');

    $this->deleteJson("/api/wishlist/{$product->id}")
        ->assertNoContent();

    $this->getJson('/api/wishlist')
        ->assertOk()
        ->assertJsonPath('ids', []);
});

test('review requires a delivered purchase by the authenticated user', function () {
    $buyer = User::factory()->create();
    $otherUser = User::factory()->create();
    $product = createCommerceProduct();

    Sanctum::actingAs($otherUser);

    $this->postJson("/api/products/{$product->id}/reviews", [
        'rating' => 5,
        'comment' => 'Sản phẩm rất tươi.',
    ])->assertForbidden();

    $order = createReviewOrder($buyer, $product);

    Sanctum::actingAs($buyer);

    $this->getJson("/api/products/{$product->id}/reviews/eligibility")
        ->assertOk()
        ->assertJsonPath('can_review', false);

    $order->update(['status' => Order::STATUS_DELIVERED]);

    $this->getJson("/api/products/{$product->id}/reviews/eligibility")
        ->assertOk()
        ->assertJsonPath('can_review', true);

    $this->postJson("/api/products/{$product->id}/reviews", [
        'rating' => 5,
        'comment' => 'Sản phẩm rất tươi.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.rating', 5)
        ->assertJsonPath('summary.review_count', 1);

    $this->getJson("/api/products/{$product->id}/reviews")
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.comment', 'Sản phẩm rất tươi.');

    $this->getJson("/api/products/{$product->id}/reviews/eligibility")
        ->assertOk()
        ->assertJsonPath('can_review', false)
        ->assertJsonPath('has_reviewed', true);
});

test('pending and failed vnpay orders cannot review products', function () {
    $buyer = User::factory()->create();
    $product = createCommerceProduct();

    createReviewOrder($buyer, $product, [
        'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_method' => Order::PAYMENT_METHOD_VNPAY,
        'payment_status' => Order::PAYMENT_STATUS_PENDING,
    ]);

    Sanctum::actingAs($buyer);

    $this->getJson("/api/products/{$product->id}/reviews/eligibility")
        ->assertOk()
        ->assertJsonPath('can_review', false);

    $this->postJson("/api/products/{$product->id}/reviews", [
        'rating' => 5,
        'comment' => 'Không được đánh giá đơn chưa thanh toán.',
    ])->assertForbidden();

    Order::query()->delete();

    createReviewOrder($buyer, $product, [
        'status' => Order::STATUS_PAYMENT_FAILED,
        'payment_method' => Order::PAYMENT_METHOD_VNPAY,
        'payment_status' => Order::PAYMENT_STATUS_FAILED,
    ]);

    $this->getJson("/api/products/{$product->id}/reviews/eligibility")
        ->assertOk()
        ->assertJsonPath('can_review', false);
});

test('paid vnpay orders cannot review until delivered', function () {
    $buyer = User::factory()->create();
    $product = createCommerceProduct();

    createReviewOrder($buyer, $product, [
        'status' => Order::STATUS_CONFIRMED,
        'payment_method' => Order::PAYMENT_METHOD_VNPAY,
        'payment_status' => Order::PAYMENT_STATUS_PAID,
    ]);

    Sanctum::actingAs($buyer);

    $this->getJson("/api/products/{$product->id}/reviews/eligibility")
        ->assertOk()
        ->assertJsonPath('can_review', false);

    Order::query()->update(['status' => Order::STATUS_DELIVERED]);

    $this->getJson("/api/products/{$product->id}/reviews/eligibility")
        ->assertOk()
        ->assertJsonPath('can_review', true);
});

test('admin or staff account cannot submit product reviews', function () {
    $admin = User::factory()->admin()->create();
    $product = createCommerceProduct();

    Sanctum::actingAs($admin);

    $this->postJson("/api/products/{$product->id}/reviews", [
        'rating' => 5,
        'comment' => 'Admin should not review products.',
    ])->assertForbidden();

    $this->getJson("/api/products/{$product->id}/reviews/eligibility")
        ->assertOk()
        ->assertJsonPath('can_review', false);
});
