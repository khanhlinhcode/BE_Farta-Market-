<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('customer can update profile information and upload avatar', function () {
    Storage::fake('public');
    $user = User::factory()->customer()->create([
        'name' => 'Old Name',
        'phone' => null,
        'avatar_url' => null,
    ]);

    Sanctum::actingAs($user);

    $this->putJson('/api/profile', [
        'name' => 'Linh Nguyen',
        'phone' => '0901234567',
    ])
        ->assertOk()
        ->assertJsonPath('name', 'Linh Nguyen')
        ->assertJsonPath('phone', '0901234567');

    expect($user->fresh()->phone)->toBe('0901234567');

    $response = $this->post('/api/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('avatar.webp'),
    ])
        ->assertOk()
        ->assertJsonStructure(['avatar_url', 'data' => ['avatar_url']]);

    expect($response->json('avatar_url'))->toContain('/storage/avatars/');
    expect($user->fresh()->avatar_url)->toBe($response->json('avatar_url'));
});

test('profile avatar validates file type and size', function () {
    Storage::fake('public');
    Sanctum::actingAs(User::factory()->customer()->create());

    $this->withHeader('Accept', 'application/json')
        ->post('/api/profile/avatar', [
        'avatar' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
    ])->assertUnprocessable();

    $this->withHeader('Accept', 'application/json')
        ->post('/api/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('avatar.jpg')->size(2200),
    ])->assertUnprocessable();

    $this->withHeader('Accept', 'application/json')
        ->post('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('oversized-dimensions.jpg', 4097, 1),
        ])->assertUnprocessable();
});

test('changing password validates current password and revokes all tokens including current token', function () {
    $user = User::factory()->customer()->create([
        'password' => Hash::make('password'),
    ]);
    $oldToken = $user->createToken('old-session');
    $currentToken = $user->createToken('current-session');

    $this->withToken($currentToken->plainTextToken)
        ->postJson('/api/profile/change-password', [
            'current_password' => 'wrong-password',
            'new_password' => 'new-password-123',
            'new_password_confirmation' => 'new-password-123',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Mật khẩu hiện tại không đúng.');

    $this->withToken($currentToken->plainTextToken)
        ->postJson('/api/profile/change-password', [
            'current_password' => 'password',
            'new_password' => 'new-password-123',
            'new_password_confirmation' => 'new-password-123',
        ])
        ->assertOk();

    expect($oldToken->accessToken->fresh())->toBeNull();
    expect($currentToken->accessToken->fresh())->toBeNull();
    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});
