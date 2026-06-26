<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('staff cannot delete a user', function () {
    $staff = User::factory()->staff()->create();
    $target = User::factory()->staff()->create();

    Sanctum::actingAs($staff);

    $this->deleteJson("/api/admin/users/{$target->id}")
        ->assertForbidden();
});

test('admin can delete a user', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->staff()->create();

    Sanctum::actingAs($admin);

    $this->deleteJson("/api/admin/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Đã xoá người dùng.');

    $this->assertDatabaseMissing('users', [
        'id' => $target->id,
    ]);
});
