<?php

use App\Http\Middleware\AdminCapabilityMiddleware;
use App\Http\Middleware\AdminPanelMiddleware;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('every protected admin route has exactly one known capability mapping', function () {
    $protected = collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(fn (Route $route) => str_starts_with($route->uri(), 'api/admin/')
            && in_array('admin.panel', $route->gatherMiddleware(), true));

    expect($protected)->not->toBeEmpty();
    $protected->each(function (Route $route): void {
        $capabilities = collect($route->gatherMiddleware())
            ->filter(fn (string $middleware): bool => str_starts_with($middleware, 'admin.capability:'))
            ->map(fn (string $middleware): string => substr($middleware, strlen('admin.capability:')))
            ->values();

        expect($capabilities)->toHaveCount(1)
            ->and(AdminCapabilityMiddleware::ALL)->toContain($capabilities->sole());
    });
});

test('staff allowlist matches the approved product owner matrix', function () {
    expect(AdminCapabilityMiddleware::STAFF)->toBe([
        'dashboard.read',
        'analytics.read',
        'catalog.write',
        'media.upload',
        'reviews.moderate',
        'orders.read',
    ]);
});

test('staff denied settings coupon order transition and delete mutations do not change state', function () {
    $staff = User::factory()->staff()->create();
    Sanctum::actingAs($staff);

    $settingsBefore = \App\Models\SiteSetting::current()->getAttributes();
    $this->putJson('/api/admin/site-settings', [])->assertForbidden();
    expect(\App\Models\SiteSetting::current()->getAttributes())->toBe($settingsBefore);

    $this->postJson('/api/admin/coupons', [
        'code' => 'DENIED',
        'type' => Coupon::TYPE_FIXED,
        'value' => 10000,
    ])->assertForbidden();
    expect(Coupon::count())->toBe(0);

    $order = Order::create([
        'fullname' => 'Capability Test',
        'status' => Order::STATUS_PENDING,
        'payment_method' => Order::PAYMENT_METHOD_COD,
        'payment_status' => Order::PAYMENT_STATUS_PENDING,
    ]);
    $this->patchJson("/api/admin/orders/{$order->id}/status", [
        'status' => Order::STATUS_CONFIRMED,
    ])->assertForbidden();
    expect($order->fresh()->status)->toBe(Order::STATUS_PENDING)
        ->and($order->statusHistory()->count())->toBe(0);

    $category = Category::create(['name' => 'Protected category']);
    $product = Product::create([
        'name' => 'Protected product', 'img' => '', 'price' => 10000, 'inventory' => 2,
        'is_active' => true, 'description' => 'Description', 'sort_description' => 'Summary',
        'facebook' => '', 'twitter' => '', 'instagram' => '', 'linkedin' => '',
        'category_id' => $category->id,
    ]);
    $this->deleteJson("/api/admin/products/{$product->id}")->assertForbidden();
    expect($product->fresh())->not->toBeNull();
});

test('staff denial does not reveal whether a protected resource exists', function () {
    Sanctum::actingAs(User::factory()->staff()->create());

    $existing = User::factory()->staff()->create();
    $this->deleteJson("/api/admin/users/{$existing->id}")->assertForbidden();
    $this->deleteJson('/api/admin/users/999999')->assertForbidden();
});

test('admin can pass every named capability and customer cannot enter admin routes', function () {
    $admin = User::factory()->admin()->create();
    $middleware = app(AdminCapabilityMiddleware::class);

    foreach (AdminCapabilityMiddleware::ALL as $capability) {
        $request = request()->duplicate();
        $request->setUserResolver(fn () => $admin);
        $response = $middleware->handle($request, fn () => response()->noContent(), $capability);
        expect($response->getStatusCode())->toBe(204);
    }

    Sanctum::actingAs(User::factory()->customer()->create());
    $this->getJson('/api/admin/dashboard')->assertForbidden();
});

test('admin panel denies an unmapped route by default', function () {
    $route = new Route(['GET'], '/api/admin/unmapped-test', fn () => response()->noContent());
    $route->middleware('admin.panel');
    $request = request()->duplicate();
    $request->setRouteResolver(fn () => $route);
    $request->setUserResolver(fn () => User::factory()->admin()->create());

    $response = app(AdminPanelMiddleware::class)->handle($request, fn () => response()->noContent());
    expect($response->getStatusCode())->toBe(403);
});
