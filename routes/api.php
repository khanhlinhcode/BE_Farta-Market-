<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminSystemController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\WishlistController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:register');
    Route::post('/login', [AuthController::class, 'userLogin'])
        ->middleware('throttle:admin-login');
    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum');
    Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
    Route::get('/products/suggest', [ProductController::class, 'suggest']);
    Route::get('/products/recommended', [ProductController::class, 'recommended']);
    Route::get('/products/{product}/related', [ProductController::class, 'related']);
    Route::get('/products/{product}/frequently-bought-with', [ProductController::class, 'frequentlyBoughtWith']);
    Route::apiResource('products', ProductController::class)->only(['index', 'show']);
    Route::get('/products/{product}/reviews', [ReviewController::class, 'index']);
    Route::post('/order', [OrderController::class, 'store'])
        ->middleware('throttle:guest-orders');
    Route::get('/payment/vnpay-return', [PaymentController::class, 'vnpayReturn']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update'])->middleware('throttle:profile-update');
        Route::patch('/profile', [ProfileController::class, 'update'])->middleware('throttle:profile-update');
        Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->middleware('throttle:uploads');
        Route::post('/profile/change-password', [ProfileController::class, 'updatePassword'])->middleware('throttle:password-change');
        Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:password-change');
        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::put('/addresses/{address}', [AddressController::class, 'update']);
        Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);
        Route::patch('/addresses/{address}/set-default', [AddressController::class, 'setDefault']);
        Route::post('/coupons/validate', [CouponController::class, 'validateCoupon']);
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
    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum');

    Route::middleware(['auth:sanctum', 'admin.panel'])->group(function () {
        Route::get('/dashboard', AdminDashboardController::class);
        Route::get('/dashboard/summary', [AdminDashboardController::class, 'summary']);
        Route::get('/dashboard/revenue-chart', [AdminDashboardController::class, 'revenueChart']);
        Route::get('/dashboard/top-products', [AdminDashboardController::class, 'topProducts']);
        Route::get('/system/queue-health', [AdminSystemController::class, 'queueHealth'])
            ->middleware('admin');
        Route::get('/me', [AuthController::class, 'me']);
        Route::apiResource('categories', CategoryController::class)->except(['destroy']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
            ->middleware('admin');
        Route::apiResource('products', ProductController::class)->except(['destroy']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])
            ->middleware('admin');
        Route::post('/products/{product}/image', [ProductController::class, 'uploadImage'])->middleware('throttle:uploads');
        Route::post('/products/{product}/images', [ProductController::class, 'uploadImages'])->middleware('throttle:uploads');
        Route::delete('/product-images/{image}', [ProductController::class, 'destroyImage']);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/export', [OrderController::class, 'exportCsv']);
        Route::get('/orders/export.csv', [OrderController::class, 'exportCsv']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
        Route::apiResource('coupons', CouponController::class);
        Route::get('/coupons/{coupon}/usage-stats', [CouponController::class, 'usageStats']);
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
