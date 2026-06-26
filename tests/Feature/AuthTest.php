<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('login succeeds with valid credentials', function () {
    $user = User::factory()->admin()->create([
        'email' => 'admin@example.test',
        'password' => 'secret123',
    ]);

    $this->postJson('/api/admin/login', [
        'email' => 'admin@example.test',
        'password' => 'secret123',
    ])
        ->assertOk()
        ->assertJsonStructure(['token', 'user'])
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
