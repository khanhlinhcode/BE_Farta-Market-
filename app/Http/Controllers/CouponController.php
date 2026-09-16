<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Services\CouponService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function validateCoupon(Request $request, CouponService $couponService)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80'],
            'order_amount' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $result = $couponService->validate(
                $data['code'],
                (float) $data['order_amount'],
                (int) $request->user()->id
            );

            return response()->json([
                'valid' => true,
                'discount_amount' => $result['discount_amount'],
                'message' => 'Áp dụng mã giảm giá thành công.',
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'valid' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ]);

        $query = Coupon::query()
            ->withCount('usages')
            ->latest();

        if (! empty($filters['q'])) {
            $query->where('code', 'like', '%'.strtoupper(trim($filters['q'])).'%');
        }

        if ($request->has('active')) {
            $query->where('active', (bool) $filters['active']);
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request)
    {
        $coupon = Coupon::create($this->validatedCouponData($request));

        return response()->json($coupon->loadCount('usages'), 201);
    }

    public function show(Coupon $coupon)
    {
        return response()->json($coupon->loadCount('usages'));
    }

    public function update(Request $request, Coupon $coupon)
    {
        $coupon->update($this->validatedCouponData($request, $coupon));

        return response()->json($coupon->fresh()->loadCount('usages'));
    }

    public function destroy(Coupon $coupon)
    {
        if ($coupon->usages()->exists()) {
            $coupon->update(['active' => false]);

            return response()->json([
                'message' => 'Mã giảm giá đã có lịch sử sử dụng nên đã được tắt thay vì xóa.',
                'data' => $coupon->fresh()->loadCount('usages'),
            ]);
        }

        $coupon->delete();

        return response()->noContent();
    }

    public function usageStats(Coupon $coupon)
    {
        $usages = CouponUsage::query()
            ->where('coupon_id', $coupon->id)
            ->with(['user:id,name,email', 'order:id,grand_total,created_at'])
            ->latest('created_at')
            ->get();

        return response()->json([
            'coupon' => $coupon,
            'total_used' => $usages->count(),
            'total_discount_amount' => round((float) $usages->sum('discount_amount'), 2),
            'users' => $usages->map(fn (CouponUsage $usage) => [
                'id' => $usage->id,
                'discount_amount' => $usage->discount_amount,
                'created_at' => $usage->created_at,
                'user' => $usage->user,
                'order' => $usage->order,
            ])->values(),
        ]);
    }

    private function validatedCouponData(Request $request, ?Coupon $coupon = null): array
    {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:80',
                Rule::unique('coupons', 'code')->ignore($coupon?->id),
            ],
            'type' => ['required', Rule::in([Coupon::TYPE_PERCENT, Coupon::TYPE_FIXED])],
            'value' => ['required', 'numeric', 'min:0.01'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['code'] = strtoupper(trim($data['code']));
        $data['min_order_amount'] = $data['min_order_amount'] ?? 0;
        $data['max_uses_per_user'] = $data['max_uses_per_user'] ?? 1;
        $data['active'] = $data['active'] ?? true;

        foreach (['max_discount_amount', 'max_uses', 'starts_at', 'expires_at'] as $nullableField) {
            if (array_key_exists($nullableField, $data) && $data[$nullableField] === '') {
                $data[$nullableField] = null;
            }
        }

        return $data;
    }
}
