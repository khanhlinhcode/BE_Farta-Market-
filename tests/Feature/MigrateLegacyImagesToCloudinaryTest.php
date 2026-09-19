<?php

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    config([
        'services.cloudinary.cloud_name' => 'test-cloud',
        'services.cloudinary.api_key' => 'test-key',
        'services.cloudinary.api_secret' => 'test-secret',
    ]);

    $this->legacySource = storage_path('framework/testing/legacy-cloudinary-'.Str::uuid().'/public');
    File::ensureDirectoryExists($this->legacySource);
    $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nGQAAAAASUVORK5CYII=');
    $this->putLegacyImage = function (string $relativePath): void {
        $path = $this->legacySource.'/'.ltrim($relativePath, '/');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $this->png);
    };

    $sequence = 0;
    Http::fake(function (ClientRequest $request) use (&$sequence) {
        if (str_ends_with($request->url(), '/image/upload')) {
            $sequence++;

            return Http::response([
                'secure_url' => "https://res.cloudinary.com/test-cloud/image/upload/migrated-{$sequence}.png",
                'public_id' => "farta/migrated-{$sequence}",
            ]);
        }

        return Http::response(['result' => 'ok']);
    });
});

afterEach(function () {
    File::deleteDirectory(dirname($this->legacySource));
});

test('legacy product cms and avatar images migrate to Cloudinary exactly once', function () {
    ($this->putLegacyImage)('assets/cover.png');
    ($this->putLegacyImage)('assets/category.png');
    ($this->putLegacyImage)('assets/banner.png');
    Storage::disk('public')->put('products/gallery.png', $this->png);
    Storage::disk('public')->put('avatars/customer.png', $this->png);

    $category = Category::create([
        'name' => 'Legacy category',
        'image_url' => '/assets/category.png',
    ]);
    $coverProduct = Product::create(productAttributes($category->id, '/assets/cover.png', 'Legacy cover'));
    $galleryProduct = Product::create(productAttributes($category->id, '/storage/products/gallery.png', 'Legacy gallery'));
    ProductImage::create([
        'product_id' => $galleryProduct->id,
        'provider' => 'local',
        'path' => 'products/gallery.png',
        'public_id' => null,
        'url' => '/storage/products/gallery.png',
        'sort_order' => 0,
        'is_primary' => true,
    ]);
    $banner = Banner::create([
        'placement' => 'hero',
        'alt_text_vi' => 'Banner',
        'alt_text_en' => 'Banner',
        'image_url' => '/assets/banner.png',
        'image_public_id' => '',
    ]);
    $user = User::factory()->create([
        'avatar_url' => '/storage/avatars/customer.png',
        'avatar_public_id' => null,
    ]);

    $this->artisan('images:migrate-to-cloudinary', ['--source' => $this->legacySource])
        ->assertSuccessful();

    $coverProduct->refresh()->load('images');
    $galleryProduct->refresh()->load('images');

    expect($coverProduct->img)->toStartWith('https://res.cloudinary.com/')
        ->and($coverProduct->images)->toHaveCount(1)
        ->and($coverProduct->images->first()->provider)->toBe('cloudinary')
        ->and($coverProduct->images->first()->path)->toBeNull()
        ->and($galleryProduct->img)->toStartWith('https://res.cloudinary.com/')
        ->and($galleryProduct->images->first()->provider)->toBe('cloudinary')
        ->and($galleryProduct->images->first()->path)->toBeNull()
        ->and($category->fresh()->image_url)->toStartWith('https://res.cloudinary.com/')
        ->and($category->fresh()->image_public_id)->not->toBeEmpty()
        ->and($banner->fresh()->image_url)->toStartWith('https://res.cloudinary.com/')
        ->and($banner->fresh()->image_public_id)->not->toBeEmpty()
        ->and($user->fresh()->avatar_url)->toStartWith('https://res.cloudinary.com/')
        ->and($user->fresh()->avatar_public_id)->not->toBeEmpty();

    Http::assertSentCount(5);

    $this->artisan('images:migrate-to-cloudinary', ['--source' => $this->legacySource])
        ->assertSuccessful();

    Http::assertSentCount(5);
    expect(File::exists($this->legacySource.'/assets/cover.png'))->toBeTrue()
        ->and(Storage::disk('public')->exists('products/gallery.png'))->toBeTrue();
});

test('dry run reports eligible images without uploading or changing the database', function () {
    ($this->putLegacyImage)('assets/cover.png');
    $category = Category::create(['name' => 'Dry run']);
    $product = Product::create(productAttributes($category->id, '/assets/cover.png', 'Dry run product'));

    $this->artisan('images:migrate-to-cloudinary', [
        '--source' => $this->legacySource,
        '--dry-run' => true,
    ])->assertSuccessful();

    expect($product->fresh()->img)->toBe('/assets/cover.png')
        ->and($product->images()->count())->toBe(0);
    Http::assertNothingSent();
});

test('migration refuses a local path outside approved image roots', function () {
    $outsidePath = dirname($this->legacySource).'/outside.png';
    File::put($outsidePath, $this->png);
    $category = Category::create(['name' => 'Unsafe path']);
    $product = Product::create(productAttributes($category->id, '/../outside.png', 'Unsafe product'));

    $this->artisan('images:migrate-to-cloudinary', ['--source' => $this->legacySource])
        ->assertFailed();

    expect($product->fresh()->img)->toBe('/../outside.png')
        ->and($product->images()->count())->toBe(0);
    Http::assertNothingSent();
});

function productAttributes(int $categoryId, string $image, string $name): array
{
    return [
        'name' => $name,
        'img' => $image,
        'price' => 1000,
        'inventory' => 5,
        'is_active' => true,
        'description' => 'Description',
        'sort_description' => 'Short description',
        'facebook' => '',
        'twitter' => '',
        'instagram' => '',
        'linkedin' => '',
        'category_id' => $categoryId,
    ];
}
