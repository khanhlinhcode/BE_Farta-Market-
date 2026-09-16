<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);

        $response = $next($request);

        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));
        $csp = config('security.csp_report_only');
        if (str_starts_with($contentType, 'text/html') && is_string($csp) && $csp !== '') {
            $response->headers->set('Content-Security-Policy-Report-Only', $csp);
        }

        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age='.max(0, (int) config('security.hsts_max_age')).'; includeSubDomains'
            );
        }

        return $response;
    }
}
