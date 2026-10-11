<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('identity responses use an explicit safe user contract', function () {
    $user = User::factory()->customer()->create();
    Sanctum::actingAs($user, ['mobile:customer']);

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJson([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'default_address' => $user->default_address,
            'role' => $user->role,
        ])
        ->assertJsonMissingPath('password')
        ->assertJsonMissingPath('remember_token')
        ->assertJsonMissingPath('mfa_secret')
        ->assertJsonMissingPath('avatar_public_id');
});

test('legacy aliases remain compatible and advertise their successors', function () {
    $user = User::factory()->customer()->create();
    $this->actingAs($user, 'web');

    $this->getJson('/api/user')
        ->assertOk()
        ->assertHeader('Deprecation', 'true')
        ->assertHeader('Link', '</api/me>; rel="successor-version"')
        ->assertHeader('Sunset');

    $this->putJson('/api/profile', ['name' => 'Compatibility User'])
        ->assertOk()
        ->assertHeader('Deprecation', 'true')
        ->assertHeader('Link', '</api/profile>; rel="successor-version"')
        ->assertHeader('Sunset', 'Fri, 30 Apr 2027 00:00:00 GMT');

    $this->postJson('/api/profile/change-password', [])
        ->assertUnprocessable()
        ->assertHeader('Deprecation', 'true')
        ->assertHeader('Link', '</api/profile/password>; rel="successor-version"')
        ->assertHeader('Sunset', 'Fri, 30 Apr 2027 00:00:00 GMT');
});

test('legacy admin export remains compatible until the approved sunset', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->get('/api/admin/orders/export')
        ->assertOk()
        ->assertHeader('Deprecation', 'true')
        ->assertHeader('Link', '</api/admin/orders/export.csv>; rel="successor-version"')
        ->assertHeader('Sunset', 'Fri, 30 Apr 2027 00:00:00 GMT');
});

test('canonical profile patch does not emit deprecation headers', function () {
    $user = User::factory()->customer()->create();

    $response = $this->actingAs($user, 'web')
        ->patchJson('/api/profile', ['name' => 'Canonical User'])
        ->assertOk();

    expect($response->headers->has('Deprecation'))->toBeFalse();
});
