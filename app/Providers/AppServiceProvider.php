<?php

namespace App\Providers;

use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
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
    }
}
