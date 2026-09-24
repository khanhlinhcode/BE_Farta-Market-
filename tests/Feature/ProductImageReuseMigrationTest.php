<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $category = Category::create(['name' => 'Migration QA']);
    $productData = [
        'img' => '',
        'price' => 10000,
        'inventory' => 5,
        'is_active' => true,
        'description' => 'Migration QA',
        'sort_description' => 'Migration QA',
        'category_id' => $category->id,
        'facebook' => '',
        'twitter' => '',
        'instagram' => '',
        'linkedin' => '',
    ];
    $this->firstProduct = Product::create([...$productData, 'name' => 'Migration product one']);
    $this->secondProduct = Product::create([...$productData, 'name' => 'Migration product two']);
    $this->migration = require database_path('migrations/2026_09_24_000001_make_product_image_public_ids_reusable.php');
});

it('restores global public id uniqueness when rollback has no reused images', function () {
    ProductImage::create([
        'product_id' => $this->firstProduct->id,
        'provider' => 'cloudinary',
        'path' => '',
        'public_id' => 'farta/migration/single-use',
        'url' => 'https://example.test/single-use.jpg',
    ]);

    $rolledBack = false;
    try {
        $this->migration->down();
        $rolledBack = true;

        expect(fn () => ProductImage::create([
            'product_id' => $this->secondProduct->id,
            'provider' => 'cloudinary',
            'path' => '',
            'public_id' => 'farta/migration/single-use',
            'url' => 'https://example.test/single-use.jpg',
        ]))->toThrow(QueryException::class);
    } finally {
        if ($rolledBack) {
            $this->migration->up();
        }
    }

    ProductImage::create([
        'product_id' => $this->secondProduct->id,
        'provider' => 'cloudinary',
        'path' => '',
        'public_id' => 'farta/migration/single-use',
        'url' => 'https://example.test/single-use.jpg',
    ]);
    expect(ProductImage::where('public_id', 'farta/migration/single-use')->count())->toBe(2);
});

it('refuses rollback before changing indexes when an image is reused', function () {
    foreach ([$this->firstProduct, $this->secondProduct] as $product) {
        ProductImage::create([
            'product_id' => $product->id,
            'provider' => 'cloudinary',
            'path' => '',
            'public_id' => 'farta/migration/shared',
            'url' => 'https://example.test/shared.jpg',
        ]);
    }

    expect(fn () => $this->migration->down())
        ->toThrow(RuntimeException::class, 'No schema changes were applied');

    expect(fn () => ProductImage::create([
        'product_id' => $this->firstProduct->id,
        'provider' => 'cloudinary',
        'path' => '',
        'public_id' => 'farta/migration/shared',
        'url' => 'https://example.test/shared.jpg',
    ]))->toThrow(QueryException::class);
    expect(ProductImage::where('public_id', 'farta/migration/shared')->count())->toBe(2);
});
