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
        ->assertJsonPath('message', 'Đã vô hiệu hóa người dùng.');

    $this->assertSoftDeleted('users', [
        'id' => $target->id,
    ]);
});

test('admin cannot change own role', function () {
    $admin = User::factory()->admin()->create();

    Sanctum::actingAs($admin);

    $this->patchJson("/api/admin/users/{$admin->id}/role", [
        'role' => 'staff',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Không thể đổi quyền tài khoản đang đăng nhập.');
});

test('admin cannot disable own account', function () {
    $admin = User::factory()->admin()->create();

    Sanctum::actingAs($admin);

    $this->deleteJson("/api/admin/users/{$admin->id}")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Không thể vô hiệu hóa tài khoản đang đăng nhập.');
});

test('cannot demote or disable last active admin', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->admin()->create();
    $actor->delete();

    Sanctum::actingAs($actor);

    $this->patchJson("/api/admin/users/{$target->id}/role", [
        'role' => 'staff',
    ])
        ->assertConflict()
        ->assertJsonPath('message', 'Không thể hạ quyền admin cuối cùng.');

    $this->deleteJson("/api/admin/users/{$target->id}")
        ->assertConflict()
        ->assertJsonPath('message', 'Không thể vô hiệu hóa admin cuối cùng.');
});

test('customer role cannot be promoted through staff role endpoint', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->customer()->create();

    Sanctum::actingAs($admin);

    $this->patchJson("/api/admin/users/{$customer->id}/role", [
        'role' => 'admin',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Chỉ được phân quyền tài khoản nhân sự.');
});

test('customer cannot access admin product management routes', function () {
    $customer = User::factory()->customer()->create();

    Sanctum::actingAs($customer);

    $this->getJson('/api/admin/orders')
        ->assertForbidden();
});
