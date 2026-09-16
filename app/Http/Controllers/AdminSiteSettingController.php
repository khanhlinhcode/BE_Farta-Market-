<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminSiteSettingController extends Controller
{
    public function show()
    {
        return response()->json(SiteSetting::current());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'brand_name' => ['required', 'string', 'max:100'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'regex:/^[0-9+().\s-]{8,30}$/'],
            'support_phone' => ['required', 'string', 'regex:/^[0-9+().\s-]{8,30}$/'],
            'address_vi' => ['required', 'string', 'max:255'],
            'address_en' => ['required', 'string', 'max:255'],
            'facebook_url' => ['nullable', 'url:https', 'max:255'],
            'instagram_url' => ['nullable', 'url:https', 'max:255'],
            'linkedin_url' => ['nullable', 'url:https', 'max:255'],
            'twitter_url' => ['nullable', 'url:https', 'max:255'],
            'featured_title_vi' => ['required', 'string', 'max:160'],
            'featured_title_en' => ['required', 'string', 'max:160'],
            'recommended_title_vi' => ['required', 'string', 'max:160'],
            'recommended_title_en' => ['required', 'string', 'max:160'],
            'footer_description_vi' => ['nullable', 'string', 'max:2000'],
            'footer_description_en' => ['nullable', 'string', 'max:2000'],
            'free_shipping_threshold' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'shipping_fee' => ['required', 'integer', 'min:0', 'max:1000000000'],
        ]);

        $settings = SiteSetting::current();
        $settings->update([...$data, 'updated_by' => $request->user()->id]);
        Cache::forget(SiteContentController::CACHE_KEY);

        return response()->json($settings->fresh());
    }
}
