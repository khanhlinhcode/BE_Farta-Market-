<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminCapabilityMiddleware
{
    public const ALL = [
        'dashboard.read',
        'analytics.read',
        'catalog.write',
        'catalog.delete',
        'media.upload',
        'media.delete',
        'site-settings.write',
        'reviews.moderate',
        'orders.read',
        'orders.transition',
        'coupons.manage',
        'users.manage',
        'system-health.read',
    ];

    public const STAFF = [
        'dashboard.read',
        'analytics.read',
        'catalog.write',
        'media.upload',
        'reviews.moderate',
        'orders.read',
    ];

    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $role = $request->user()?->role;

        if (! in_array($capability, self::ALL, true)
            || ($role !== 'admin' && ($role !== 'staff' || ! in_array($capability, self::STAFF, true)))) {
            return response()->json([
                'message' => 'Bạn không có quyền thực hiện thao tác này.',
            ], 403);
        }

        return $next($request);
    }
}
