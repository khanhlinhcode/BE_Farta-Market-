<?php

namespace App\Models;

use App\Support\Vnd;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponUsage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'coupon_id',
        'user_id',
        'order_id',
        'discount_amount',
        'discount_amount_vnd',
        'created_at',
    ];

    protected $casts = [
        'coupon_id' => 'integer',
        'user_id' => 'integer',
        'order_id' => 'integer',
        'discount_amount' => 'decimal:2',
        'discount_amount_vnd' => 'integer',
        'created_at' => 'datetime',
    ];

    protected function discountAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => Vnd::canonicalDecimal($value, $attributes['discount_amount_vnd'] ?? null),
            set: function ($value): array {
                $amount = Vnd::fromDecimal($value);

                return ['discount_amount' => Vnd::decimal($amount), 'discount_amount_vnd' => $amount];
            },
        );
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
