<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

test('mobile registration is stateless customer only and issues a limited token before verification', function () {
    Notification::fake();

    $response = $this->postJson('/api/mobile/register', [
        'name' => 'Mobile Customer',
        'email' => ' Mobile-Register@Example.Test ',
        'password' => 'FartaPass123',
        'password_confirmation' => 'FartaPass123',
        'device_name' => 'installation-123',
        'role' => 'admin',
    ])->assertCreated()
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email', 'role'],
            'verification_email_sent',
        ])
        ->assertJsonPath('user.email', 'mobile-register@example.test')
        ->assertJsonPath('user.role', 'customer')
        ->assertJsonPath('verification_email_sent', true)
        ->assertJsonMissingPath('user.password')
        ->assertJsonMissingPath('user.created_at');

    $user = User::query()->where('email', 'mobile-register@example.test')->firstOrFail();
    expect($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->tokens)->toHaveCount(1)
        ->and($user->tokens->first()->abilities)->toBe(['mobile:customer']);

    $this->withToken($response->json('token'))
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('id', $user->id);
    Notification::assertSentTo(
        $user,
        VerifyEmail::class,
        fn (VerifyEmail $notification): bool => str_contains(
            (string) $notification->toMail($user)->actionUrl,
            '/api/mobile/email/verify/'
        )
    );
});

test('mobile registration enforces unique email central password rule and valid device name', function () {
    User::factory()->customer()->create(['email' => 'existing@example.test']);

    $this->postJson('/api/mobile/register', [
        'name' => 'Duplicate',
        'email' => 'existing@example.test',
        'password' => 'weak',
        'password_confirmation' => 'weak',
        'device_name' => "\x00\x1F",
    ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);

    $this->postJson('/api/mobile/register', [
        'name' => 'Bad Device',
        'email' => 'new@example.test',
        'password' => 'FartaPass123',
        'password_confirmation' => 'FartaPass123',
        'device_name' => "\x00\x1F",
    ])->assertUnprocessable()->assertJsonValidationErrors(['device_name']);
});

test('mobile forgot password is stateless generic and never returns the reset token', function () {
    Notification::fake();
    $user = User::factory()->customer()->create(['email' => 'forgot-mobile@example.test']);

    $known = $this->postJson('/api/mobile/forgot-password', ['email' => ' FORGOT-Mobile@Example.Test '])
        ->assertAccepted()
        ->assertJsonMissingPath('token')
        ->assertJsonMissingPath('email');
    $unknown = $this->postJson('/api/mobile/forgot-password', ['email' => 'unknown-mobile@example.test'])
        ->assertAccepted();

    $unknown->assertExactJson($known->json());
    Notification::assertSentTo($user, ResetPassword::class);
});

test('mobile password reset is stateless single use and revokes every existing token', function () {
    $user = User::factory()->customer()->create([
        'email' => 'reset-mobile@example.test',
        'password' => 'OldPass123',
    ]);
    $user->createToken('device-a', ['mobile:customer']);
    $user->createToken('device-b', ['mobile:customer']);
    $resetToken = Password::createToken($user);
    $payload = [
        'email' => $user->email,
        'token' => $resetToken,
        'password' => 'NewPass456',
        'password_confirmation' => 'NewPass456',
    ];

    $this->postJson('/api/mobile/reset-password', $payload)
        ->assertOk()
        ->assertJsonMissingPath('token')
        ->assertJsonMissingPath('user');

    expect(Hash::check('NewPass456', $user->fresh()->password))->toBeTrue();
    $this->assertDatabaseCount('personal_access_tokens', 0);
    $this->postJson('/api/mobile/reset-password', $payload)->assertUnprocessable();
});

test('mobile verification status and resend use bearer auth without exposing email', function () {
    Notification::fake();
    $user = User::factory()->customer()->unverified()->create();
    $token = $user->createToken('device', ['mobile:customer'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/mobile/email/verification-status')
        ->assertOk()
        ->assertExactJson(['email_verified' => false]);

    $this->withToken($token)
        ->postJson('/api/mobile/email/verification-notification')
        ->assertOk()
        ->assertJsonMissingPath('email');

    Notification::assertSentTo($user, VerifyEmail::class);
});
