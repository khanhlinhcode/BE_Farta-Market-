<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\NotPwnedVerifier;
use Illuminate\Validation\Rules\Password;

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
        ResetPassword::createUrlUsing(function ($user, string $token): string {
            $query = http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ], '', '&', PHP_QUERY_RFC3986);

            return rtrim((string) config('services.frontend.url'), '/').'/reset-password?'.$query;
        });

        Password::defaults(function () {
            $rule = Password::min(8)->mixedCase()->numbers();

            return app()->runningUnitTests() || app()->environment('testing')
                ? $rule
                : $rule->uncompromised();
        });

        RateLimiter::for('user-login', function (Request $request) {
            return [
                Limit::perMinute(10)->by('user-login:ip:'.$request->ip()),
                Limit::perMinute(7)->by('user-login:account:'.$this->loginAccountHash($request)),
            ];
        });

        RateLimiter::for('admin-login', function (Request $request) {
            return [
                Limit::perMinute((int) config('auth.admin_login_ip_limit', 5))->by('admin-login:ip:'.$request->ip()),
                Limit::perMinute((int) config('auth.admin_login_account_limit', 5))->by('admin-login:account:'.$this->loginAccountHash($request)),
            ];
        });

        RateLimiter::for('mfa-challenge', function (Request $request) {
            return [
                Limit::perMinute((int) config('auth.mfa_challenge_ip_limit', 5))->by('mfa:ip:'.$request->ip()),
                Limit::perMinute((int) config('auth.mfa_challenge_session_limit', 5))->by('mfa:challenge:'.hash('sha256', (string) $request->session()->getId())),
            ];
        });

        RateLimiter::for('guest-orders', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));
            $phone = preg_replace('/\D+/', '', (string) ($request->input('customer_phone') ?: $request->input('phone')));

            return [
                Limit::perHour(5)->by('guest-orders:ip:'.hash('sha256', (string) $request->ip())),
                Limit::perHour(5)->by('guest-orders:email:'.hash('sha256', $email)),
                Limit::perHour(5)->by('guest-orders:phone:'.hash('sha256', $phone)),
                Limit::perMinute(100)->by('guest-orders:global'),
            ];
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

        RateLimiter::for('forgot-password', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by('forgot-password:ip:'.hash('sha256', (string) $request->ip())),
                Limit::perMinute(3)->by('forgot-password:email:'.hash('sha256', $email)),
            ];
        });

        RateLimiter::for('reset-password', function (Request $request) {
            return Limit::perMinute(10)->by('reset-password:ip:'.hash('sha256', (string) $request->ip()));
        });

        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(10)->by('upload:'.($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('analytics', function (Request $request) {
            return [
                Limit::perMinute(60)->by('analytics:ip:'.hash('sha256', (string) $request->ip())),
                Limit::perMinute(30)->by('analytics:token:'.hash('sha256', (string) $request->header('X-Analytics-Token'))),
            ];
        });

        RateLimiter::for('analytics-session', function (Request $request) {
            return [
                Limit::perHour(20)->by('analytics-session:ip:'.hash('sha256', (string) $request->ip())),
                Limit::perMinute(200)->by('analytics-session:global'),
            ];
        });

        RateLimiter::for('verification-resend', function (Request $request) {
            return Limit::perMinute(3)->by('verification:'.($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('chat', function (Request $request) {
            return [
                Limit::perMinute(20)->by('chat:ip:'.hash('sha256', (string) $request->ip())),
                Limit::perMinute(60)->by('chat:global'),
            ];
        });
    }

    private function loginAccountHash(Request $request): string
    {
        return hash('sha256', Str::lower(trim((string) $request->input('email'))));
    }
}
