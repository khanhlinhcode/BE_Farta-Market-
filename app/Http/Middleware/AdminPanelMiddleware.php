<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminPanelMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->user()?->role, ['admin', 'staff'], true)) {
            return response()->json([
                'message' => 'Bạn không có quyền truy cập trang quản trị.',
            ], 403);
        }

        if (! $request->user()->mfa_confirmed_at) {
            return response()->json([
                'message' => 'Tài khoản quản trị phải thiết lập MFA.',
                'mfa_enrollment_required' => true,
            ], 403);
        }

        $hasCapabilityMapping = collect($request->route()?->gatherMiddleware() ?? [])
            ->contains(fn (string $middleware): bool => str_starts_with($middleware, 'admin.capability:'));

        if (! $hasCapabilityMapping) {
            return response()->json([
                'message' => 'Admin route capability is not configured.',
            ], 403);
        }

        return $next($request);
    }
}
