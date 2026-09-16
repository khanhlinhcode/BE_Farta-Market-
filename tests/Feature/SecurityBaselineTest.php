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
