<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_name', 'contact_email', 'contact_phone', 'support_phone',
        'address_vi', 'address_en', 'facebook_url', 'instagram_url',
        'linkedin_url', 'twitter_url', 'featured_title_vi', 'featured_title_en',
        'recommended_title_vi', 'recommended_title_en', 'footer_description_vi',
        'footer_description_en', 'free_shipping_threshold', 'shipping_fee', 'updated_by',
    ];

    protected $hidden = ['updated_by'];

    protected $casts = [
        'free_shipping_threshold' => 'integer',
        'shipping_fee' => 'integer',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'brand_name' => 'FartaMarket',
            'contact_email' => 'FartaMarket@gmail.com',
            'contact_phone' => '0977232232',
            'support_phone' => '0393886668',
            'address_vi' => '213 Trương Định, Nghệ An',
            'address_en' => '213 Truong Dinh, Nghe An',
            'featured_title_vi' => 'Sản phẩm nổi bật',
            'featured_title_en' => 'Featured products',
            'recommended_title_vi' => 'Gợi ý cho bạn',
            'recommended_title_en' => 'Recommended for you',
            'free_shipping_threshold' => 200000,
            'shipping_fee' => 20000,
        ]);
    }
}
