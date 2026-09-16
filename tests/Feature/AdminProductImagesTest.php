<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    config([
        'services.cloudinary.cloud_name' => 'test-cloud',
        'services.cloudinary.api_key' => 'test-key',
        'services.cloudinary.api_secret' => 'test-secret',
    ]);
    $this->cloudinaryUploadFailureAt = null;
    $this->cloudinaryDeleteStatus = 200;
    $this->cloudinaryDeleteResult = 'ok';
    $uploadSequence = 0;
    Http::fake(function (ClientRequest $request) use (&$uploadSequence) {
        if (str_ends_with($request->url(), '/image/upload')) {
            $uploadSequence++;

            if ($uploadSequence === $this->cloudinaryUploadFailureAt) {
                return Http::response(['error' => ['message' => 'provider failure']], 503);
            }

            $publicId = 'farta/products/'.$this->product?->id.'/test-'.$uploadSequence;

            return Http::response([
                'secure_url' => 'https://res.cloudinary.com/test-cloud/image/upload/'.$publicId.'.jpg',
                'public_id' => $publicId,
            ]);
        }

        return $this->cloudinaryDeleteStatus === 200
            ? Http::response(['result' => $this->cloudinaryDeleteResult])
            : Http::response(['error' => ['message' => 'unavailable']], $this->cloudinaryDeleteStatus);
    });

    $this->product = Product::create([
        'name' => 'Image QA', 'img' => '/old.png', 'price' => 12000,
        'inventory' => 10, 'is_active' => true,
        'facebook' => '', 'twitter' => '', 'instagram' => '', 'linkedin' => '',
        'description' => 'Image QA', 'sort_description' => 'Image QA',
        'category_id' => Category::create(['name' => 'Image QA'])->id,
    ]);
});

test('gallery upload, cover replacement and deleting every image persist to the public API', function () {
    Sanctum::actingAs(User::factory()->admin()->create());
    $id = $this->product->id;
    $gallery = $this->postJson("/api/admin/products/{$id}/images", [
        'images' => [UploadedFile::fake()->image('first.png'), UploadedFile::fake()->image('second.jpg')],
    ])->assertCreated()->assertJsonCount(2, 'product.images')->json('product');
    $this->getJson("/api/products/{$id}")->assertOk()->assertJsonPath('img', $gallery['img']);
    foreach ($gallery['images'] as $image) {
        $this->assertDatabaseHas('product_images', [
            'id' => $image['id'],
            'product_id' => $id,
            'provider' => 'cloudinary',
            'path' => null,
        ]);
    }

    $replacement = $this->postJson("/api/admin/products/{$id}/image", [
        'image' => UploadedFile::fake()->image('cover.webp'),
    ])->assertOk()->assertJsonCount(3, 'product.images')->json('product');
    expect($replacement['img'])->not->toBe($gallery['img']);
    $this->getJson("/api/products/{$id}")->assertJsonPath('img', $replacement['img'])
        ->assertJsonPath('images.0.is_primary', true)->assertJsonPath('images.0.url', $replacement['img']);

    $cover = $replacement['images'][0];
    $this->deleteJson('/api/admin/product-images/'.$cover['id'])
        ->assertOk()->assertJsonPath('product.img', $gallery['img']);
    foreach ($gallery['images'] as $image) {
        $this->deleteJson('/api/admin/product-images/'.$image['id'])->assertOk();
    }
    $this->getJson("/api/products/{$id}")->assertOk()->assertJsonPath('img', '')->assertJsonCount(0, 'images');
    $this->assertDatabaseHas('products', ['id' => $id, 'img' => '']);
    $this->assertDatabaseCount('product_images', 0);
});

test('customer cannot list, upload, replace or delete admin product images', function () {
    Sanctum::actingAs(User::factory()->admin()->create());
    $id = $this->product->id;
    $image = $this->postJson("/api/admin/products/{$id}/image", [
        'image' => UploadedFile::fake()->image('private.png'),
    ])->assertOk()->json('product.images.0');
    Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
    $this->getJson('/api/admin/products')->assertForbidden();
    $this->postJson("/api/admin/products/{$id}/image", ['image' => UploadedFile::fake()->image('new.png')])->assertForbidden();
    $this->postJson("/api/admin/products/{$id}/images", ['images' => [UploadedFile::fake()->image('new.png')]])->assertForbidden();
    $this->deleteJson('/api/admin/product-images/'.$image['id'])->assertForbidden();
    $this->assertDatabaseCount('product_images', 1);
    Http::assertSentCount(1);
});

test('staff can manage images but cannot delete a product', function () {
    Sanctum::actingAs(User::factory()->create(['role' => 'staff']));
    $id = $this->product->id;
    $image = $this->postJson("/api/admin/products/{$id}/image", [
        'image' => UploadedFile::fake()->image('staff.png'),
    ])->assertOk()->json('product.images.0');
    $this->deleteJson('/api/admin/product-images/'.$image['id'])->assertOk();
    $this->deleteJson("/api/admin/products/{$id}")->assertForbidden();
});

test('a partial gallery upload is removed from Cloudinary and is not saved', function () {
    Sanctum::actingAs(User::factory()->admin()->create());
    $this->cloudinaryUploadFailureAt = 2;

    $this->postJson("/api/admin/products/{$this->product->id}/images", [
        'images' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')],
    ])->assertStatus(502)
        ->assertJsonPath('message', 'Không thể xử lý ảnh trên Cloudinary. Vui lòng thử lại.');

    $this->assertDatabaseCount('product_images', 0);
    expect($this->product->fresh()->img)->toBe('/old.png');
    Http::assertSent(fn (ClientRequest $request) => str_ends_with($request->url(), '/image/destroy'));
});

