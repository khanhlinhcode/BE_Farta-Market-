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

test('review requires a completed purchase by the authenticated user', function () {
    $buyer = User::factory()->create();
    $otherUser = User::factory()->create();
    $product = createCommerceProduct();

    Sanctum::actingAs($otherUser);

    $this->postJson("/api/products/{$product->id}/reviews", [
        'rating' => 5,
        'comment' => 'Sản phẩm rất tươi.',
    ])->assertForbidden();

    $order = Order::create([
        'user_id' => $buyer->id,
        'fullname' => 'Nguyen Van A',
        'address' => 'Da Nang City',
        'phone' => '0900000000',
        'email' => 'customer@example.test',
        'status' => Order::STATUS_ORDERED,
    ]);
    $order->details()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 45000,
        'product_name' => $product->name,
        'line_total' => 90000,
    ]);

    Sanctum::actingAs($buyer);

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
