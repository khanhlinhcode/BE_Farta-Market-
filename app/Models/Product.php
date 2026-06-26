<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'img',
        'price',
        'inventory',
        'description',
        'sort_description',
        'facebook',
        'twitter',
        'instagram',
        'linkedin',
        'category_id'
    ];
    protected $table = 'products';

    protected $casts = [
        'price' => 'integer',
        'inventory' => 'integer',
        'category_id' => 'integer',
    ];

    public function category():BelongsTo
    {
        return $this->belongsTo(Category::class,'category_id','id');
    }

    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class);
    }
}
