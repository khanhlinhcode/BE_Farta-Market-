<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('unverified customer cannot use verified checkout features', function () {
    Sanctum::actingAs(User::factory()->customer()->unverified()->create());

    $this->postJson('/api/payment/create')->assertForbidden();
    $this->postJson('/api/coupons/validate')->assertForbidden();
});

test('valid signed verification url verifies only its intended account', function () {
    $user = User::factory()->customer()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->getJson($url)->assertOk()->assertJsonPath('email_verified', true);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    $other = User::factory()->customer()->unverified()->create();
    $wrongAccountUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $other->id,
        'hash' => sha1($user->getEmailForVerification()),
    ]);
    $this->getJson($wrongAccountUrl)->assertForbidden();
    expect($other->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('expired email verification url is rejected', function () {
    $user = User::factory()->customer()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
        'id' => $user->id,
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->getJson($url)->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verification resend is rate limited without exposing email', function () {
    Notification::fake();
    $user = User::factory()->customer()->unverified()->create();
    Sanctum::actingAs($user);

    foreach (range(1, 3) as $attempt) {
        $this->postJson('/api/email/verification-notification')
            ->assertOk()
            ->assertJsonMissingPath('email');
    }
    $this->postJson('/api/email/verification-notification')->assertTooManyRequests();
    Notification::assertSentToTimes($user, VerifyEmail::class, 3);
});

test('verification notification uses the branded template and official https api url', function () {
    config(['app.url' => 'https://api.fartamarket.company']);
    URL::forceRootUrl('https://api.fartamarket.company');
    URL::forceScheme('https');
    Notification::fake();
    $user = User::factory()->customer()->unverified()->create();

    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user): bool {
        $mail = $notification->toMail($user);
        $url = parse_url((string) $mail->actionUrl);

        return $mail->subject === 'Xác minh email Farta Market'
            && ($mail->view['html'] ?? null) === 'emails.auth-notification'
            && ($mail->view['text'] ?? null) === 'emails.auth-notification-text'
            && ($url['scheme'] ?? null) === 'https'
            && ($url['host'] ?? null) === 'api.fartamarket.company';
    });
});

test('production verification resend fails clearly with a non delivery mailer', function () {
    config(['app.env' => 'production', 'mail.default' => 'log']);
    Notification::fake();
    $user = User::factory()->customer()->unverified()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/email/verification-notification')
        ->assertStatus(503)
        ->assertJsonPath('email_verified', false)
        ->assertJsonMissingPath('email');

    Notification::assertNothingSent();
});

test('changing email requires verification again and sends a new notice', function () {
    Notification::fake();
    $user = User::factory()->customer()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/profile', [
        'name' => $user->name,
        'email' => 'changed@example.test',
    ])->assertOk()->assertJsonPath('email_verified', false);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});
