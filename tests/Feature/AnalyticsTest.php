<?php

use App\Models\AnalyticsPageView;
use App\Models\Order;
use App\Models\User;
use App\Support\AnalyticsIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('page view ingestion hashes identifiers strips referrer and deduplicates quickly', function () {
    $visitor = '11111111-1111-4111-8111-111111111111';
    $session = '22222222-2222-4222-8222-222222222222';
    $payload = ['visitor_id' => $visitor, 'session_id' => $session, 'path' => '/san-pham', 'referrer' => 'https://google.com/search?q=private'];
    $headers = ['User-Agent' => 'Mozilla/5.0 (iPhone) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1'];

    $this->withHeaders($headers)->postJson('/api/analytics/page-view', $payload)->assertNoContent();
    $this->withHeaders($headers)->postJson('/api/analytics/page-view', $payload)->assertNoContent();

    $this->assertDatabaseCount('analytics_page_views', 1);
    $view = AnalyticsPageView::first();
    expect($view->visitor_hash)->toBe(AnalyticsIdentifier::hash($visitor))
        ->and($view->session_hash)->toBe(AnalyticsIdentifier::hash($session))
        ->and($view->referrer_host)->toBe('google.com')
        ->and($view->device_type)->toBe('mobile')
        ->and(json_encode($view->getAttributes()))->not->toContain($visitor)->not->toContain('search?q');
});

test('analytics overview reports sessions devices and tracked conversion without raw events', function () {
    AnalyticsPageView::create([
        'visitor_hash' => str_repeat('a', 64), 'session_hash' => str_repeat('b', 64),
        'path' => '/', 'device_type' => 'desktop', 'browser' => 'Chrome', 'os' => 'macOS', 'occurred_at' => now(),
    ]);
    $order = Order::create([
        'fullname' => 'Buyer', 'address' => '123 Test Street', 'phone' => '0900000000', 'email' => 'buyer@example.test',
        'status' => Order::STATUS_PENDING, 'payment_method' => Order::PAYMENT_METHOD_COD, 'payment_status' => Order::PAYMENT_STATUS_PENDING,
    ]);
    $order->forceFill(['analytics_session_hash' => str_repeat('b', 64)])->save();
    $untrackedOrder = Order::create([
        'fullname' => 'Other buyer', 'address' => '456 Test Street', 'phone' => '0911111111', 'email' => 'other@example.test',
        'status' => Order::STATUS_PENDING, 'payment_method' => Order::PAYMENT_METHOD_COD, 'payment_status' => Order::PAYMENT_STATUS_PENDING,
    ]);
    $untrackedOrder->forceFill(['analytics_session_hash' => str_repeat('c', 64)])->save();
    Sanctum::actingAs(User::factory()->create(['role' => 'staff']));

    $this->getJson('/api/admin/analytics/overview?range=7d')->assertOk()
        ->assertJsonPath('totals.page_views', 1)
        ->assertJsonPath('totals.sessions', 1)
        ->assertJsonPath('totals.tracked_orders', 1)
        ->assertJsonPath('totals.conversion_rate', 100)
        ->assertJsonPath('devices.0.label', 'desktop')
        ->assertJsonMissingPath('data');

    foreach (['30d', '90d'] as $range) {
        $this->getJson("/api/admin/analytics/overview?range={$range}")
            ->assertOk()->assertJsonPath('range', $range);
    }
});

test('analytics rejects unsafe paths and ignores bots', function () {
    $payload = [
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'session_id' => '22222222-2222-4222-8222-222222222222',
        'path' => '/products?email=private@example.test',
    ];
    $this->postJson('/api/analytics/page-view', $payload)->assertUnprocessable();
    $payload['path'] = '/products';
    $this->withHeaders(['User-Agent' => 'Googlebot'])->postJson('/api/analytics/page-view', $payload)->assertNoContent();
    $this->assertDatabaseCount('analytics_page_views', 0);
});

test('analytics is rate limited and admin report is protected', function () {
    $payload = [
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'session_id' => '22222222-2222-4222-8222-222222222222',
    ];
    $headers = ['User-Agent' => 'Mozilla/5.0'];

    $this->getJson('/api/admin/analytics/overview')->assertUnauthorized();
    Sanctum::actingAs(User::factory()->customer()->create());
    $this->getJson('/api/admin/analytics/overview')->assertForbidden();
    auth()->forgetGuards();

    foreach (range(1, 60) as $index) {
        $this->withHeaders($headers)->postJson('/api/analytics/page-view', [
            ...$payload,
            'path' => "/rate-limit/{$index}",
        ])->assertNoContent();
    }

    $this->withHeaders($headers)->postJson('/api/analytics/page-view', [
        ...$payload,
        'path' => '/rate-limit/blocked',
    ])->assertTooManyRequests();
});

test('analytics prune removes only events beyond retention', function () {
    config(['services.analytics.retention_days' => 90]);
    $base = [
        'visitor_hash' => str_repeat('a', 64),
        'session_hash' => str_repeat('b', 64),
        'path' => '/',
        'device_type' => 'desktop',
        'browser' => 'Chrome',
        'os' => 'macOS',
    ];
    AnalyticsPageView::create([...$base, 'occurred_at' => now()->subDays(91)]);
    AnalyticsPageView::create([...$base, 'occurred_at' => now()->subDays(89)]);

    $this->artisan('analytics:prune')->assertSuccessful();

    expect(AnalyticsPageView::count())->toBe(1)
        ->and(AnalyticsPageView::first()->occurred_at->isAfter(now()->subDays(90)))->toBeTrue();
});