test('a Cloudinary delete failure preserves the image record and current cover', function () {
    Sanctum::actingAs(User::factory()->admin()->create());
    $image = $this->postJson("/api/admin/products/{$this->product->id}/image", [
        'image' => UploadedFile::fake()->image('cover.png'),
    ])->assertOk()->json('product.images.0');

    $this->cloudinaryDeleteStatus = 503;

    $this->deleteJson('/api/admin/product-images/'.$image['id'])->assertStatus(502);
    $this->assertDatabaseHas('product_images', ['id' => $image['id']]);
    expect($this->product->fresh()->img)->toBe($image['url']);
});

test('missing Cloudinary configuration fails without a local fallback', function () {
    Sanctum::actingAs(User::factory()->admin()->create());
    config([
        'services.cloudinary.cloud_name' => null,
        'services.cloudinary.api_key' => null,
        'services.cloudinary.api_secret' => null,
    ]);

    $this->postJson("/api/admin/products/{$this->product->id}/image", [
        'image' => UploadedFile::fake()->image('cover.png'),
    ])->assertStatus(502);

    $this->assertDatabaseCount('product_images', 0);
    expect($this->product->fresh()->img)->toBe('/old.png');
    Http::assertNothingSent();
});

test('legacy local images remain deletable after the Cloudinary migration', function () {
    Sanctum::actingAs(User::factory()->admin()->create());
    Storage::disk('public')->put('products/legacy.jpg', 'legacy');
    $image = ProductImage::create([
        'product_id' => $this->product->id,
        'provider' => 'local',
        'path' => 'products/legacy.jpg',
        'url' => '/storage/products/legacy.jpg',
        'sort_order' => 1,
        'is_primary' => true,
    ]);
    $this->product->update(['img' => $image->url]);

    $this->deleteJson('/api/admin/product-images/'.$image->id)->assertOk();

    Storage::disk('public')->assertMissing('products/legacy.jpg');
    $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
    Http::assertNothingSent();
});

test('deleting a product removes its Cloudinary images first', function () {
    Sanctum::actingAs(User::factory()->admin()->create());
    $this->postJson("/api/admin/products/{$this->product->id}/images", [
        'images' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')],
    ])->assertCreated();

    $this->deleteJson("/api/admin/products/{$this->product->id}")->assertNoContent();

    $this->assertDatabaseMissing('products', ['id' => $this->product->id]);
    $this->assertDatabaseCount('product_images', 0);
    Http::assertSentCount(4);
});

test('Cloudinary not found is an idempotent image deletion success', function () {
    Sanctum::actingAs(User::factory()->admin()->create());
    $image = $this->postJson("/api/admin/products/{$this->product->id}/image", [
        'image' => UploadedFile::fake()->image('already-removed.png'),
    ])->assertOk()->json('product.images.0');
    $this->cloudinaryDeleteResult = 'not found';

    $this->deleteJson('/api/admin/product-images/'.$image['id'])->assertOk();

    $this->assertDatabaseMissing('product_images', ['id' => $image['id']]);
    expect($this->product->fresh()->img)->toBe('');
});

test('a Cloudinary failure keeps the product and its legacy local files', function () {
    Sanctum::actingAs(User::factory()->admin()->create());
    $this->postJson("/api/admin/products/{$this->product->id}/image", [
        'image' => UploadedFile::fake()->image('cloud.png'),
    ])->assertOk();
    Storage::disk('public')->put('products/legacy.jpg', 'legacy');
    ProductImage::create([
        'product_id' => $this->product->id,
        'provider' => 'local',
        'path' => 'products/legacy.jpg',
        'url' => '/storage/products/legacy.jpg',
        'sort_order' => 2,
        'is_primary' => false,
    ]);
    $this->cloudinaryDeleteStatus = 503;

    $this->deleteJson("/api/admin/products/{$this->product->id}")->assertStatus(502);

    $this->assertDatabaseHas('products', ['id' => $this->product->id]);
    $this->assertDatabaseCount('product_images', 2);
    Storage::disk('public')->assertExists('products/legacy.jpg');
});

test('staff can choose an existing cover and reorder only the complete products gallery', function () {
    Sanctum::actingAs(User::factory()->create(['role' => 'staff']));
    $images = $this->postJson("/api/admin/products/{$this->product->id}/images", [
        'images' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')],
    ])->assertCreated()->json('product.images');

    $this->patchJson('/api/admin/product-images/'.$images[1]['id'].'/primary')
        ->assertOk()->assertJsonPath('product.img', $images[1]['url']);
    $this->patchJson("/api/admin/products/{$this->product->id}/images/order", [
        'image_ids' => [$images[1]['id'], $images[0]['id']],
    ])->assertOk()->assertJsonPath('product.images.0.id', $images[1]['id']);
    $this->patchJson("/api/admin/products/{$this->product->id}/images/order", [
        'image_ids' => [$images[0]['id']],
    ])->assertUnprocessable();
    auth()->forgetGuards();
    $this->getJson("/api/products/{$this->product->id}")
        ->assertOk()
        ->assertJsonMissingPath('images.0.public_id');
});
