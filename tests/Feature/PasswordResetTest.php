<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

function passwordResetSpaHeaders(): array
{
    return [
        'Origin' => 'http://127.0.0.1:5173',
        'Referer' => 'http://127.0.0.1:5173/',
    ];
}

test('forgot password gives the same response without revealing account existence', function () {
    Notification::fake();
    $user = User::factory()->customer()->create(['email' => 'customer@example.test']);

    $known = $this->withHeaders(passwordResetSpaHeaders())->postJson('/api/forgot-password', [
        'email' => $user->email,
    ]);
    $unknown = $this->withHeaders(passwordResetSpaHeaders())->postJson('/api/forgot-password', [
        'email' => 'unknown@example.test',
    ]);

    $known->assertAccepted()->assertJsonMissingPath('token')->assertJsonMissingPath('email');
    $unknown->assertAccepted()->assertExactJson($known->json());
    Notification::assertSentTo($user, ResetPassword::class);
});

test('admin accounts cannot request customer password reset links', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();

    $this->withHeaders(passwordResetSpaHeaders())->postJson('/api/forgot-password', [
        'email' => $admin->email,
    ])->assertAccepted();

    Notification::assertNothingSent();
});

test('password reset notifications point to the storefront without exposing the token in the api response', function () {
    config(['services.frontend.url' => 'https://fartamarket.company']);
    Notification::fake();
    $user = User::factory()->customer()->create(['email' => 'reset-url@example.test']);

    $this->withHeaders(passwordResetSpaHeaders())->postJson('/api/forgot-password', [
        'email' => $user->email,
    ])->assertAccepted()->assertJsonMissingPath('token');

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $mail = $notification->toMail($user);
        $url = (string) $mail->actionUrl;

        return $mail->subject === 'Đặt lại mật khẩu Farta Market'
            && ($mail->view['html'] ?? null) === 'emails.auth-notification'
            && ($mail->view['text'] ?? null) === 'emails.auth-notification-text'
            && str_starts_with($url, 'https://fartamarket.company/reset-password?')
            && str_contains($url, 'token=')
            && str_contains($url, 'email=reset-url%40example.test');
    });
});

test('forgot password requires a stateful browser session and is rate limited', function () {
    Notification::fake();
    $this->postJson('/api/forgot-password', ['email' => 'customer@example.test'])
        ->assertStatus(419);

    $ip = '192.0.2.90';
    RateLimiter::clear('forgot-password:ip:'.hash('sha256', $ip));
    $this->withServerVariables(['REMOTE_ADDR' => $ip]);

    foreach (range(1, 5) as $attempt) {
        $email = "customer-{$attempt}@example.test";
        $this->withHeaders(passwordResetSpaHeaders())->postJson('/api/forgot-password', [
            'email' => $email,
        ])->assertAccepted();
    }

    $this->withHeaders(passwordResetSpaHeaders())->postJson('/api/forgot-password', [
        'email' => 'customer-6@example.test',
    ])->assertTooManyRequests();
});

test('valid token resets a customer password once and invalidates existing access', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->customer()->create([
        'email' => 'reset@example.test',
        'password' => 'OldPass123',
    ]);
    $accessTokenId = $user->createToken('old-session')->accessToken->id;
    DB::table('sessions')->insert([
        'id' => 'existing-browser-session',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => 'test',
        'last_activity' => now()->timestamp,
    ]);
    $token = Password::createToken($user);

    $payload = [
        'email' => $user->email,
        'token' => $token,
        'password' => 'NewPass456',
        'password_confirmation' => 'NewPass456',
    ];

    $this->withHeaders(passwordResetSpaHeaders())->postJson('/api/reset-password', $payload)
        ->assertOk()
        ->assertJsonMissingPath('token')
        ->assertJsonMissingPath('user');

    expect(Hash::check('NewPass456', $user->fresh()->password))->toBeTrue();
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $accessTokenId]);
    $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);

    $this->withHeaders(passwordResetSpaHeaders())->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'OldPass123',
    ])->assertUnauthorized();
    $this->withHeaders(passwordResetSpaHeaders())->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'NewPass456',
    ])->assertOk();

    $this->withHeaders(passwordResetSpaHeaders())->postJson('/api/reset-password', $payload)
        ->assertUnprocessable();
});

test('invalid expired and weak password reset attempts are rejected', function () {
    $user = User::factory()->customer()->create();

    $this->withHeaders(passwordResetSpaHeaders())->postJson('/api/reset-password', [
        'email' => $user->email,
        'token' => 'invalid-token',
        'password' => 'NewPass456',
        'password_confirmation' => 'NewPass456',
    ])->assertUnprocessable();

    $expiredToken = Password::createToken($user);
    DB::table('password_reset_tokens')->where('email', $user->email)->update([
        'created_at' => now()->subMinutes(61),
    ]);
    $this->withHeaders(passwordResetSpaHeaders())->postJson('/api/reset-password', [
        'email' => $user->email,
        'token' => $expiredToken,
        'password' => 'NewPass456',
        'password_confirmation' => 'NewPass456',
    ])->assertUnprocessable();

    $validToken = Password::createToken($user);
    $this->withHeaders(passwordResetSpaHeaders())->postJson('/api/reset-password', [
        'email' => $user->email,
        'token' => $validToken,
        'password' => 'aaaaaaaa',
        'password_confirmation' => 'aaaaaaaa',
    ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

    $this->withHeaders(passwordResetSpaHeaders())->postJson('/api/reset-password', [
        'email' => $user->email,
        'token' => $validToken,
        'password' => 'NewPass456',
        'password_confirmation' => 'Different456',
    ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
});

test('production log mailer never writes a password reset notification', function () {
    config(['app.env' => 'production', 'mail.default' => 'log']);
    Notification::fake();
    $user = User::factory()->customer()->create();

    $this->withHeaders(passwordResetSpaHeaders())->postJson('/api/forgot-password', [
        'email' => $user->email,
    ])->assertAccepted();

    Notification::assertNothingSent();
});

test('database session payload is encrypted at rest when session encryption is enabled', function () {
    config(['session.driver' => 'database', 'session.encrypt' => true]);
    $user = User::factory()->customer()->create();

    $this->withHeaders(passwordResetSpaHeaders())->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    $payload = DB::table('sessions')->where('user_id', $user->id)->value('payload');
    expect($payload)->toBeString()
        ->not->toContain('password_hash_')
        ->not->toContain('login_web_');
});
