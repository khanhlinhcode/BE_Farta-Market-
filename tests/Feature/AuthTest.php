<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

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

    $this->postJson('/api/admin/login', [
        'email' => 'wrong-password@example.test',
        'password' => 'bad-password',
    ])->assertUnauthorized();
});

test('default weak admin account cannot log in', function () {
    $this->postJson('/api/admin/login', [
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
        $this->postJson('/api/admin/login', [
            'email' => 'limited@example.test',
            'password' => 'bad-password',
        ])->assertUnauthorized();
    }

    $this->postJson('/api/admin/login', [
        'email' => 'limited@example.test',
        'password' => 'bad-password',
    ])->assertTooManyRequests();
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

    $this->postJson('/api/admin/login', [
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
