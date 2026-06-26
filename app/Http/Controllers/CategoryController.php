<?php

namespace App\Http\Controllers;

use App\Models\Category as CategoryModel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = CategoryModel::withCount('products')->orderBy('name')->get();

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
        ]);

        $category = CategoryModel::create($data);

        return response()->json($category->loadCount('products'), 201);
    }

    public function show(string $id)
    {
        $category = CategoryModel::with('products')->withCount('products')->findOrFail($id);

        return response()->json($category);
    }

    public function update(Request $request, string $id)
    {
        $category = CategoryModel::findOrFail($id);
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->ignore($category->id),
            ],
        ]);

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

        $category->delete();

        return response()->json(null, 204);
    }
}
