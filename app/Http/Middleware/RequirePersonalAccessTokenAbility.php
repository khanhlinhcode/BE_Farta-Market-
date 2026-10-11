<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class RequirePersonalAccessTokenAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        if ($request->bearerToken() === null) {
            return $next($request);
        }

        $accessToken = $request->user('sanctum')?->currentAccessToken();

        if (! $accessToken instanceof PersonalAccessToken) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $accessToken->can($ability)) {
            return response()->json([
                'message' => 'Bạn không có quyền thực hiện thao tác này.',
            ], 403);
        }

        return $next($request);
    }
}
