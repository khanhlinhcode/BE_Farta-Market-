<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createUpgradeProduct(array $overrides = []): Product
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

test('customer can view update profile and revoke tokens after password change', function () {
    $user = User::factory()->customer()->create([
        'phone' => null,
        'default_address' => null,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('email', $user->email);

    $this->patchJson('/api/profile', [
        'name' => 'Linh Nguyen',
        'phone' => '0901234567',
        'default_address' => '123 Duong Test, Da Nang',
    ])
        ->assertOk()
        ->assertJsonPath('name', 'Linh Nguyen')
        ->assertJsonPath('phone', '0901234567')
        ->assertJsonPath('default_address', '123 Duong Test, Da Nang');

    $token = $user->createToken('customer');

    $this->patchJson('/api/profile/password', [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertOk()
        ->assertJsonPath('reauthenticate', true);

    expect($token->accessToken->fresh())->toBeNull();
});

test('inactive products are hidden from public listing but visible to admin', function () {
    createUpgradeProduct(['name' => 'Visible product', 'is_active' => true]);
    createUpgradeProduct(['name' => 'Hidden product', 'is_active' => false]);

    $this->getJson('/api/products?per_page=20')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Visible product');

    Sanctum::actingAs(User::factory()->staff()->create());

    $this->getJson('/api/admin/products?per_page=20')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
});

test('staff can view dashboard and export order csv', function () {
    $product = createUpgradeProduct();

    $order = Order::create([
        'fullname' => 'Nguyen Van A',
        'address' => 'Da Nang City',
        'phone' => '0900000000',
        'email' => 'customer@example.test',
        'status' => Order::STATUS_ORDERED,
        'payment_method' => Order::PAYMENT_METHOD_COD,
        'payment_status' => Order::PAYMENT_STATUS_PENDING,
        'subtotal' => 90000,
        'shipping_fee' => 20000,
        'grand_total' => 110000,
    ]);
    $order->details()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 45000,
        'product_name' => $product->name,
        'line_total' => 90000,
    ]);

    Sanctum::actingAs(User::factory()->staff()->create());

    $this->getJson('/api/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('total_orders', 1)
        ->assertJsonPath('pending_orders', 1);

    $this->get('/api/admin/orders/export.csv')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

test('admin can upload multiple product images', function () {
    Storage::fake('public');
    Sanctum::actingAs(User::factory()->admin()->create());
    $product = createUpgradeProduct();

    $this->post("/api/admin/products/{$product->id}/images", [
        'images' => [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.webp'),
        ],
    ])
        ->assertCreated()
        ->assertJsonCount(2, 'images')
        ->assertJsonPath('product.images.0.is_primary', true);

    expect($product->images()->count())->toBe(2);
    expect($product->fresh()->img)->not->toBe('/assets/users/images/featured/feature-1.png');
});
