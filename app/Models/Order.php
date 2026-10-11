<?php

namespace App\Models;

use App\Support\Vnd;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_ORDERED = self::STATUS_PENDING;

    public const STATUS_PREPARING = self::STATUS_CONFIRMED;

    public const STATUS_DELIVERING = self::STATUS_SHIPPED;

    public const STATUS_PENDING_PAYMENT = self::STATUS_PENDING;

    public const STATUS_PAYMENT_FAILED = self::STATUS_CANCELLED;

    public const PAYMENT_METHOD_COD = 'cod';

    public const PAYMENT_METHOD_VNPAY = 'vnpay';

    public const PAYMENT_METHOD_SEPAY = 'sepay';

    public const PAYMENT_STATUS_PENDING = 'pending';

    public const PAYMENT_STATUS_PAID = 'paid';

    public const PAYMENT_STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_PROCESSING,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'user_id',
        'fullname',
        'address',
        'phone',
        'email',
        'note',
        'status',
        'payment_method',
        'payment_status',
        'payment_reference',
        'payment_expires_at',
        'payment_transaction_id',
        'coupon_id',
        'discount_amount',
        'discount_amount_vnd',
        'subtotal',
        'subtotal_vnd',
        'shipping_fee',
        'shipping_fee_vnd',
        'grand_total',
        'grand_total_vnd',
        'idempotency_key',
        'checkout_ip_hash',
        'guest_expires_at',
        'reservation_expires_at',
    ];

    protected $table = 'orders';

    protected $hidden = [
        'idempotency_key',
        'analytics_session_hash',
        'checkout_ip_hash',
        'payment_transaction_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'coupon_id' => 'integer',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'discount_amount_vnd' => 'integer',
        'subtotal_vnd' => 'integer',
        'shipping_fee_vnd' => 'integer',
        'grand_total_vnd' => 'integer',
        'guest_expires_at' => 'datetime',
        'payment_expires_at' => 'datetime',
        'reservation_expires_at' => 'datetime',
    ];

    protected function discountAmount(): Attribute
    {
        return $this->vndAttribute('discount_amount', 'discount_amount_vnd');
    }

    protected function subtotal(): Attribute
    {
        return $this->vndAttribute('subtotal', 'subtotal_vnd');
    }

    protected function shippingFee(): Attribute
    {
        return $this->vndAttribute('shipping_fee', 'shipping_fee_vnd');
    }

    protected function grandTotal(): Attribute
    {
        return $this->vndAttribute('grand_total', 'grand_total_vnd');
    }

    private function vndAttribute(string $legacyColumn, string $canonicalColumn)
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => Vnd::canonicalDecimal(
                $value,
                $attributes[$canonicalColumn] ?? null
            ),
            set: function ($value) use ($legacyColumn, $canonicalColumn): array {
                $amount = Vnd::fromDecimal($value);

                return [
                    $legacyColumn => Vnd::decimal($amount),
                    $canonicalColumn => $amount,
                ];
            },
        );
    }

    public function details(): HasMany
    {
        return $this->hasMany(OrderDetail::class, 'order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
