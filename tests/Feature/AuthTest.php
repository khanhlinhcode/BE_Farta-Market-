<?php

use App\Models\User;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

uses(RefreshDatabase::class);

function spaHeaders(): array
{
    return [
        'Origin' => 'http://127.0.0.1:5173',
        'Referer' => 'http://127.0.0.1:5173/',
    ];
}

test('login succeeds with valid credentials', function () {
    $user = User::factory()->admin()->create([
        'email' => 'admin@example.test',
        'password' => 'secret123',
    ]);

    $this->withHeaders(spaHeaders())->postJson('/api/admin/login', [
        'email' => 'admin@example.test',
        'password' => 'secret123',
    ])
        ->assertOk()
        ->assertJsonStructure(['user'])
        ->assertJsonPath('user.id', $user->id);
});

test('login fails with wrong password', function () {
    User::factory()->admin()->create([
        'email' => 'wrong-password@example.test',
        'password' => 'secret123',
    ]);

    $this->withHeaders(spaHeaders())->postJson('/api/admin/login', [
        'email' => 'wrong-password@example.test',
        'password' => 'bad-password',
    ])->assertUnauthorized();
});

test('default weak admin account cannot log in', function () {
    $this->withHeaders(spaHeaders())->postJson('/api/admin/login', [
        'email' => 'test@example.com',
        'password' => 'password',
    ])->assertUnauthorized();
});

test('login is rate limited after five attempts', function () {
    User::factory()->admin()->create([
        'email' => 'limited@example.test',
        'password' => 'secret123',
    ]);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->withHeaders(spaHeaders())->postJson('/api/admin/login', [
            'email' => 'limited@example.test',
            'password' => 'bad-password',
        ])->assertUnauthorized();
    }

    $this->withHeaders(spaHeaders())->postJson('/api/admin/login', [
        'email' => 'limited@example.test',
        'password' => 'bad-password',
    ])->assertTooManyRequests();
});

test('register without a stateful session fails without creating a user', function () {
    $this->postJson('/api/register', [
        'name' => 'Stateless User',
        'email' => 'stateless@example.test',
        'password' => 'FartaPass123',
        'password_confirmation' => 'FartaPass123',
    ])
        ->assertStatus(419)
        ->assertJsonPath('message', 'Yêu cầu xác thực cần session cookie hợp lệ.');

    $this->assertDatabaseMissing('users', [
        'email' => 'stateless@example.test',
    ]);
});

test('uncompromised password checks use a faked http client and a short timeout in tests', function () {
    $verifier = app(UncompromisedVerifier::class);
    $timeout = new ReflectionProperty($verifier, 'timeout');
    $timeout->setAccessible(true);

    expect($timeout->getValue($verifier))->toBe(3);

    $validator = Validator::make([
        'password' => 'FartaPass123',
    ], [
        'password' => [Password::min(8)->uncompromised()],
    ]);

    expect($validator->passes())->toBeTrue();
});

test('customer can register and login through user auth only', function () {
    Http::fake([
        'https://api.pwnedpasswords.com/*' => Http::response('', 200),
    ]);

    $this->withHeaders(spaHeaders())->postJson('/api/register', [
        'name' => 'Weak Password',
        'email' => 'weak-password@example.test',
        'password' => '123456',
        'password_confirmation' => '123456',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    $this->withHeaders(spaHeaders())->postJson('/api/register', [
        'name' => 'Nguyen Van A',
        'email' => 'customer@example.test',
        'password' => 'FartaPass123',
        'password_confirmation' => 'FartaPass123',
    ])
        ->assertCreated()
        ->assertJsonStructure(['user'])
        ->assertJsonPath('user.role', 'customer');

    $this->withHeaders(spaHeaders())->postJson('/api/login', [
        'email' => 'customer@example.test',
        'password' => 'FartaPass123',
    ])
        ->assertOk()
        ->assertJsonStructure(['user'])
        ->assertJsonPath('user.role', 'customer');

    $this->withHeaders(spaHeaders())->postJson('/api/admin/login', [
        'email' => 'customer@example.test',
        'password' => 'FartaPass123',
    ])->assertUnauthorized();
});

test('admin account cannot login through user auth', function () {
    User::factory()->admin()->create([
        'email' => 'admin-user-auth@example.test',
        'password' => 'secret123',
    ]);

    $this->withHeaders(spaHeaders())->postJson('/api/login', [
        'email' => 'admin-user-auth@example.test',
        'password' => 'secret123',
    ])->assertUnauthorized();
});

test('admin logout invalidates the browser session', function () {
    User::factory()->admin()->create([
        'email' => 'logout-admin@example.test',
        'password' => 'secret123',
    ]);

    $this->withHeaders(spaHeaders())->postJson('/api/admin/login', [
        'email' => 'logout-admin@example.test',
        'password' => 'secret123',
    ])->assertOk();

    $this->withHeaders(spaHeaders())->getJson('/api/admin/me')
        ->assertOk()
        ->assertJsonPath('email', 'logout-admin@example.test');

    $this->withHeaders(spaHeaders())->postJson('/api/admin/logout')
        ->assertNoContent();

    $this->withHeaders(spaHeaders())->getJson('/api/admin/me')
        ->assertUnauthorized();

    $this->withHeaders(spaHeaders())->getJson('/api/me')
        ->assertUnauthorized();
});

test('admin logout works even if the account no longer has admin panel access', function () {
    $user = User::factory()->admin()->create([
        'email' => 'logout-role-changed@example.test',
        'password' => 'secret123',
    ]);

    $this->withHeaders(spaHeaders())->postJson('/api/admin/login', [
        'email' => 'logout-role-changed@example.test',
        'password' => 'secret123',
    ])->assertOk();

    $user->forceFill(['role' => 'customer'])->save();

    $this->withHeaders(spaHeaders())->postJson('/api/admin/logout')
        ->assertNoContent();

    $this->withHeaders(spaHeaders())->getJson('/api/me')
        ->assertUnauthorized();
});

test('api unauthenticated responses are json even without accept header', function () {
    $this->get('/api/admin/users')
        ->assertUnauthorized()
        ->assertHeader('content-type', 'application/json')
        ->assertJsonPath('message', 'Unauthenticated.');
});
