<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

test('valid customer login returns success with sanctum bearer token', function () {
    $user = User::factory()->customer()->create([
        'email' => 'mobile-customer@example.test',
        'password' => 'FartaPass123',
    ]);

    $response = $this->postJson('/api/mobile/login', [
        'email' => 'mobile-customer@example.test',
        'password' => 'FartaPass123',
        'device_name' => 'iPhone 15 Pro',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email', 'role'],
        ])
        ->assertJsonPath('user.email', 'mobile-customer@example.test')
        ->assertJsonPath('user.role', 'customer');

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
});

test('invalid password returns generic auth failure', function () {
    User::factory()->customer()->create([
        'email' => 'wrong-pass-customer@example.test',
        'password' => 'FartaPass123',
    ]);

    $this->postJson('/api/mobile/login', [
        'email' => 'wrong-pass-customer@example.test',
        'password' => 'WrongPassword999',
        'device_name' => 'iPhone 15 Pro',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Thông tin đăng nhập không đúng.');
});

test('nonexistent email returns generic auth failure', function () {
    $this->postJson('/api/mobile/login', [
        'email' => 'nonexistent@example.test',
        'password' => 'FartaPass123',
        'device_name' => 'iPhone 15 Pro',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Thông tin đăng nhập không đúng.');
});

test('admin staff or non customer cannot obtain mobile customer token', function () {
    User::factory()->admin()->create([
        'email' => 'admin-mobile-login@example.test',
        'password' => 'FartaPass123',
    ]);

    $this->postJson('/api/mobile/login', [
        'email' => 'admin-mobile-login@example.test',
        'password' => 'FartaPass123',
        'device_name' => 'iPhone 15 Pro',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Thông tin đăng nhập không đúng.');
});

test('required validation errors on missing fields', function () {
    $this->postJson('/api/mobile/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password', 'device_name']);
});

test('device_name validation rejects blank or control characters', function () {
    $this->postJson('/api/mobile/login', [
        'email' => 'customer@example.test',
        'password' => 'FartaPass123',
        'device_name' => "\x00\x1F",
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Tên thiết bị không hợp lệ.');
});

test('user login rate limiter eventually returns http 429', function () {
    User::factory()->customer()->create([
        'email' => 'rate-limited-mobile@example.test',
        'password' => 'FartaPass123',
    ]);

    for ($attempt = 1; $attempt <= 7; $attempt++) {
        $this->postJson('/api/mobile/login', [
            'email' => 'rate-limited-mobile@example.test',
            'password' => 'wrong-pass',
            'device_name' => 'iPhone 15 Pro',
        ])->assertUnauthorized();
    }

    $this->postJson('/api/mobile/login', [
        'email' => 'rate-limited-mobile@example.test',
        'password' => 'wrong-pass',
        'device_name' => 'iPhone 15 Pro',
    ])->assertTooManyRequests();
});

test('bearer token accesses protected me endpoint', function () {
    $user = User::factory()->customer()->create([
        'email' => 'token-me@example.test',
        'password' => 'FartaPass123',
    ]);

    $loginResponse = $this->postJson('/api/mobile/login', [
        'email' => 'token-me@example.test',
        'password' => 'FartaPass123',
        'device_name' => 'iPhone 15 Pro',
    ])->assertOk();

    $token = $loginResponse->json('token');

    $this->withToken($token)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('email', 'token-me@example.test');
});

test('logout revokes current token and invalidates access', function () {
    $user = User::factory()->customer()->create([
        'email' => 'logout-mobile@example.test',
        'password' => 'FartaPass123',
    ]);

    $token = $this->postJson('/api/mobile/login', [
        'email' => 'logout-mobile@example.test',
        'password' => 'FartaPass123',
        'device_name' => 'iPhone 15 Pro',
    ])->json('token');

    $this->withToken($token)
        ->postJson('/api/mobile/logout')
        ->assertNoContent();

    $this->withToken($token)
        ->getJson('/api/me')
        ->assertUnauthorized();
});

test('token belonging to another device remains valid after single device logout', function () {
    $user = User::factory()->customer()->create([
        'email' => 'multi-device@example.test',
        'password' => 'FartaPass123',
    ]);

    $tokenA = $this->postJson('/api/mobile/login', [
        'email' => 'multi-device@example.test',
        'password' => 'FartaPass123',
        'device_name' => 'Device A',
    ])->json('token');

    $tokenB = $this->postJson('/api/mobile/login', [
        'email' => 'multi-device@example.test',
        'password' => 'FartaPass123',
        'device_name' => 'Device B',
    ])->json('token');

    $this->withToken($tokenA)
        ->postJson('/api/mobile/logout')
        ->assertNoContent();

    $this->withToken($tokenA)->getJson('/api/me')->assertUnauthorized();
    $this->withToken($tokenB)->getJson('/api/me')->assertOk()->assertJsonPath('email', 'multi-device@example.test');
});

test('same device_name token deduplication revokes previous token of same device', function () {
    $user = User::factory()->customer()->create([
        'email' => 'dedup-device@example.test',
        'password' => 'FartaPass123',
    ]);

    $token1 = $this->postJson('/api/mobile/login', [
        'email' => 'dedup-device@example.test',
        'password' => 'FartaPass123',
        'device_name' => 'iPhone 15 Pro',
    ])->json('token');

    $token2 = $this->postJson('/api/mobile/login', [
        'email' => 'dedup-device@example.test',
        'password' => 'FartaPass123',
        'device_name' => 'iPhone 15 Pro',
    ])->json('token');

    $this->withToken($token1)->getJson('/api/me')->assertUnauthorized();
    $this->withToken($token2)->getJson('/api/me')->assertOk()->assertJsonPath('email', 'dedup-device@example.test');
});

test('browser post api login still retains stateful session behavior', function () {
    $this->postJson('/api/login', [
        'email' => 'browser@example.test',
        'password' => 'FartaPass123',
    ])
        ->assertStatus(419)
        ->assertJsonPath('message', 'Yêu cầu xác thực cần session cookie hợp lệ.');
});

test('browser post api register still retains stateful session behavior', function () {
    $this->postJson('/api/register', [
        'name' => 'Browser User',
        'email' => 'browser-reg@example.test',
        'password' => 'FartaPass123',
        'password_confirmation' => 'FartaPass123',
    ])
        ->assertStatus(419)
        ->assertJsonPath('message', 'Yêu cầu xác thực cần session cookie hợp lệ.');
});

test('sensitive credentials and tokens are not intentionally logged during mobile auth', function () {
    Log::spy();

    User::factory()->customer()->create([
        'email' => 'logging-test@example.test',
        'password' => 'SuperSecret123',
    ]);

    $this->postJson('/api/mobile/login', [
        'email' => 'logging-test@example.test',
        'password' => 'SuperSecret123',
        'device_name' => 'iPhone 15 Pro',
    ])->assertOk();

    Log::shouldHaveNotReceived('info', function ($message) {
        return str_contains((string) $message, 'SuperSecret123');
    });

    Log::shouldHaveNotReceived('error', function ($message) {
        return str_contains((string) $message, 'SuperSecret123');
    });
});

test('mobile customer token cannot gain privileged admin functionality', function () {
    $user = User::factory()->customer()->create([
        'email' => 'normal-customer@example.test',
        'password' => 'FartaPass123',
    ]);

    $token = $this->postJson('/api/mobile/login', [
        'email' => 'normal-customer@example.test',
        'password' => 'FartaPass123',
        'device_name' => 'iPhone 15 Pro',
    ])->json('token');

    $this->withToken($token)
        ->getJson('/api/admin/dashboard')
        ->assertForbidden()
        ->assertJsonPath('message', 'Bạn không có quyền truy cập trang quản trị.');
});
