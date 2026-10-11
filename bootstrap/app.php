<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);
        $middleware->statefulApi();
        $middleware->throttleApi('api');
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->redirectGuestsTo(fn (Request $request) => null);

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'admin.capability' => \App\Http\Middleware\AdminCapabilityMiddleware::class,
            'admin.panel' => \App\Http\Middleware\AdminPanelMiddleware::class,
            'customer' => \App\Http\Middleware\CustomerMiddleware::class,
            'deprecated' => \App\Http\Middleware\DeprecationHeaders::class,
            'guest.binding' => \App\Http\Middleware\GuestCheckoutBindingMiddleware::class,
            'pat.ability' => \App\Http\Middleware\RequirePersonalAccessTokenAbility::class,
        ]);
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\AdminPanelMiddleware::class
        );
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\AdminCapabilityMiddleware::class
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            return null;
        });

        $exceptions->render(function (\App\Exceptions\CloudinaryException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Không thể xử lý ảnh trên Cloudinary. Vui lòng thử lại.',
                ], 502);
            }
        });

        $exceptions->render(function (\Illuminate\Contracts\Cache\LockTimeoutException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'A request is already being processed. Please try again.',
                ], 409);
            }
        });
    })->create();
