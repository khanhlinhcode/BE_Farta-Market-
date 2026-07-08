<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;
use Exception;

class CouponService
{
    /**
     * @return array{coupon: Coupon, discount_amount: float}
     *
     * @throws Exception
     */
    public function validate(string $code, float $orderAmount, int $userId, bool $lock = false): array
    {
        $query = Coupon::query()
            ->where('code', strtoupper(trim($code)))
            ->where('active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()));

        if ($lock) {
            $query->lockForUpdate();
        }

        $coupon = $query->first();

        if (! $coupon) {
            throw new Exception('Mã giảm giá không hợp lệ hoặc đã hết hạn.');
        }

        if ($orderAmount < (float) $coupon->min_order_amount) {
            throw new Exception('Đơn hàng tối thiểu '.number_format((float) $coupon->min_order_amount).'đ để dùng mã này.');
        }

        if ($coupon->max_uses && $coupon->used_count >= $coupon->max_uses) {
            throw new Exception('Mã giảm giá đã hết lượt sử dụng.');
        }

        $userUsedCount = CouponUsage::query()
            ->where('coupon_id', $coupon->id)
            ->where('user_id', $userId)
            ->count();

        if ($userUsedCount >= $coupon->max_uses_per_user) {
            throw new Exception('Bạn đã sử dụng mã này rồi.');
        }

        $discount = $coupon->type === Coupon::TYPE_PERCENT
            ? $orderAmount * ((float) $coupon->value / 100)
            : (float) $coupon->value;

        if ($coupon->max_discount_amount !== null) {
            $discount = min($discount, (float) $coupon->max_discount_amount);
        }

        $discount = min($discount, $orderAmount);

        return [
            'coupon' => $coupon,
            'discount_amount' => round($discount),
        ];
    }
}
