<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'coupon_id',
        'discount_amount',
        'subtotal',
        'shipping_fee',
        'grand_total',
        'idempotency_key',
    ];

    protected $table = 'orders';

    protected $hidden = [
        'idempotency_key',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'coupon_id' => 'integer',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(OrderDetail::class,'order_id');
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
