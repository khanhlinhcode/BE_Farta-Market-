<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'image_url', 'image_public_id', 'sort_order', 'is_active'];

    protected $hidden = ['image_public_id'];

    protected $casts = ['sort_order' => 'integer', 'is_active' => 'boolean'];

    protected $table = 'categories';

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
