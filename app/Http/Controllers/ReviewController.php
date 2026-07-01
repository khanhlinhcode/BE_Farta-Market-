<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Product $product)
    {
        $reviews = $product
            ->reviews()
            ->with('user:id,name')
            ->latest()
            ->paginate(5);

        return response()->json([
            'data' => $reviews->items(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
            ],
            'summary' => $this->summary($product),
        ]);
    }

    public function eligibility(Request $request, Product $product)
    {
        $user = $request->user();
        $hasPurchased = $this->hasPurchased($user->id, $product->id);
        $hasReviewed = Review::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->exists();

        return response()->json([
            'has_purchased' => $hasPurchased,
            'has_reviewed' => $hasReviewed,
            'can_review' => $hasPurchased && ! $hasReviewed,
        ]);
    }

    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $user = $request->user();

        if (! $this->hasPurchased($user->id, $product->id)) {
            return response()->json([
                'message' => 'Bạn cần mua sản phẩm trước khi đánh giá.',
            ], 403);
        }

        if (
            Review::query()
                ->where('user_id', $user->id)
                ->where('product_id', $product->id)
                ->exists()
        ) {
            return response()->json([
                'message' => 'Bạn đã đánh giá sản phẩm này.',
            ], 422);
        }

        $review = Review::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'rating' => (int) $data['rating'],
            'comment' => $data['comment'],
        ])->load('user:id,name');

        return response()->json([
            'data' => $review,
            'summary' => $this->summary($product),
        ], 201);
    }

    private function hasPurchased(int $userId, int $productId): bool
    {
        return OrderDetail::query()
            ->where('product_id', $productId)
            ->whereHas('order', function ($query) use ($userId) {
                $query
                    ->where('user_id', $userId)
                    ->where('status', '!=', Order::STATUS_CANCELLED);
            })
            ->exists();
    }

    private function summary(Product $product): array
    {
        return [
            'avg_rating' => round((float) $product->reviews()->avg('rating'), 1),
            'review_count' => $product->reviews()->count(),
        ];
    }
}
