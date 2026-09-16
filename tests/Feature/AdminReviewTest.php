<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('staff can moderate reviews and hidden reviews leave public rating totals', function () {
    $category = Category::create(['name' => 'Fruit']);
    $product = Product::create([
        'name' => 'Orange', 'img' => '/orange.jpg', 'price' => 10000, 'inventory' => 4,
        'is_active' => true, 'description' => 'Fresh', 'sort_description' => 'Fresh',
        'facebook' => '', 'twitter' => '', 'instagram' => '', 'linkedin' => '', 'category_id' => $category->id,
    ]);
    $customer = User::factory()->customer()->create();
    $review = Review::create(['user_id' => $customer->id, 'product_id' => $product->id, 'rating' => 5, 'comment' => 'Excellent product']);
    Review::create(['user_id' => User::factory()->customer()->create()->id, 'product_id' => $product->id, 'rating' => 1, 'comment' => 'Hidden review', 'is_visible' => false]);
    Sanctum::actingAs(User::factory()->create(['role' => 'staff']));

    $this->getJson('/api/admin/reviews?rating=5')->assertOk()->assertJsonPath('meta.total', 1);
    $this->patchJson("/api/admin/reviews/{$review->id}/visibility", ['is_visible' => false])
        ->assertOk()->assertJsonPath('is_visible', false);

    auth()->forgetGuards();
    $this->getJson("/api/products/{$product->id}/reviews")
        ->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('summary.review_count', 0);
    $this->getJson("/api/products/{$product->id}")
        ->assertOk()->assertJsonPath('reviews_count', 0);

    Sanctum::actingAs($customer);
    $this->postJson("/api/wishlist/{$product->id}")
        ->assertCreated()->assertJsonPath('data.reviews_count', 0);
});

test('customer cannot access review moderation', function () {
    Sanctum::actingAs(User::factory()->customer()->create());

    $this->getJson('/api/admin/reviews')->assertForbidden();
});
