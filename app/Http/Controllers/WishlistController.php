<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $items = Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'product' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with('category')
                    ->withAvg('visibleReviews as avg_rating', 'rating')
                    ->withCount('visibleReviews as reviews_count'),
            ])
            ->latest()
            ->get();

        $products = $items
            ->pluck('product')
            ->filter()
            ->values();

        return response()->json([
            'data' => $products,
            'ids' => $products->pluck('id')->map(fn ($id) => (int) $id)->values(),
        ]);
    }

    public function store(Request $request, Product $product)
    {
        abort_unless($product->is_active, 404);

        Wishlist::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        return response()->json([
            'data' => $product
                ->fresh()
                ->load('category')
                ->loadAvg('visibleReviews as avg_rating', 'rating')
                ->loadCount('visibleReviews as reviews_count'),
        ], 201);
    }

    public function destroy(Request $request, Product $product)
    {
        Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->delete();

        return response()->json(null, 204);
    }
}
