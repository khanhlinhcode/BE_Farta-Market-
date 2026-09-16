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

        return $next($request);
    }
}
