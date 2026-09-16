<?php

namespace App\Services;

use App\Models\Coupon;
use InvalidArgumentException;

class CouponService
{
    public function validate(string $code, float $subtotal, int $userId, bool $lock = false): array
    {
        $query = Coupon::query()->where('code', strtoupper(trim($code)));
        $coupon = ($lock ? $query->lockForUpdate() : $query)->first();

        if (! $coupon || ! $coupon->active
            || ($coupon->starts_at && $coupon->starts_at->isFuture())
            || ($coupon->expires_at && $coupon->expires_at->isPast())) {
            throw new InvalidArgumentException('Mã giảm giá không hợp lệ hoặc đã hết hạn.');
        }

        if ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses) {
            throw new InvalidArgumentException('Mã giảm giá đã hết lượt sử dụng.');
        }

        if ($userId < 1 || $coupon->usages()->where('user_id', $userId)->count() >= ($coupon->max_uses_per_user ?? 1)) {
            throw new InvalidArgumentException('Bạn đã sử dụng mã này rồi.');
        }

        if ($subtotal < (float) $coupon->min_order_amount) {
            throw new InvalidArgumentException('Đơn hàng tối thiểu '.number_format((float) $coupon->min_order_amount, 0).'đ để dùng mã này.');
        }

        $discount = $coupon->type === Coupon::TYPE_PERCENT
            ? $subtotal * (float) $coupon->value / 100
            : (float) $coupon->value;

        if ($coupon->max_discount_amount !== null) {
            $discount = min($discount, (float) $coupon->max_discount_amount);
        }

        return ['coupon' => $coupon, 'discount_amount' => round(max(0, min($discount, $subtotal)), 2)];
    }
}
