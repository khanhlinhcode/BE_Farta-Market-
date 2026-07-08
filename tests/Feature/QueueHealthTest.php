<?php

use App\Jobs\SendOrderConfirmationEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('admin can inspect queue health with portable queue metrics', function () {
    DB::table('jobs')->insert([
        'queue' => 'emails',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->timestamp,
        'created_at' => now()->subSeconds(360)->timestamp,
    ]);

    DB::table('failed_jobs')->insert([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'connection' => 'database',
        'queue' => 'emails',
        'payload' => '{}',
        'exception' => 'Test exception',
        'failed_at' => now(),
    ]);

    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->getJson('/api/admin/system/queue-health')
        ->assertOk()
        ->assertJsonPath('pending_jobs', 1)
        ->assertJsonPath('failed_jobs', 1);

    expect($response->json('oldest_pending_age_seconds'))->toBeGreaterThanOrEqual(300);
});

test('staff cannot inspect queue health', function () {
    Sanctum::actingAs(User::factory()->staff()->create());

    $this->getJson('/api/admin/system/queue-health')
        ->assertForbidden();
});

test('queue check command reports stuck jobs without failing the scheduler', function () {
    DB::table('jobs')->insert([
        'queue' => 'emails',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->timestamp,
        'created_at' => now()->subSeconds(360)->timestamp,
    ]);

    $this->artisan('queue:check-stuck')
        ->expectsOutputToContain('Queue health: 1 pending job(s)')
        ->assertExitCode(0);
});

test('order confirmation email job has retry timeout and backoff settings', function () {
    $job = new SendOrderConfirmationEmail(123);

    expect($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(60)
        ->and($job->backoff)->toBe([10, 30, 60]);
});
