<?php

namespace App\Models;

use App\Support\Vnd;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    protected $fillable = [
        'code',
        'type',
        'value',
        'value_vnd',
        'min_order_amount',
        'min_order_amount_vnd',
        'max_discount_amount',
        'max_discount_amount_vnd',
        'max_uses',
        'used_count',
        'max_uses_per_user',
        'starts_at',
        'expires_at',
        'active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'value_vnd' => 'integer',
        'min_order_amount_vnd' => 'integer',
        'max_discount_amount_vnd' => 'integer',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'max_uses_per_user' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Coupon $coupon): void {
            $rawValue = $coupon->getRawOriginal('value') ?? $coupon->getAttributes()['value'] ?? null;
            $coupon->setAttribute('value_vnd', $coupon->type === self::TYPE_FIXED && $rawValue !== null
                ? Vnd::fromDecimal($rawValue)
                : null);
        });
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => ($attributes['type'] ?? null) === self::TYPE_FIXED
                ? Vnd::canonicalDecimal($value, $attributes['value_vnd'] ?? null)
                : $value,
        );
    }

    protected function minOrderAmount(): Attribute
    {
        return $this->vndAttribute('min_order_amount', 'min_order_amount_vnd');
    }

    protected function maxDiscountAmount(): Attribute
    {
        return $this->vndAttribute('max_discount_amount', 'max_discount_amount_vnd', true);
    }

    private function vndAttribute(string $legacyColumn, string $canonicalColumn, bool $nullable = false)
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => Vnd::canonicalDecimal($value, $attributes[$canonicalColumn] ?? null),
            set: function ($value) use ($legacyColumn, $canonicalColumn, $nullable): array {
                if ($nullable && $value === null) {
                    return [$legacyColumn => null, $canonicalColumn => null];
                }

                $amount = Vnd::fromDecimal($value);

                return [$legacyColumn => Vnd::decimal($amount), $canonicalColumn => $amount];
            },
        );
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }
}
