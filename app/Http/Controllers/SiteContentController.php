<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

class SiteContentController extends Controller
{
    public const CACHE_KEY = 'public:site-content:v1';

    public function __invoke()
    {
        return response()->json(Cache::remember(self::CACHE_KEY, now()->addMinutes(5), function () {
            $settings = SiteSetting::current();

            return [
                'settings' => $settings->only([
                    'brand_name', 'contact_email', 'contact_phone', 'support_phone',
                    'address_vi', 'address_en', 'facebook_url', 'instagram_url',
                    'linkedin_url', 'twitter_url', 'featured_title_vi', 'featured_title_en',
                    'recommended_title_vi', 'recommended_title_en', 'footer_description_vi',
                    'footer_description_en', 'free_shipping_threshold', 'shipping_fee',
                ]),
                'banners' => Banner::query()
                    ->currentlyVisible()
                    ->orderBy('placement')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(),
            ];
        }));
    }
}
