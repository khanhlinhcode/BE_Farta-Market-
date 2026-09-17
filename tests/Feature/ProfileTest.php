<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.cloudinary.cloud_name' => 'test-cloud',
        'services.cloudinary.api_key' => 'test-key',
        'services.cloudinary.api_secret' => 'test-secret',
    ]);
    $this->avatarUploadStatus = 200;
    $this->avatarDeleteStatus = 200;
    $this->avatarPublicId = 'farta/avatars/1/avatar-new';

    Http::fake(function (ClientRequest $request) {
        if (str_ends_with($request->url(), '/image/upload')) {
            return $this->avatarUploadStatus === 200
                ? Http::response([
                    'secure_url' => 'https://res.cloudinary.com/test-cloud/image/upload/avatar-new.webp',
                    'public_id' => $this->avatarPublicId,
                ])
                : Http::response(['error' => ['message' => 'provider failure']], $this->avatarUploadStatus);
        }

        return $this->avatarDeleteStatus === 200
            ? Http::response(['result' => 'ok'])
            : Http::response(['error' => ['message' => 'provider failure']], $this->avatarDeleteStatus);
    });
});

test('customer can update profile information and upload avatar', function () {
    $user = User::factory()->customer()->create([
        'name' => 'Old Name',
        'phone' => null,
        'avatar_url' => '/storage/avatars/legacy.webp',
        'avatar_public_id' => null,
    ]);
    $this->avatarPublicId = 'farta/avatars/'.$user->id.'/avatar-new';

    Sanctum::actingAs($user);

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonMissingPath('password')
        ->assertJsonMissingPath('remember_token')
        ->assertJsonMissingPath('avatar_public_id');

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

    expect($response->json('avatar_url'))->toStartWith('https://res.cloudinary.com/')
        ->and($response->json('data'))->not->toHaveKey('avatar_public_id')
        ->and($user->fresh()->avatar_url)->toBe($response->json('avatar_url'))
        ->and($user->fresh()->avatar_public_id)->toBe($this->avatarPublicId);
    Http::assertSentCount(1);
});

test('profile avatar validates file type and size', function () {
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

    Http::assertNothingSent();
});

test('avatar upload failure keeps the existing profile without a local fallback', function () {
    $user = User::factory()->customer()->create([
        'avatar_url' => '/storage/avatars/legacy.webp',
        'avatar_public_id' => null,
    ]);
    Sanctum::actingAs($user);
    $this->avatarUploadStatus = 503;

    $this->post('/api/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('avatar.webp'),
    ])->assertStatus(502);

    expect($user->fresh()->avatar_url)->toBe('/storage/avatars/legacy.webp')
        ->and($user->fresh()->avatar_public_id)->toBeNull();
    Http::assertSentCount(1);
});

test('a database failure removes the newly uploaded avatar', function () {
    $user = User::factory()->customer()->create([
        'avatar_url' => '/storage/avatars/legacy.webp',
        'avatar_public_id' => null,
    ]);
    $this->avatarPublicId = 'farta/avatars/'.$user->id.'/rollback';
    Sanctum::actingAs($user);
    User::updating(function (User $updating) use ($user) {
        if ($updating->is($user) && $updating->isDirty('avatar_url')) {
            throw new \RuntimeException('Simulated database failure.');
        }
    });
    $this->withoutExceptionHandling();

    expect(fn () => $this->post('/api/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('avatar.webp'),
    ]))->toThrow(\RuntimeException::class);

    expect($user->fresh()->avatar_url)->toBe('/storage/avatars/legacy.webp')
        ->and($user->fresh()->avatar_public_id)->toBeNull();
    Http::assertSentCount(2);
    Http::assertSent(fn (ClientRequest $request) => str_ends_with($request->url(), '/image/destroy'));
});

test('a failed old avatar cleanup keeps the new avatar active', function () {
    $user = User::factory()->customer()->create([
        'avatar_url' => 'https://res.cloudinary.com/test-cloud/image/upload/avatar-old.webp',
        'avatar_public_id' => 'farta/avatars/1/avatar-old',
    ]);
    $this->avatarPublicId = 'farta/avatars/'.$user->id.'/avatar-new';
    $this->avatarDeleteStatus = 503;
    Sanctum::actingAs($user);

    $this->post('/api/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('avatar.webp'),
    ])->assertOk();

    expect($user->fresh()->avatar_url)->toStartWith('https://res.cloudinary.com/')
        ->and($user->fresh()->avatar_public_id)->toBe($this->avatarPublicId);
    Http::assertSentCount(2);
});

test('changing password validates current password and revokes all tokens including current token', function () {
    $user = User::factory()->customer()->create([
        'password' => Hash::make('CurrentPass123'),
    ]);
    $oldToken = $user->createToken('old-session');
    $currentToken = $user->createToken('current-session');

    $this->withToken($currentToken->plainTextToken)
        ->postJson('/api/profile/change-password', [
            'current_password' => 'wrong-password',
            'new_password' => 'NewPassword123',
            'new_password_confirmation' => 'NewPassword123',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Mật khẩu hiện tại không đúng.');

    $this->withToken($currentToken->plainTextToken)
        ->postJson('/api/profile/change-password', [
            'current_password' => 'CurrentPass123',
            'new_password' => 'aaaaaaaa',
            'new_password_confirmation' => 'aaaaaaaa',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['new_password']);

    $this->withToken($currentToken->plainTextToken)
        ->postJson('/api/profile/change-password', [
            'current_password' => 'CurrentPass123',
            'new_password' => 'CurrentPass123',
            'new_password_confirmation' => 'CurrentPass123',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['new_password']);

    $this->withToken($currentToken->plainTextToken)
        ->postJson('/api/profile/change-password', [
            'current_password' => 'CurrentPass123',
            'new_password' => 'NewPassword123',
            'new_password_confirmation' => 'NewPassword123',
        ])
        ->assertOk();

    expect($oldToken->accessToken->fresh())->toBeNull();
    expect($currentToken->accessToken->fresh())->toBeNull();
    expect(Hash::check('NewPassword123', $user->fresh()->password))->toBeTrue();
});

test('changing password invalidates an older browser session', function () {
    $this->withCredentials();
    $user = User::factory()->customer()->create([
        'email' => 'session-revocation@example.test',
        'password' => Hash::make('CurrentPass123'),
    ]);
    $headers = [
        'Origin' => 'http://127.0.0.1:5173',
        'Referer' => 'http://127.0.0.1:5173/',
    ];

    $this->withHeaders($headers)->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'CurrentPass123',
    ])->assertOk();
    expect(session()->has('password_hash_web'))->toBeTrue();
    $oldSessionId = session()->getId();
    $oldSession = unserialize(session()->getHandler()->read($oldSessionId));
    expect($oldSession)->toHaveKey('password_hash_web');

    $currentSessionId = Str::random(40);
    $this->withCookie(config('session.cookie'), $currentSessionId)
        ->withHeaders($headers)
        ->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'CurrentPass123',
        ])->assertOk();

    $this->withCookie(config('session.cookie'), $currentSessionId)
        ->withHeaders($headers)
        ->postJson('/api/profile/change-password', [
            'current_password' => 'CurrentPass123',
            'new_password' => 'NewPassword123',
            'new_password_confirmation' => 'NewPassword123',
        ])->assertOk();

    expect($oldSession['password_hash_web'])->not->toBe($user->fresh()->getAuthPassword());

    $this->withCookie(config('session.cookie'), $oldSessionId)
        ->withHeaders($headers)
        ->getJson('/api/me')
        ->assertUnauthorized();
});
