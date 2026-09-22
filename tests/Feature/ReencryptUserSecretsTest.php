<?php

use App\Models\User;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

afterEach(function () {
    User::encryptUsing(null);
});

test('user secrets can be re-encrypted with the current application key', function () {
    $oldEncrypter = new Encrypter(random_bytes(32), 'AES-256-CBC');
    $newKey = random_bytes(32);
    $newEncrypter = new Encrypter($newKey, 'AES-256-CBC');
    $newEncrypter->previousKeys([$oldEncrypter->getKey()]);
    User::encryptUsing($newEncrypter);

    $user = User::factory()->customer()->create([
        'mfa_secret' => null,
        'mfa_recovery_codes' => null,
    ]);
    DB::table('users')->where('id', $user->id)->update([
        'mfa_secret' => $oldEncrypter->encryptString('mfa-secret'),
        'mfa_recovery_codes' => $oldEncrypter->encryptString(json_encode(['recovery-code'])),
    ]);
    $updatedAt = $user->updated_at;

    $this->artisan('security:reencrypt-user-secrets')->assertSuccessful();

    $raw = DB::table('users')->where('id', $user->id)->first();
    $currentOnly = new Encrypter($newKey, 'AES-256-CBC');
    expect($currentOnly->decryptString($raw->mfa_secret))->toBe('mfa-secret')
        ->and(json_decode($currentOnly->decryptString($raw->mfa_recovery_codes), true))->toBe(['recovery-code'])
        ->and(User::query()->findOrFail($user->id)->updated_at->equalTo($updatedAt))->toBeTrue();
});

test('dry run decrypts user secrets without changing ciphertext', function () {
    $encrypter = new Encrypter(random_bytes(32), 'AES-256-CBC');
    User::encryptUsing($encrypter);
    $user = User::factory()->customer()->create([
        'mfa_secret' => 'mfa-secret',
        'mfa_recovery_codes' => ['recovery-code'],
    ]);
    $before = DB::table('users')->where('id', $user->id)->first();

    $this->artisan('security:reencrypt-user-secrets', ['--dry-run' => true])->assertSuccessful();

    $after = DB::table('users')->where('id', $user->id)->first();
    expect($after->mfa_secret)->toBe($before->mfa_secret)
        ->and($after->mfa_recovery_codes)->toBe($before->mfa_recovery_codes);
});

test('re-encryption fails closed when a secret cannot be decrypted', function () {
    $encrypter = new Encrypter(random_bytes(32), 'AES-256-CBC');
    User::encryptUsing($encrypter);
    $good = User::factory()->customer()->create(['mfa_secret' => 'valid-secret']);
    $before = DB::table('users')->where('id', $good->id)->value('mfa_secret');
    $bad = User::factory()->customer()->create();
    DB::table('users')->where('id', $bad->id)->update(['mfa_secret' => 'invalid-ciphertext']);

    $this->artisan('security:reencrypt-user-secrets')->assertFailed();
    expect(DB::table('users')->where('id', $bad->id)->value('mfa_secret'))->toBe('invalid-ciphertext')
        ->and(DB::table('users')->where('id', $good->id)->value('mfa_secret'))->toBe($before);
});
