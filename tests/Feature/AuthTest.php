<?php

use App\Models\User;
use App\Services\AdminMfaService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

function spaHeaders(): array
{
    return [
        'Origin' => 'http://127.0.0.1:5173',
        'Referer' => 'http://127.0.0.1:5173/',
    ];
}

function mfaAdmin(array $attributes = []): array
{
    $totp = app(Google2FA::class);
    $secret = $totp->generateSecretKey();
    $user = User::factory()->admin()->create(array_merge([
        'password' => 'secret123',
        'mfa_secret' => $secret,
        'mfa_confirmed_at' => now(),
        'mfa_last_used_timestep' => null,
    ], $attributes));

    return [$user, $secret];
}

function completeAdminMfaLogin($test, User $user, string $secret): void
{
    $test->withHeaders(spaHeaders())->postJson('/api/admin/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ])->assertOk()->assertJsonPath('mfa_required', true);

    $test->withHeaders(spaHeaders())->postJson('/api/admin/mfa/challenge', [
        'code' => app(Google2FA::class)->getCurrentOtp($secret),
    ])->assertOk()->assertJsonPath('user.id', $user->id);
}

test('valid admin password requires mfa before creating an authenticated session', function () {
    [$user] = mfaAdmin([
        'email' => 'admin@example.test',
    ]);

    $this->withHeaders(spaHeaders())->postJson('/api/admin/login', [
        'email' => 'admin@example.test',
        'password' => 'secret123',
    ])
        ->assertOk()
        ->assertJsonPath('mfa_required', true)
        ->assertJsonMissingPath('user');

    $this->assertGuest('web');
    $this->withHeaders(spaHeaders())->getJson('/api/admin/me')->assertUnauthorized();
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
        'password' => 'aaaaaaaa',
        'password_confirmation' => 'aaaaaaaa',
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

test('unverified customer login sends a fresh verification email', function () {
    Notification::fake();
    $user = User::factory()->customer()->unverified()->create([
        'password' => 'FartaPass123',
    ]);

    $this->withHeaders(spaHeaders())->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'FartaPass123',
    ])
        ->assertOk()
        ->assertJsonPath('verification_email_sent', true);

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('unverified customer login succeeds when verification email delivery fails', function () {
    $user = User::factory()->customer()->unverified()->create([
        'password' => 'FartaPass123',
    ]);

    $this->mock(Dispatcher::class, function ($mock) {
        $mock->shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('Mail transport unavailable'));
    });

    $this->withHeaders(spaHeaders())->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'FartaPass123',
    ])
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('verification_email_sent', false);
});

test('registration succeeds when the verification email transport is unavailable', function () {
    $this->mock(Dispatcher::class, function ($mock) {
        $mock->shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('Mail transport unavailable'));
    });

    $this->withHeaders(spaHeaders())->postJson('/api/register', [
        'name' => 'Mail Failure Customer',
        'email' => 'mail-failure@example.test',
        'password' => 'FartaPass123',
        'password_confirmation' => 'FartaPass123',
    ])
        ->assertCreated()
        ->assertJsonPath('user.email', 'mail-failure@example.test')
        ->assertJsonPath('verification_email_sent', false);

    $this->assertDatabaseHas('users', [
        'email' => 'mail-failure@example.test',
    ]);
});

test('production registration reports that a log mailer cannot deliver verification email', function () {
    config(['app.env' => 'production', 'mail.default' => 'log']);
    Notification::fake();

    $this->withHeaders(spaHeaders())->postJson('/api/register', [
        'name' => 'Log Mailer Customer',
        'email' => 'log-mailer@example.test',
        'password' => 'FartaPass123',
        'password_confirmation' => 'FartaPass123',
    ])
        ->assertCreated()
        ->assertJsonPath('verification_email_sent', false);

    Notification::assertNothingSent();
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
    [$user, $secret] = mfaAdmin([
        'email' => 'logout-admin@example.test',
    ]);

    completeAdminMfaLogin($this, $user, $secret);

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
    [$user, $secret] = mfaAdmin([
        'email' => 'logout-role-changed@example.test',
    ]);

    completeAdminMfaLogin($this, $user, $secret);

    $user->forceFill(['role' => 'customer'])->save();

    $this->withHeaders(spaHeaders())->postJson('/api/admin/logout')
        ->assertNoContent();

    $this->withHeaders(spaHeaders())->getJson('/api/me')
        ->assertUnauthorized();
});

