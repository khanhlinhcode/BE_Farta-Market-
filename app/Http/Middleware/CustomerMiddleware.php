<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Personal access tokens represent the mobile API contract and must
        // always belong to a customer. Stateful browser sessions keep their
        // established controller/policy behavior.
        if ($request->bearerToken() !== null && $request->user()?->role !== 'customer') {
            return response()->json([
                'message' => 'Bạn không có quyền thực hiện thao tác này.',
            ], 403);
        }

        return $next($request);
    }
}
