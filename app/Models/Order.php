<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    public const STATUS_ORDERED = 'ORDERED';
    public const STATUS_PREPARING = 'PREPARING';
    public const STATUS_DELIVERING = 'DELIVERING';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUSES = [
        self::STATUS_ORDERED,
        self::STATUS_PREPARING,
        self::STATUS_DELIVERING,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'fullname',
        'address',
        'phone',
        'email',
        'note',
        'status',
        'idempotency_key',
    ];

    protected $table = 'orders';

    protected $hidden = [
        'idempotency_key',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(OrderDetail::class,'order_id');
    }
}
