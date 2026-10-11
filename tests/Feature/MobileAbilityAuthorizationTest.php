<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('mobile customer ability is required across protected customer api families', function (string $path) {
    $user = User::factory()->customer()->create();
    $token = $user->createToken('wrong-ability', ['profile:read'])->plainTextToken;

    $this->withToken($token)->getJson($path)->assertForbidden();
})->with([
    '/api/user',
    '/api/mobile/email/verification-status',
    '/api/me',
    '/api/profile',
    '/api/addresses',
    '/api/my-orders',
    '/api/wishlist',
]);

test('valid mobile ability and browser customer sessions remain supported', function () {
    $user = User::factory()->customer()->create();
    $token = $user->createToken('mobile', ['mobile:customer'])->plainTextToken;

    $this->withToken($token)->getJson('/api/me')->assertOk();
    $this->actingAs($user, 'web')->getJson('/api/profile')->assertOk();
});

test('invalid and revoked bearer tokens return unauthorized without session fallback', function (string $token) {
    $sessionUser = User::factory()->customer()->create();

    $this->actingAs($sessionUser, 'web')
        ->withToken($token)
        ->getJson('/api/me')
        ->assertUnauthorized();
})->with([
    'invalid-token',
    fn () => tap(
        User::factory()->customer()->create()->createToken('revoked', ['mobile:customer']),
        fn ($created) => $created->accessToken->delete()
    )->plainTextToken,
]);

test('customer routes reject a non customer even with the mobile ability', function () {
    $staff = User::factory()->staff()->create();
    $token = $staff->createToken('invalid-role', ['mobile:customer'])->plainTextToken;

    $this->withToken($token)->getJson('/api/me')->assertForbidden();
});
