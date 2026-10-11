<?php

namespace App\Models;

use App\Support\Vnd;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'unit_price_vnd',
        'product_name',
        'line_total',
        'line_total_vnd',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'unit_price_vnd' => 'integer',
        'line_total_vnd' => 'integer',
    ];

    protected function unitPrice(): Attribute
    {
        return $this->vndAttribute('unit_price', 'unit_price_vnd');
    }

    protected function lineTotal(): Attribute
    {
        return $this->vndAttribute('line_total', 'line_total_vnd');
    }

    private function vndAttribute(string $legacyColumn, string $canonicalColumn)
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => Vnd::canonicalDecimal($value, $attributes[$canonicalColumn] ?? null),
            set: function ($value) use ($legacyColumn, $canonicalColumn): array {
                $amount = Vnd::fromDecimal($value);

                return [$legacyColumn => Vnd::decimal($amount), $canonicalColumn => $amount];
            },
        );
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
