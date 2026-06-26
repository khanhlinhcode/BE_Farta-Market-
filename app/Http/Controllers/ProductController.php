<?php

namespace App\Http\Controllers;

use App\Models\Product as ProductModel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $query = ProductModel::query()->with('category');

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
            $this->normalizeSocialLinks($this->validatedData($request))
        );

        return response()->json($product->load('category'), 201);
    }

    public function show(string $id)
    {
        $product = ProductModel::with('category')->where('id', $id)->firstOrFail();

        return response()->json($product, 200);
    }

    public function update(Request $request, string $id)
    {
        $product = ProductModel::findOrFail($id);
        $product->update(
            $this->normalizeSocialLinks($this->validatedData($request, true), true)
        );

        return response()->json($product->load('category'));
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
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
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

        return response()->json([
            'image_url' => $imageUrl,
            'product' => $product->fresh('category'),
        ]);
    }

    private function validatedData(Request $request, bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'img' => [$required, 'string', 'max:255'],
            'price' => [$required, 'integer', 'min:0'],
            'inventory' => [$required, 'integer', 'min:0'],
            'description' => [$required, 'string'],
            'sort_description' => [$required, 'string', 'max:1000'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'twitter' => ['nullable', 'string', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'category_id' => [$required, 'integer', Rule::exists('categories', 'id')],
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
}
