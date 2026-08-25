<?php

namespace App\Http\Controllers;

use App\Models\Product as ProductModel;
use App\Models\ProductImage;
use App\Models\OrderDetail;
use App\Models\Order;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'in_stock' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['price_asc', 'price_desc', 'newest'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $isAdminRequest = $request->is('api/admin/*');

        $query = ProductModel::query()
            ->with(['category', 'images'])
            ->withAvg('reviews as avg_rating', 'rating')
            ->withCount('reviews');

        if (! $isAdminRequest) {
            $query->where('is_active', true);
        }

        if (! empty($filters['q'])) {
            $keyword = trim($filters['q']);
            $query->where('name', 'like', "%{$keyword}%");
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (
            isset($filters['min_price'], $filters['max_price']) &&
            (float) $filters['min_price'] > (float) $filters['max_price']
        ) {
            $query->where('id', '<', 0);
        }

        if (array_key_exists('in_stock', $filters)) {
            $inStock = filter_var($filters['in_stock'], FILTER_VALIDATE_BOOLEAN);
            $query->where('inventory', $inStock ? '>' : '<=', 0);
        }

        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderBy('price')->orderByDesc('id'),
            'price_desc' => $query->orderByDesc('price')->orderByDesc('id'),
            default => $query->latest()->latest('id'),
        };

        $perPage = min((int) ($filters['per_page'] ?? 15), 100);
        $products = $query->paginate($perPage);

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        $product = ProductModel::create(
            $this->prepareProductData($this->validatedData($request))
        );

        return response()->json($product->load(['category', 'images']), 201);
    }

    public function show(string $id)
    {
        $product = ProductModel::with(['category', 'images'])
            ->withAvg('reviews as avg_rating', 'rating')
            ->withCount('reviews')
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                    ->orWhere('slug', $id);
            })
            ->when(! request()->is('api/admin/*'), fn ($query) => $query->where('is_active', true))
            ->firstOrFail();

        return response()->json($product, 200);
    }

    public function related(string $id)
    {
        $product = $this->resolvePublicProduct($id);

        return response()->json([
            'data' => $this->relatedProductsFor($product, 8),
        ]);
    }

    public function frequentlyBoughtWith(string $id)
    {
        $product = $this->resolvePublicProduct($id);
        $orderIds = OrderDetail::query()
            ->where('product_id', $product->id)
            ->whereHas('order', fn ($query) => $this->validRecommendationOrderScope($query))
            ->pluck('order_id');

        $frequentItems = collect();

        if ($orderIds->isNotEmpty()) {
            $frequentItems = OrderDetail::query()
                ->whereIn('order_id', $orderIds)
                ->where('product_id', '!=', $product->id)
                ->whereHas('product', fn ($query) => $this->publicProductScope($query)->where('inventory', '>', 0))
                ->select('product_id', DB::raw('COUNT(*) as freq'))
                ->groupBy('product_id')
                ->orderByDesc('freq')
                ->limit(4)
                ->with(['product' => fn ($query) => $this->productCardQuery($query)])
                ->get()
                ->map(function (OrderDetail $detail) {
                    $detail->product?->setAttribute('freq', (int) $detail->freq);

                    return $detail->product;
                })
                ->filter()
                ->values();
        }

        if ($frequentItems->isEmpty()) {
            $frequentItems = $this->relatedProductsFor($product, 4);
        }

        return response()->json([
            'data' => $frequentItems,
        ]);
    }

    public function suggest(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $keyword = trim((string) ($data['q'] ?? ''));

        if (mb_strlen($keyword) < 1) {
            return response()->json(['data' => []]);
        }

        $suggestions = ProductModel::query()
            ->where('name', 'like', "%{$keyword}%")
            ->where('inventory', '>', 0)
            ->where('is_active', true)
            ->select(['id', 'name', 'price', 'img'])
            ->orderBy('name')
            ->limit(6)
            ->get()
            ->map(fn (ProductModel $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'image_url' => $product->img,
            ])
            ->values();

        return response()->json([
            'data' => $suggestions,
        ]);
    }

    public function recommended(Request $request)
    {
        $userId = $this->optionalUserId($request);

        if ($userId) {
            $wishlistProductIds = Wishlist::query()
                ->where('user_id', $userId)
                ->pluck('product_id');
            $purchasedProductIds = OrderDetail::query()
                ->whereHas('order', fn ($query) => $this
                    ->validRecommendationOrderScope($query)
                    ->where('user_id', $userId))
                ->pluck('product_id');
            $categoryIds = ProductModel::query()
                ->whereIn('id', $wishlistProductIds->merge($purchasedProductIds)->unique()->values())
                ->whereNotNull('category_id')
                ->pluck('category_id')
                ->unique()
                ->values();

            if ($categoryIds->isNotEmpty()) {
                $recommendations = ProductModel::query()
                    ->whereIn('category_id', $categoryIds)
                    ->whereNotIn('id', $wishlistProductIds->merge($purchasedProductIds)->unique()->values())
                    ->where('inventory', '>', 0)
                    ->where('is_active', true)
                    ->with(['category', 'images'])
                    ->withAvg('reviews as avg_rating', 'rating')
                    ->withCount('reviews')
                    ->orderByDesc('avg_rating')
                    ->orderByDesc('reviews_count')
                    ->latest('id')
                    ->limit(8)
                    ->get();

                if ($recommendations->isNotEmpty()) {
                    return response()->json(['data' => $recommendations]);
                }
            }
        }

        return response()->json([
            'data' => $this->topRatedProducts(8),
        ]);
    }

    public function update(Request $request, string $id)
    {
        $product = ProductModel::findOrFail($id);
        $product->update(
            $this->prepareProductData($this->validatedData($request, true), $product)
        );

        return response()->json($product->load(['category', 'images']));
    }

    public function destroy(string $id)
    {
        $product = ProductModel::findOrFail($id);

        if ($product->orderDetails()->exists()) {
            return response()->json([
                'message' => 'Không thể xoá sản phẩm đã phát sinh đơn hàng.',
            ], 422);
        }

        $product->delete();

        return response()->json(null, 204);
    }

    public function uploadImage(Request $request, ProductModel $product)
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'dimensions:max_width=4096,max_height=4096', 'max:2048'],
        ], [
            'image.uploaded' => 'Ảnh quá lớn. Vui lòng chọn ảnh nhỏ hơn 2MB',
            'image.max' => 'Ảnh quá lớn. Vui lòng chọn ảnh nhỏ hơn 2MB',
            'image.image' => 'Chỉ hỗ trợ định dạng JPG, PNG, WEBP',
            'image.mimes' => 'Chỉ hỗ trợ định dạng JPG, PNG, WEBP',
        ]);

        $path = $data['image']->store('products', 'public');
        $imageUrl = asset('storage/'.$path);

        $product->update([
            'img' => $imageUrl,
        ]);

        $this->createProductImage($product, $path, $imageUrl, true);

        return response()->json([
            'image_url' => $imageUrl,
            'product' => $product->fresh(['category', 'images']),
        ]);
    }

    public function uploadImages(Request $request, ProductModel $product)
    {
        $data = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:8'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'dimensions:max_width=4096,max_height=4096', 'max:2048'],
        ], [
            'images.*.uploaded' => 'Ảnh quá lớn. Vui lòng chọn ảnh nhỏ hơn 2MB',
            'images.*.max' => 'Ảnh quá lớn. Vui lòng chọn ảnh nhỏ hơn 2MB',
            'images.*.image' => 'Chỉ hỗ trợ định dạng JPG, PNG, WEBP',
            'images.*.mimes' => 'Chỉ hỗ trợ định dạng JPG, PNG, WEBP',
        ]);

        $createdImages = [];
        $shouldSetPrimary = ! $product->images()->exists();

        foreach ($data['images'] as $index => $image) {
            $path = $image->store('products', 'public');
            $imageUrl = asset('storage/'.$path);
            $isPrimary = $shouldSetPrimary && $index === 0;

            if ($isPrimary) {
                $product->update(['img' => $imageUrl]);
            }

            $createdImages[] = $this->createProductImage(
                $product,
                $path,
                $imageUrl,
                $isPrimary
            );
        }

        return response()->json([
            'images' => $createdImages,
            'product' => $product->fresh(['category', 'images']),
        ], 201);
    }

    public function destroyImage(ProductImage $image)
    {
        $product = $image->product;

        Storage::disk('public')->delete($image->path);
        $wasPrimary = $image->is_primary;
        $image->delete();

        if ($wasPrimary) {
            $nextPrimary = $product->images()->orderBy('sort_order')->first();

            if ($nextPrimary) {
                $product->images()->update(['is_primary' => false]);
                $nextPrimary->update(['is_primary' => true]);
                $product->update(['img' => $nextPrimary->url]);
            }
        }

        return response()->json([
            'product' => $product->fresh(['category', 'images']),
        ]);
    }

    private function validatedData(Request $request, bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'img' => [$required, 'string', 'max:255'],
            'price' => [$required, 'integer', 'min:0'],
            'inventory' => [$required, 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => [$required, 'string'],
            'sort_description' => [$required, 'string', 'max:1000'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'twitter' => ['nullable', 'string', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'category_id' => [$required, 'integer', Rule::exists('categories', 'id')],
        ]);
    }

    private function prepareProductData(array $data, ?ProductModel $product = null): array
    {
        $data = $this->normalizeSocialLinks($data, $product !== null);

        if (array_key_exists('name', $data) || array_key_exists('slug', $data)) {
            $source = ($data['slug'] ?? '') ?: ($data['name'] ?? $product?->name ?? 'product');
            $data['slug'] = $this->uniqueSlug($source, $product?->id);
        }

        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        return $data;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($value) ?: 'product';
        $slug = $baseSlug;
        $suffix = 1;

        while (
            ProductModel::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function createProductImage(
        ProductModel $product,
        string $path,
        string $imageUrl,
        bool $isPrimary
    ): ProductImage {
        if ($isPrimary) {
            $product->images()->update(['is_primary' => false]);
        }

        return $product->images()->create([
            'path' => $path,
            'url' => $imageUrl,
            'sort_order' => (int) $product->images()->max('sort_order') + 1,
            'is_primary' => $isPrimary,
        ]);
    }

    private function normalizeSocialLinks(array $data, bool $isUpdate = false): array
    {
        foreach (['facebook', 'twitter', 'instagram', 'linkedin'] as $field) {
            if ($isUpdate && ! array_key_exists($field, $data)) {
                continue;
            }

            $data[$field] = $data[$field] ?? '';
        }

        return $data;
    }

    private function resolvePublicProduct(string $id): ProductModel
    {
        return ProductModel::query()
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                    ->orWhere('slug', $id);
            })
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function relatedProductsFor(ProductModel $product, int $limit)
    {
        $related = ProductModel::query()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->where('inventory', '>', 0)
            ->where('is_active', true)
            ->with(['category', 'images'])
            ->withAvg('reviews as avg_rating', 'rating')
            ->withCount('reviews')
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        if ($related->count() >= $limit) {
            return $related->values();
        }

        $fallback = $this->topRatedProducts(
            $limit - $related->count(),
            $related->pluck('id')->push($product->id)->unique()->values()->all()
        );

        return $related->concat($fallback)->values();
    }

    private function topRatedProducts(int $limit, array $excludeIds = [])
    {
        return ProductModel::query()
            ->when($excludeIds, fn ($query) => $query->whereNotIn('id', $excludeIds))
            ->where('inventory', '>', 0)
            ->where('is_active', true)
            ->with(['category', 'images'])
            ->withAvg('reviews as avg_rating', 'rating')
            ->withCount('reviews')
            ->orderByDesc('avg_rating')
            ->orderByDesc('reviews_count')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    private function productCardQuery($query)
    {
        return $query
            ->with(['category', 'images'])
            ->withAvg('reviews as avg_rating', 'rating')
            ->withCount('reviews');
    }

    private function publicProductScope($query)
    {
        return $query->where('is_active', true);
    }

    private function validRecommendationOrderScope($query)
    {
        return $query->where(function ($orderQuery) {
            $orderQuery
                ->whereIn('status', [
                    Order::STATUS_DELIVERED,
                    Order::STATUS_SHIPPED,
                    Order::STATUS_PROCESSING,
                ])
                ->orWhere('payment_status', Order::PAYMENT_STATUS_PAID);
        });
    }

    private function optionalUserId(Request $request): ?int
    {
        if ($request->user()) {
            return (int) $request->user()->getKey();
        }

        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        $tokenable = PersonalAccessToken::findToken($token)?->tokenable;

        return $tokenable?->getKey();
    }
}
