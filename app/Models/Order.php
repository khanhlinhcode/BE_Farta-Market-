<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    public const STATUS_ORDERED = 'ORDERED';
    public const STATUS_PREPARING = 'PREPARING';
    public const STATUS_DELIVERING = 'DELIVERING';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_PENDING_PAYMENT = 'PENDING_PAYMENT';
    public const STATUS_PAYMENT_FAILED = 'PAYMENT_FAILED';

    public const PAYMENT_METHOD_COD = 'cod';
    public const PAYMENT_METHOD_VNPAY = 'vnpay';

    public const PAYMENT_STATUS_PENDING = 'pending';
    public const PAYMENT_STATUS_PAID = 'paid';
    public const PAYMENT_STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_ORDERED,
        self::STATUS_PREPARING,
        self::STATUS_DELIVERING,
        self::STATUS_CANCELLED,
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PAYMENT_FAILED,
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
        'idempotency_key',
    ];

    protected $table = 'orders';

    protected $hidden = [
        'idempotency_key',
    ];

    protected $casts = [
        'user_id' => 'integer',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(OrderDetail::class,'order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
