<?php

namespace App\Http\Controllers;

use App\Exceptions\CloudinaryException;
use App\Models\Category as CategoryModel;
use App\Services\CloudinaryImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class CategoryController extends Controller
{
    public function __construct(private readonly CloudinaryImageService $cloudinary) {}

    public function index(Request $request)
    {
        $isAdminRequest = $request->is('api/admin/*');
        $categories = CategoryModel::query()
            ->withCount($isAdminRequest
                ? 'products'
                : ['products' => fn ($query) => $query->where('is_active', true)])
            ->when(! $isAdminRequest, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $category = CategoryModel::create($data);

        return response()->json($category->loadCount('products'), 201);
    }

    public function show(Request $request, string $id)
    {
        $isAdminRequest = $request->is('api/admin/*');
        $products = fn ($query) => $isAdminRequest ? $query : $query->where('is_active', true);
        $category = CategoryModel::query()
            ->with(['products' => $products])
            ->withCount($isAdminRequest ? 'products' : ['products' => $products])
            ->when(! $isAdminRequest, fn ($query) => $query->where('is_active', true))
            ->findOrFail($id);

        return response()->json($category);
    }

    public function update(Request $request, string $id)
    {
        $category = CategoryModel::findOrFail($id);
        $data = $this->validatedData($request, $category);

        $category->update($data);

        return response()->json($category->loadCount('products'));
    }

    public function destroy(string $id)
    {
        $category = CategoryModel::withCount('products')->findOrFail($id);

        if ($category->products_count > 0) {
            return response()->json([
                'message' => 'Không thể xoá danh mục đang có sản phẩm.',
            ], 422);
        }

        $publicId = $category->image_public_id;
        $category->delete();

        if ($publicId) {
            $this->destroyCloudinaryImage($publicId);
        }

        return response()->json(null, 204);
    }

    public function uploadImage(Request $request, CategoryModel $category)
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
        ]);
        $uploaded = $this->cloudinary->uploadToFolder($data['image'], 'farta/categories/'.$category->id);
        $oldPublicId = $category->image_public_id;

        try {
            $category->update([
                'image_url' => $uploaded['url'],
                'image_public_id' => $uploaded['public_id'],
            ]);
        } catch (Throwable $exception) {
            $this->destroyCloudinaryImage($uploaded['public_id']);
            throw $exception;
        }

        if ($oldPublicId) {
            $this->destroyCloudinaryImage($oldPublicId);
        }

        return response()->json($category->fresh()->loadCount('products'));
    }

    public function destroyImage(CategoryModel $category)
    {
        $publicId = $category->image_public_id;
        $category->update(['image_url' => null, 'image_public_id' => null]);

        if ($publicId) {
            $this->destroyCloudinaryImage($publicId);
        }

        return response()->json($category->fresh()->loadCount('products'));
    }

    private function validatedData(Request $request, ?CategoryModel $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function destroyCloudinaryImage(string $publicId): void
    {
        try {
            $this->cloudinary->destroy($publicId);
        } catch (CloudinaryException $exception) {
            Log::warning('Could not clean up a category image.', [
                'public_id_hash' => hash('sha256', $publicId),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