test('legacy admin must enroll mfa and receives recovery codes only once', function () {
    $user = User::factory()->admin()->create([
        'email' => 'enroll@example.test',
        'password' => 'secret123',
        'mfa_secret' => null,
        'mfa_confirmed_at' => null,
    ]);

    $this->withHeaders(spaHeaders())->postJson('/api/admin/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ])->assertOk()->assertJsonPath('mfa_enrollment_required', true);
    $this->withHeaders(spaHeaders())->getJson('/api/admin/dashboard')->assertUnauthorized();

    $setup = $this->withHeaders(spaHeaders())->postJson('/api/admin/mfa/setup')
        ->assertOk()
        ->assertJsonStructure(['secret', 'provisioning_uri']);
    $secret = $setup->json('secret');

    $confirmation = $this->withHeaders(spaHeaders())->postJson('/api/admin/mfa/confirm', [
        'code' => app(Google2FA::class)->getCurrentOtp($secret),
    ])->assertOk()->assertJsonCount(8, 'recovery_codes');

    $confirmation->assertJsonMissingPath('user.mfa_secret')
        ->assertJsonMissingPath('user.mfa_recovery_codes');
    $this->withHeaders(spaHeaders())->getJson('/api/admin/dashboard')->assertOk();
});

test('mfa rejects wrong and replayed recovery codes', function () {
    [$user, $secret] = mfaAdmin(['email' => 'recovery@example.test']);
    $recoveryCode = 'ABCDE-12345';
    $user->forceFill([
        'mfa_recovery_codes' => [hash_hmac('sha256', $recoveryCode, (string) config('app.key'))],
    ])->save();

    $this->withHeaders(spaHeaders())->postJson('/api/admin/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ])->assertOk();
    $this->withHeaders(spaHeaders())->postJson('/api/admin/mfa/challenge', ['code' => '000000'])
        ->assertUnprocessable();
    $this->withHeaders(spaHeaders())->postJson('/api/admin/mfa/challenge', ['recovery_code' => $recoveryCode])
        ->assertOk();
    $this->withHeaders(spaHeaders())->postJson('/api/admin/logout')->assertNoContent();

    $this->withHeaders(spaHeaders())->postJson('/api/admin/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ])->assertOk();
    $this->withHeaders(spaHeaders())->postJson('/api/admin/mfa/challenge', ['recovery_code' => $recoveryCode])
        ->assertUnprocessable();
});

test('totp code cannot be replayed in the same time window', function () {
    [$user, $secret] = mfaAdmin(['email' => 'totp-replay@example.test']);
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    $this->withHeaders(spaHeaders())->postJson('/api/admin/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ])->assertOk();
    $this->withHeaders(spaHeaders())->postJson('/api/admin/mfa/challenge', ['code' => $code])->assertOk();
    $this->withHeaders(spaHeaders())->postJson('/api/admin/logout')->assertNoContent();

    $this->withHeaders(spaHeaders())->postJson('/api/admin/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ])->assertOk();
    $this->withHeaders(spaHeaders())->postJson('/api/admin/mfa/challenge', ['code' => $code])
        ->assertUnprocessable();
});

test('stale mfa models cannot claim the same totp or recovery code twice', function () {
    [$user, $secret] = mfaAdmin(['email' => 'atomic-mfa@example.test']);
    $mfa = app(AdminMfaService::class);
    $staleTotpUser = User::query()->findOrFail($user->id);
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    expect($mfa->consumeTotp($user, $code))->toBeInt()
        ->and($mfa->consumeTotp($staleTotpUser, $code))->toBeFalse();

    $recoveryCode = 'ATOMIC-RECOVERY';
    $user->forceFill([
        'mfa_recovery_codes' => $mfa->recoveryCodeHashes([$recoveryCode]),
    ])->save();
    $firstRecoveryUser = User::query()->findOrFail($user->id);
    $staleRecoveryUser = User::query()->findOrFail($user->id);

    expect($mfa->consumeRecoveryCode($firstRecoveryUser, $recoveryCode))->toBeTrue()
        ->and($mfa->consumeRecoveryCode($staleRecoveryUser, $recoveryCode))->toBeFalse();
});

test('mfa challenge is rate limited', function () {
    [$user] = mfaAdmin(['email' => 'mfa-rate@example.test']);
    $this->withHeaders(spaHeaders())->postJson('/api/admin/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ])->assertOk();

    foreach (range(1, 5) as $attempt) {
        $this->withHeaders(spaHeaders())->postJson('/api/admin/mfa/challenge', ['code' => '000000'])
            ->assertUnprocessable();
    }
    $this->withHeaders(spaHeaders())->postJson('/api/admin/mfa/challenge', ['code' => '000000'])
        ->assertTooManyRequests();
});

test('admin login account limiter still applies when attacker changes ip', function () {
    User::factory()->admin()->create([
        'email' => 'distributed@example.test',
        'password' => 'secret123',
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => "192.0.2.{$attempt}"])
            ->withHeaders(spaHeaders())
            ->postJson('/api/admin/login', [
                'email' => 'distributed@example.test',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.99'])
        ->withHeaders(spaHeaders())
        ->postJson('/api/admin/login', [
            'email' => 'distributed@example.test',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
});

test('admin login ip limiter applies when attacker changes email', function () {
    foreach (range(1, 5) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->withHeaders(spaHeaders())
            ->postJson('/api/admin/login', [
                'email' => "unknown{$attempt}@example.test",
                'password' => 'wrong-password',
            ])->assertUnauthorized();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
        ->withHeaders(spaHeaders())
        ->postJson('/api/admin/login', [
            'email' => 'another@example.test',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
});

test('customer cannot use an admin mfa endpoint', function () {
    $this->actingAs(User::factory()->customer()->create(), 'web')
        ->withHeaders(spaHeaders())
        ->postJson('/api/admin/mfa/setup')
        ->assertUnauthorized();
});

test('api unauthenticated responses are json even without accept header', function () {
    $this->get('/api/admin/users')
        ->assertUnauthorized()
        ->assertHeader('content-type', 'application/json')
        ->assertJsonPath('message', 'Unauthenticated.');
});

test('cookie authenticated admins can log out without deleting a transient Sanctum token', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user, 'web')
        ->withHeaders(['Origin' => 'http://localhost:5174'])
        ->postJson('/api/admin/logout')
        ->assertNoContent();

    $this->assertGuest('web');
});
