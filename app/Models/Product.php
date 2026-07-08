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
        'slug',
        'img',
        'price',
        'inventory',
        'is_active',
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
        'is_active' => 'boolean',
    ];

    public function category():BelongsTo
    {
        return $this->belongsTo(Category::class,'category_id','id');
    }

    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_primary')->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }
}
