<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\WishlistController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('')->group(function () {
    Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
    Route::apiResource('products', ProductController::class)->only(['index', 'show']);
    Route::get('/products/{product}/reviews', [ReviewController::class, 'index']);
    Route::post('/order', [OrderController::class, 'store'])
        ->middleware('throttle:guest-orders');
    Route::get('/payment/vnpay-return', [PaymentController::class, 'vnpayReturn']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/payment/create', [PaymentController::class, 'create']);
        Route::get('/my-orders', [OrderController::class, 'myOrders']);
        Route::patch('/my-orders/{order}/cancel', [OrderController::class, 'cancelMyOrder']);
        Route::get('/wishlist', [WishlistController::class, 'index']);
        Route::post('/wishlist/{product}', [WishlistController::class, 'store']);
        Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy']);
        Route::get('/products/{product}/reviews/eligibility', [ReviewController::class, 'eligibility']);
        Route::post('/products/{product}/reviews', [ReviewController::class, 'store']);
    });
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
        Route::get('/users', [AdminUserController::class, 'index'])
            ->middleware('admin');
        Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole'])
            ->middleware('admin');
        Route::get('/users/{user}/orders', [AdminUserController::class, 'orders'])
            ->middleware('admin');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])
            ->middleware('admin');
    });
});
