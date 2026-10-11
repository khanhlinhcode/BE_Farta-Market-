<?php

namespace App\Http\Middleware;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeprecationHeaders
{
    public function handle(Request $request, Closure $next, string $successor): Response
    {
        $response = $next($request);
        $response->headers->set('Deprecation', 'true');
        $response->headers->set(
            'Link',
            '</'.ltrim($successor, '/').'>; rel="successor-version"'
        );

        $sunset = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            (string) config('api.legacy_sunset'),
            new DateTimeZone('UTC')
        );
        if ($sunset !== false) {
            $response->headers->set('Sunset', $sunset->format(DATE_RFC7231));
        }

        return $response;
    }
}
