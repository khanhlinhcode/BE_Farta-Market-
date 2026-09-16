<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    public const PLACEMENTS = ['hero', 'home_promo'];

    protected $fillable = [
        'placement', 'title_vi', 'title_en', 'subtitle_vi', 'subtitle_en',
        'button_label_vi', 'button_label_en', 'alt_text_vi', 'alt_text_en',
        'link_url', 'image_url', 'image_public_id', 'sort_order', 'is_active',
        'starts_at', 'ends_at',
    ];

    protected $hidden = ['image_public_id'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function scopeCurrentlyVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
