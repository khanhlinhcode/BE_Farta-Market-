<?php

namespace App\Providers;

use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\NotPwnedVerifier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->resolving(UncompromisedVerifier::class, function ($verifier) {
            if (! $verifier instanceof NotPwnedVerifier) {
                return;
            }

            $timeout = new \ReflectionProperty($verifier, 'timeout');
            $timeout->setAccessible(true);
            $timeout->setValue(
                $verifier,
                max(1, (int) config('auth.password_uncompromised_timeout', 3))
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email').'|'.$request->ip());
        });

        RateLimiter::for('guest-orders', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            return [
                Limit::perMinute(5)->by('register:ip:'.$request->ip()),
                Limit::perMinute(3)->by('register:email:'.hash('sha256', Str::lower((string) $request->input('email')))),
            ];
        });

        RateLimiter::for('profile-update', function (Request $request) {
            return Limit::perMinute(20)->by('profile:'.($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('password-change', function (Request $request) {
            return Limit::perMinute(5)->by('password:'.($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(10)->by('upload:'.($request->user()?->id ?? $request->ip()));
        });
    }
}
