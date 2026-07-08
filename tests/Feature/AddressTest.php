<?php

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function addressPayload(array $overrides = []): array
{
    return array_merge([
        'label' => 'Nhà riêng',
        'recipient_name' => 'Linh Nguyen',
        'phone' => '0901234567',
        'address_line' => '123 Duong Test, Quan 1, TP HCM',
        'is_default' => false,
    ], $overrides);
}

test('customer can manage address book and set a default address', function () {
    $user = User::factory()->customer()->create();
    Sanctum::actingAs($user);

    $firstId = $this->postJson('/api/addresses', addressPayload([
        'label' => 'Nhà riêng',
    ]))
        ->assertCreated()
        ->assertJsonPath('data.is_default', true)
        ->json('data.id');

    $secondId = $this->postJson('/api/addresses', addressPayload([
        'label' => 'Công ty',
        'recipient_name' => 'Linh Office',
        'phone' => '0912345678',
        'address_line' => '456 Duong Cong Ty, Quan 3, TP HCM',
    ]))
        ->assertCreated()
        ->assertJsonPath('data.is_default', false)
        ->json('data.id');

    $this->patchJson("/api/addresses/{$secondId}/set-default")
        ->assertOk()
        ->assertJsonPath('data.is_default', true);

    expect(Address::find($firstId)->is_default)->toBeFalse();
    expect(Address::find($secondId)->is_default)->toBeTrue();

    $this->putJson("/api/addresses/{$secondId}", addressPayload([
        'label' => 'Văn phòng',
        'recipient_name' => 'Linh Office',
        'phone' => '0912345678',
        'address_line' => '789 Duong Moi, Quan 3, TP HCM',
        'is_default' => true,
    ]))
        ->assertOk()
        ->assertJsonPath('data.label', 'Văn phòng')
        ->assertJsonPath('data.address_line', '789 Duong Moi, Quan 3, TP HCM');

    $this->getJson('/api/addresses')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $secondId);

    $this->deleteJson("/api/addresses/{$firstId}")
        ->assertNoContent();

    expect(Address::where('user_id', $user->id)->count())->toBe(1);
});

test('customer cannot access another users address', function () {
    $owner = User::factory()->customer()->create();
    $other = User::factory()->customer()->create();
    $address = $owner->addresses()->create(addressPayload());

    Sanctum::actingAs($other);

    $this->putJson("/api/addresses/{$address->id}", addressPayload([
        'label' => 'Hack',
    ]))->assertNotFound();

    $this->deleteJson("/api/addresses/{$address->id}")
        ->assertNotFound();
});
