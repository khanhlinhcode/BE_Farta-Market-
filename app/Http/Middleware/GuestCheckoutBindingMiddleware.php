<?php

namespace App\Http\Middleware;

use App\Services\GuestCheckoutBindingService;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class GuestCheckoutBindingMiddleware
{
    public function __construct(private readonly GuestCheckoutBindingService $bindings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user('sanctum') || $request->user()) {
            return $next($request);
        }

        $token = $request->cookie(GuestCheckoutBindingService::COOKIE);
        $hash = $this->bindings->verify($token);
        if ($hash !== null) {
            $request->attributes->set('guest_checkout_binding_hash', $hash);

            return $next($request);
        }

        if ($this->compatibilityWindowEnded()) {
            return response()->json([
                'message' => 'Guest checkout binding is required.',
                'error_code' => 'guest_checkout_binding_required',
            ], 428)->withHeaders($this->deprecationHeaders());
        }

        $issued = $this->bindings->rotate($token);
        $request->attributes->set('guest_checkout_binding_hash', $issued['hash']);
        Log::notice('Legacy guest checkout was issued an opaque binding.', [
            'event' => 'guest_checkout_binding_compatibility_fallback',
        ]);

        return $next($request)
            ->withCookie($this->bindings->cookie($issued['token']))
            ->withHeaders($this->deprecationHeaders());
    }

    private function compatibilityWindowEnded(): bool
    {
        $sunset = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            (string) config('api.legacy_sunset'),
            new DateTimeZone('UTC')
        );

        return $sunset === false || now('UTC')->startOfDay()->timestamp >= $sunset->getTimestamp();
    }

    /** @return array<string, string> */
    private function deprecationHeaders(): array
    {
        $sunset = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            (string) config('api.legacy_sunset'),
            new DateTimeZone('UTC')
        );

        return array_filter([
            'Deprecation' => 'true',
            'Sunset' => $sunset?->format(DATE_RFC7231),
            'Link' => '</api/guest-checkout/binding>; rel="successor-version"',
        ]);
    }
}
