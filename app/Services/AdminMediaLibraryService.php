<?php

namespace App\Services;

use App\Models\Banner;
use App\Models\Category;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class AdminMediaLibraryService
{
    public const SOURCE_TYPES = ['product_image', 'category', 'banner'];

    public function paginate(?string $query, ?string $kind, int $page, int $perPage): LengthAwarePaginator
    {
        $items = $this->catalog()
            ->when($kind, fn (Collection $media) => $media->filter(
                fn (array $item) => collect($item['usages'])->contains('kind', $kind)
            ))
            ->when($query, function (Collection $media) use ($query) {
                $needle = mb_strtolower(trim($query));

                return $media->filter(function (array $item) use ($needle) {
                    return str_contains(mb_strtolower($item['label'].' '.$item['url']), $needle)
                        || collect($item['usages'])->contains(
                            fn (array $usage) => str_contains(mb_strtolower($usage['label']), $needle)
                        );
                });
            })
            ->sortByDesc('created_at')
            ->values();

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page
        );
    }

    /**
     * @return array{url: string, public_id: string}
     */
    public function resolve(string $sourceType, int $sourceId): array
    {
        $source = match ($sourceType) {
            'product_image' => $this->resolveProductImage($sourceId),
            'category' => $this->resolveCategory($sourceId),
            'banner' => $this->resolveBanner($sourceId),
            default => null,
        };

        if (! $source || $source['public_id'] === '' || $source['url'] === '') {
            throw ValidationException::withMessages([
                'media_source_id' => 'Ảnh đã chọn không còn khả dụng. Vui lòng tải lại thư viện ảnh.',
            ]);
        }

        return $source;
    }

    public function isUsed(string $publicId): bool
    {
        return ProductImage::query()->where('public_id', $publicId)->exists()
            || Category::query()->where('image_public_id', $publicId)->exists()
            || Banner::query()->where('image_public_id', $publicId)->exists()
            || User::withTrashed()->where('avatar_public_id', $publicId)->exists();
    }

    public function isUsedOutsideProductImage(string $publicId, int $imageId): bool
    {
        return ProductImage::query()->where('public_id', $publicId)->whereKeyNot($imageId)->exists()
            || Category::query()->where('image_public_id', $publicId)->exists()
            || Banner::query()->where('image_public_id', $publicId)->exists()
            || User::withTrashed()->where('avatar_public_id', $publicId)->exists();
    }

    public function isUsedOutsideProduct(string $publicId, int $productId): bool
    {
        return ProductImage::query()
            ->where('public_id', $publicId)
            ->where('product_id', '!=', $productId)
            ->exists()
            || Category::query()->where('image_public_id', $publicId)->exists()
            || Banner::query()->where('image_public_id', $publicId)->exists()
            || User::withTrashed()->where('avatar_public_id', $publicId)->exists();
    }

    private function catalog(): Collection
    {
        $items = collect();

        ProductImage::query()
            ->where('provider', 'cloudinary')
            ->whereNotNull('public_id')
            ->where('public_id', '!=', '')
            ->with('product:id,name')
            ->get()
            ->each(fn (ProductImage $image) => $this->addUsage($items, [
                'source_type' => 'product_image',
                'source_id' => $image->id,
                'kind' => 'product',
                'label' => $image->product?->name ?? 'Sản phẩm đã xoá',
                'url' => $image->url,
                'public_id' => $image->public_id,
                'created_at' => $image->created_at,
            ]));

        Category::query()
            ->whereNotNull('image_public_id')
            ->where('image_public_id', '!=', '')
            ->get()
            ->each(fn (Category $category) => $this->addUsage($items, [
                'source_type' => 'category',
                'source_id' => $category->id,
                'kind' => 'category',
                'label' => $category->name,
                'url' => $category->image_url,
                'public_id' => $category->image_public_id,
                'created_at' => $category->updated_at,
            ]));

        Banner::query()
            ->whereNotNull('image_public_id')
            ->where('image_public_id', '!=', '')
            ->get()
            ->each(fn (Banner $banner) => $this->addUsage($items, [
                'source_type' => 'banner',
                'source_id' => $banner->id,
                'kind' => 'banner',
                'label' => $banner->title_vi ?: $banner->alt_text_vi,
                'url' => $banner->image_url,
                'public_id' => $banner->image_public_id,
                'created_at' => $banner->updated_at,
            ]));

        return $items->values()->map(function (array $item) {
            unset($item['public_id']);
            $item['usage_count'] = count($item['usages']);

            return $item;
        });
    }

    private function addUsage(Collection $items, array $usage): void
    {
        $publicId = $usage['public_id'];
        $existing = $items->get($publicId);
        $public = [
            'kind' => $usage['kind'],
            'label' => $usage['label'],
            'source_type' => $usage['source_type'],
            'source_id' => $usage['source_id'],
        ];

        if ($existing) {
            $existing['usages'][] = $public;
            if ((string) $usage['created_at'] > $existing['created_at']) {
                $existing['created_at'] = (string) $usage['created_at'];
            }
            $items->put($publicId, $existing);

            return;
        }

        $items->put($publicId, [
            'key' => hash('sha256', $publicId),
            'source_type' => $usage['source_type'],
            'source_id' => $usage['source_id'],
            'url' => $usage['url'],
            'label' => $usage['label'],
            'kind' => $usage['kind'],
            'created_at' => (string) $usage['created_at'],
            'public_id' => $publicId,
            'usages' => [$public],
        ]);
    }

    private function resolveProductImage(int $id): ?array
    {
        $image = ProductImage::query()->where('provider', 'cloudinary')->find($id);

        return $image ? ['url' => $image->url, 'public_id' => (string) $image->public_id] : null;
    }

    private function resolveCategory(int $id): ?array
    {
        $category = Category::find($id);

        return $category ? ['url' => (string) $category->image_url, 'public_id' => (string) $category->image_public_id] : null;
    }

    private function resolveBanner(int $id): ?array
    {
        $banner = Banner::find($id);

        return $banner ? ['url' => (string) $banner->image_url, 'public_id' => (string) $banner->image_public_id] : null;
    }
}
