<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function index(Product $product)
    {
        $reviews = $product
            ->reviews()
            ->where('is_visible', true)
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

        if ($user->role !== 'customer') {
            return response()->json([
                'has_purchased' => false,
                'has_reviewed' => false,
                'can_review' => false,
            ]);
        }

        $hasPurchased = $this->hasDeliveredPurchase($user->id, $product->id);
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

        if ($user->role !== 'customer') {
            return response()->json([
                'message' => 'Vui lòng đăng nhập bằng tài khoản khách hàng để đánh giá.',
            ], 403);
        }

        if (! $this->hasDeliveredPurchase($user->id, $product->id)) {
            return response()->json([
                'message' => 'Bạn chỉ có thể đánh giá sau khi đơn hàng đã giao thành công.',
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

    public function adminIndex(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'visibility' => ['nullable', Rule::in(['visible', 'hidden'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $reviews = Review::query()
            ->with(['user:id,name,email', 'product:id,name', 'moderator:id,name'])
            ->when($data['q'] ?? null, function ($query, $keyword) {
                $keyword = trim($keyword);
                $query->where(fn ($query) => $query
                    ->where('comment', 'like', "%{$keyword}%")
                    ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$keyword}%")->orWhere('email', 'like', "%{$keyword}%"))
                    ->orWhereHas('product', fn ($query) => $query->where('name', 'like', "%{$keyword}%")));
            })
            ->when($data['product_id'] ?? null, fn ($query, $id) => $query->where('product_id', $id))
            ->when($data['rating'] ?? null, fn ($query, $rating) => $query->where('rating', $rating))
            ->when($data['visibility'] ?? null, fn ($query, $visibility) => $query->where('is_visible', $visibility === 'visible'))
            ->when($data['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($data['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate((int) ($data['per_page'] ?? 20));

        return response()->json([
            'data' => $reviews->items(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    public function updateVisibility(Request $request, Review $review)
    {
        $data = $request->validate(['is_visible' => ['required', 'boolean']]);
        $review->update([
            'is_visible' => $data['is_visible'],
            'moderated_by' => $request->user()->id,
            'moderated_at' => now(),
        ]);

        return response()->json($review->fresh(['user:id,name,email', 'product:id,name', 'moderator:id,name']));
    }

    private function hasDeliveredPurchase(int $userId, int $productId): bool
    {
        return Order::query()
            ->where('user_id', $userId)
            ->where('status', Order::STATUS_DELIVERED)
            ->whereHas('details', fn ($query) => $query->where('product_id', $productId))
            ->exists();
    }

    private function summary(Product $product): array
    {
        return [
            'avg_rating' => round((float) $product->visibleReviews()->avg('rating'), 1),
            'review_count' => $product->visibleReviews()->count(),
        ];
    }
}
