<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

function securitySpaHeaders(): array
{
    return [
        'Origin' => 'http://127.0.0.1:5173',
        'Referer' => 'http://127.0.0.1:5173/',
    ];
}

test('api responses include baseline security headers and a correlation id', function () {
    $this->getJson('/api/products')
        ->assertOk()
        ->assertHeader('x-content-type-options', 'nosniff')
        ->assertHeader('x-frame-options', 'DENY')
        ->assertHeader('referrer-policy', 'strict-origin-when-cross-origin')
        ->assertHeader('permissions-policy', 'camera=(), geolocation=(), microphone=()')
        ->assertHeader('x-request-id');
});

test('registration is rate limited by source ip', function () {
    RateLimiter::clear('register:ip:127.0.0.1');

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->withHeaders(securitySpaHeaders())->postJson('/api/register', [
            'name' => "Rate Limited {$attempt}",
            'email' => "registration-{$attempt}@example.test",
            'password' => 'FartaPass123',
            'password_confirmation' => 'FartaPass123',
        ])->assertCreated();
    }

    $this->withHeaders(securitySpaHeaders())->postJson('/api/register', [
        'name' => 'Rate Limited Six',
        'email' => 'registration-6@example.test',
        'password' => 'FartaPass123',
        'password_confirmation' => 'FartaPass123',
    ])->assertTooManyRequests();
});

test('public chat is rate limited per ip and across the deployment', function () {
    $ip = '192.0.2.30';
    $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    RateLimiter::clear('chat:ip:'.hash('sha256', $ip));
    RateLimiter::clear('chat:global');

    for ($attempt = 0; $attempt < 20; $attempt++) {
        $this->postJson('/api/chat', ['message' => 'Alo'])->assertOk();
    }
    $this->postJson('/api/chat', ['message' => 'Alo'])->assertTooManyRequests();

    RateLimiter::clear('chat:ip:'.hash('sha256', $ip));
    RateLimiter::clear('chat:global');
    for ($attempt = 0; $attempt < 60; $attempt++) {
        RateLimiter::hit('chat:global', 60);
    }
    $this->postJson('/api/chat', ['message' => 'Alo'])->assertTooManyRequests();
});

test('production html responses enforce a restrictive content security policy', function () {
    config(['app.env' => 'production']);

    $response = $this->get('/')->assertOk()->assertHeader('content-security-policy');
    $policy = (string) $response->headers->get('Content-Security-Policy');

    expect($policy)->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'none'")
        ->not->toContain('default-src *')
        ->not->toContain('script-src *')
        ->not->toContain("'unsafe-eval'");
    expect($response->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse();
});

test('local html responses use report only csp', function () {
    config(['app.env' => 'local']);

    $this->get('/')
        ->assertOk()
        ->assertHeader('content-security-policy-report-only');
});

test('cors allows configured origins and does not authorize foreign origins', function () {
    config(['cors.allowed_origins' => ['https://shop.example.test']]);

    $this->withHeader('Origin', 'https://shop.example.test')
        ->getJson('/api/products')
        ->assertOk()
        ->assertHeader('access-control-allow-origin', 'https://shop.example.test');

    $foreign = $this->withHeader('Origin', 'https://attacker.example.test')
        ->getJson('/api/products')
        ->assertOk();
    expect($foreign->headers->get('Access-Control-Allow-Origin'))
        ->not->toBe('https://attacker.example.test');
});

test('production cors fails closed without an origin allowlist', function () {
    config([
        'app.env' => 'production',
        'cors.allowed_origins' => [],
    ]);

    $response = $this->withHeader('Origin', 'http://127.0.0.1:5173')
        ->getJson('/api/products')
        ->assertOk();

    expect($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
});

test('cors preflight only permits the configured methods and headers', function () {
    config(['cors.allowed_origins' => ['https://shop.example.test']]);

    $this->withHeaders([
        'Origin' => 'https://shop.example.test',
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'Content-Type, X-Idempotency-Key',
    ])->options('/api/order')
        ->assertNoContent()
        ->assertHeader('access-control-allow-origin', 'https://shop.example.test');

    $unknownHeader = $this->withHeaders([
        'Origin' => 'https://shop.example.test',
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'X-Unapproved-Header',
    ])->options('/api/order')->assertNoContent();

    expect(config('cors.allowed_methods'))->not->toContain('*')
        ->and(config('cors.allowed_headers'))->not->toContain('*')
        ->and(strtolower((string) $unknownHeader->headers->get('Access-Control-Allow-Headers')))
        ->not->toContain('x-unapproved-header');
});

test('session payload is encrypted when session encrypt is enabled', function () {
    config([
        'session.driver' => 'database',
        'session.encrypt' => true,
        'sanctum.stateful' => ['127.0.0.1:5173', 'localhost', '127.0.0.1'],
    ]);

    $user = \App\Models\User::factory()->customer()->create([
        'password' => 'FartaPass123',
    ]);

    $response = $this->withHeaders([
        'Origin' => 'http://127.0.0.1:5173',
        'Referer' => 'http://127.0.0.1:5173/',
    ])->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'FartaPass123',
    ])->assertOk();

    $sessionRow = \Illuminate\Support\Facades\DB::table(config('session.table'))->first();

    expect($sessionRow)->not->toBeNull();
    // Payload when encrypted should not contain plain text email address
    expect($sessionRow->payload)->not->toContain($user->email);
});

test('purging database session table invalidates authenticated requests', function () {
    config([
        'session.driver' => 'database',
        'session.encrypt' => true,
        'sanctum.stateful' => ['127.0.0.1:5173', 'localhost', '127.0.0.1'],
    ]);

    $user = \App\Models\User::factory()->customer()->create([
        'password' => 'FartaPass123',
    ]);

    $this->withHeaders([
        'Origin' => 'http://127.0.0.1:5173',
        'Referer' => 'http://127.0.0.1:5173/',
    ])->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'FartaPass123',
    ])->assertOk();

    // Verify session works before purge
    $this->withHeaders([
        'Origin' => 'http://127.0.0.1:5173',
        'Referer' => 'http://127.0.0.1:5173/',
    ])->getJson('/api/me')->assertOk()->assertJsonPath('email', $user->email);

    // Simulate session purge (Phase 3 of runbook)
    \Illuminate\Support\Facades\DB::table(config('session.table'))->delete();
    $this->flushSession();
    \Illuminate\Support\Facades\Auth::forgetGuards();
    $this->app->forgetInstance('auth');

    // Verify request with old session cookie is now 401 Unauthenticated
    $this->withHeaders([
        'Origin' => 'http://127.0.0.1:5173',
        'Referer' => 'http://127.0.0.1:5173/',
    ])->getJson('/api/me')->assertUnauthorized();
});
