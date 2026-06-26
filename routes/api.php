<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('')->group(function () {
    Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
    Route::apiResource('products', ProductController::class)->only(['index', 'show']);
    Route::post('/order', [OrderController::class, 'store'])
        ->middleware('throttle:guest-orders');
    Route::post('/chat', [ChatController::class, 'send'])
        ->middleware('throttle:20,1');
    Route::get('/chat/health', [ChatController::class, 'health'])
        ->middleware('throttle:30,1');
});

Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:admin-login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::apiResource('categories', CategoryController::class)->except(['destroy']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
            ->middleware('admin');
        Route::apiResource('products', ProductController::class)->except(['destroy']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])
            ->middleware('admin');
        Route::post('/products/{product}/image', [ProductController::class, 'uploadImage']);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])
            ->middleware('admin');
    });
});
