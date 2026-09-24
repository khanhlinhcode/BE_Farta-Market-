<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.cloudinary.cloud_name' => 'test-cloud',
        'services.cloudinary.api_key' => 'test-key',
        'services.cloudinary.api_secret' => 'test-secret',
    ]);
    Http::fake(function (ClientRequest $request) {
        if (str_ends_with($request->url(), '/image/upload')) {
            static $uploadSequence = 0;
            $uploadSequence++;

            return Http::response([
                'secure_url' => "https://res.cloudinary.com/test/image/upload/farta/products/2/uploaded-{$uploadSequence}.avif",
                'public_id' => "farta/products/2/uploaded-{$uploadSequence}",
            ]);
        }

        return Http::response(['result' => 'ok']);
    });

    $this->category = Category::create(['name' => 'Media QA']);
    $this->sourceProduct = Product::create([
        'name' => 'Source product', 'img' => '', 'price' => 10000, 'inventory' => 5,
        'is_active' => true, 'description' => 'Source', 'sort_description' => 'Source',
        'category_id' => $this->category->id,
        'facebook' => '', 'twitter' => '', 'instagram' => '', 'linkedin' => '',
    ]);
    $this->targetProduct = Product::create([
        'name' => 'Target product', 'img' => '', 'price' => 12000, 'inventory' => 5,
        'is_active' => true, 'description' => 'Target', 'sort_description' => 'Target',
        'category_id' => $this->category->id,
        'facebook' => '', 'twitter' => '', 'instagram' => '', 'linkedin' => '',
    ]);
    $this->sourceImage = ProductImage::create([
        'product_id' => $this->sourceProduct->id,
        'provider' => 'cloudinary',
        'public_id' => 'farta/products/1/shared-secret',
        'url' => 'https://res.cloudinary.com/test/image/upload/shared.jpg',
        'sort_order' => 1,
        'is_primary' => true,
    ]);
    $this->sourceProduct->update(['img' => $this->sourceImage->url]);
});

test('staff can browse admin managed media without provider identifiers or customer avatars', function () {
    User::factory()->create([
        'avatar_url' => 'https://res.cloudinary.com/test/image/upload/private-avatar.jpg',
        'avatar_public_id' => 'farta/avatars/private',
    ]);
    Sanctum::actingAs(User::factory()->create(['role' => 'staff']));

    $response = $this->getJson('/api/admin/media')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.label', 'Source product')
        ->assertJsonPath('data.0.usage_count', 1)
        ->assertJsonMissingPath('data.0.public_id');

    expect($response->getContent())
        ->not->toContain('shared-secret')
        ->not->toContain('private-avatar');

    Sanctum::actingAs(User::factory()->customer()->create());
    $this->getJson('/api/admin/media')->assertForbidden();
});

test('an existing image can be reused across products categories and banners without premature deletion', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $reused = $this->postJson("/api/admin/products/{$this->targetProduct->id}/images/reuse", [
        'media_source_type' => 'product_image',
        'media_source_id' => $this->sourceImage->id,
        'as_primary' => true,
    ])->assertCreated()
        ->assertJsonPath('product.img', $this->sourceImage->url)
        ->json('image');

    $this->postJson("/api/admin/categories/{$this->category->id}/image/reuse", [
        'media_source_type' => 'product_image',
        'media_source_id' => $this->sourceImage->id,
    ])->assertOk()->assertJsonPath('image_url', $this->sourceImage->url);

    $banner = $this->postJson('/api/admin/banners', [
        'placement' => 'hero',
        'alt_text_vi' => 'Ảnh dùng lại',
        'alt_text_en' => 'Reused image',
        'media_source_type' => 'product_image',
        'media_source_id' => $this->sourceImage->id,
    ])->assertCreated()->assertJsonPath('image_url', $this->sourceImage->url)->json();

    $this->deleteJson('/api/admin/product-images/'.$this->sourceImage->id)->assertOk();
    $this->assertDatabaseHas('product_images', ['id' => $reused['id']]);
    Http::assertNothingSent();

    $this->deleteJson("/api/admin/categories/{$this->category->id}/image")->assertOk();
    Http::assertNothingSent();

    $this->deleteJson('/api/admin/banners/'.$banner['id'])->assertNoContent();
    Http::assertNothingSent();

    $this->deleteJson('/api/admin/product-images/'.$reused['id'])->assertOk();
    Http::assertSentCount(1);
});

test('admin image uploads accept safe web formats and reject svg content', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/admin/products/{$this->targetProduct->id}/images", [
        'images' => [UploadedFile::fake()->image('catalog.gif')],
    ])->assertCreated();

    $temporaryAvif = tempnam(sys_get_temp_dir(), 'farta-avif-');
    $image = imagecreatetruecolor(4, 4);
    imageavif($image, $temporaryAvif);
    imagedestroy($image);

    try {
        $this->postJson("/api/admin/products/{$this->targetProduct->id}/images", [
            'images' => [new UploadedFile($temporaryAvif, 'catalog.avif', 'image/avif', null, true)],
        ])->assertCreated();
    } finally {
        @unlink($temporaryAvif);
    }

    $this->postJson("/api/admin/products/{$this->targetProduct->id}/images", [
        'images' => [UploadedFile::fake()->createWithContent('unsafe.svg', '<svg onload="alert(1)"/>')],
    ])->assertUnprocessable()->assertJsonValidationErrors('images.0');
});

test('media reuse rejects stale sources and duplicate product attachments', function () {
    Sanctum::actingAs(User::factory()->create(['role' => 'staff']));

    $payload = [
        'media_source_type' => 'product_image',
        'media_source_id' => $this->sourceImage->id,
    ];
    $this->postJson("/api/admin/products/{$this->targetProduct->id}/images/reuse", $payload)->assertCreated();
    $this->postJson("/api/admin/products/{$this->targetProduct->id}/images/reuse", $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('media_source_id');

    $this->postJson("/api/admin/categories/{$this->category->id}/image/reuse", [
        'media_source_type' => 'banner',
        'media_source_id' => 999999,
    ])->assertUnprocessable()->assertJsonValidationErrors('media_source_id');
});
