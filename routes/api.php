<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminMediaController;
use App\Http\Controllers\AdminMfaController;
use App\Http\Controllers\AdminSiteSettingController;
use App\Http\Controllers\AdminSystemController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\GuestCheckoutBindingController;
use App\Http\Controllers\MobileAuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SiteContentController;
use App\Http\Controllers\WishlistController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return response()->json((new UserResource($request->user()))->resolve($request));
})->middleware(['auth:sanctum', 'pat.ability:mobile:customer', 'customer', 'deprecated:api/me']);

Route::prefix('')->group(function () {
    Route::prefix('mobile')->group(function () {
        Route::post('/register', [MobileAuthController::class, 'register'])
            ->middleware('throttle:register');
        Route::post('/login', [MobileAuthController::class, 'login'])
            ->middleware('throttle:user-login');
        Route::post('/forgot-password', [PasswordResetController::class, 'requestMobileLink'])
            ->middleware('throttle:forgot-password');
        Route::post('/reset-password', [PasswordResetController::class, 'mobileReset'])
            ->middleware('throttle:reset-password');
        Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');
        Route::middleware(['auth:sanctum', 'pat.ability:mobile:customer', 'customer'])->group(function () {
            Route::post('/logout', [MobileAuthController::class, 'logout']);
            Route::get('/email/verification-status', [EmailVerificationController::class, 'status']);
            Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
                ->middleware('throttle:verification-resend');
        });
    });

    Route::post('/register', [AuthController::class, 'register'])
        ->block(35, 1)
        ->middleware('throttle:register');
    Route::post('/login', [AuthController::class, 'userLogin'])
        ->block(35, 1)
        ->middleware('throttle:user-login');
    Route::post('/forgot-password', [PasswordResetController::class, 'requestLink'])
        ->block(35, 1)
        ->middleware('throttle:forgot-password');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->block(35, 1)
        ->middleware('throttle:reset-password');
    Route::post('/logout', [AuthController::class, 'logout'])
        ->block(35, 1)
        ->middleware('auth:sanctum');
    Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
    Route::get('/site-content', SiteContentController::class);
    Route::post('/analytics/session', [AnalyticsController::class, 'issueSession'])
        ->middleware('throttle:analytics-session');
    Route::post('/analytics/page-view', [AnalyticsController::class, 'store'])
        ->middleware('throttle:analytics');
    Route::post('/guest-checkout/binding', GuestCheckoutBindingController::class)
        ->middleware('throttle:30,1');
    Route::get('/products/suggest', [ProductController::class, 'suggest'])->middleware('throttle:expensive-read');
    Route::get('/products/recommended', [ProductController::class, 'recommended'])->middleware('throttle:expensive-read');
    Route::get('/products/{product}/related', [ProductController::class, 'related'])->middleware('throttle:expensive-read');
    Route::get('/products/{product}/frequently-bought-with', [ProductController::class, 'frequentlyBoughtWith'])->middleware('throttle:expensive-read');
    Route::apiResource('products', ProductController::class)->only(['index', 'show']);
    Route::get('/products/{product}/reviews', [ReviewController::class, 'index']);
    Route::post('/order', [OrderController::class, 'store'])
        ->middleware(['pat.ability:mobile:customer', 'guest.binding', 'throttle:guest-orders', 'throttle:authenticated-checkout']);
    Route::post('/payment/sepay/webhook', [PaymentController::class, 'webhook'])
        ->middleware('throttle:120,1');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1']);
    Route::middleware(['auth:sanctum', 'pat.ability:mobile:customer', 'customer'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update'])
            ->middleware(['throttle:profile-update', 'deprecated:api/profile']);
        Route::patch('/profile', [ProfileController::class, 'update'])->middleware('throttle:profile-update');
        Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->middleware('throttle:uploads');
        Route::post('/profile/change-password', [ProfileController::class, 'updatePassword'])
            ->middleware(['throttle:password-change', 'deprecated:api/profile/password']);
        Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:password-change');
        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::put('/addresses/{address}', [AddressController::class, 'update']);
        Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);
        Route::patch('/addresses/{address}/set-default', [AddressController::class, 'setDefault']);
        Route::get('/email/verification-status', [EmailVerificationController::class, 'status']);
        Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
            ->middleware('throttle:verification-resend');
        Route::post('/coupons/validate', [CouponController::class, 'validateCoupon'])->middleware('verified');
        Route::post('/payment/create', [PaymentController::class, 'create'])
            ->middleware(['verified', 'throttle:authenticated-checkout']);
        Route::get('/payment/{order}/status', [PaymentController::class, 'status'])
            ->middleware(['verified', 'throttle:60,1']);
        Route::get('/my-orders', [OrderController::class, 'myOrders']);
        Route::get('/my-orders/{order}', [OrderController::class, 'myOrder']);
        Route::patch('/my-orders/{order}/cancel', [OrderController::class, 'cancelMyOrder']);
        Route::get('/wishlist', [WishlistController::class, 'index']);
        Route::post('/wishlist/{product}', [WishlistController::class, 'store']);
        Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy']);
        Route::get('/products/{product}/reviews/eligibility', [ReviewController::class, 'eligibility']);
        Route::post('/products/{product}/reviews', [ReviewController::class, 'store'])->middleware('verified');
    });
    Route::post('/chat', [ChatController::class, 'send'])
        ->middleware('throttle:chat');
    Route::get('/chat/health', [ChatController::class, 'health'])
        ->middleware('throttle:30,1');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->block(35, 1)
        ->middleware('throttle:admin-login');
    Route::post('/logout', [AuthController::class, 'logout'])
        ->block(35, 1)
        ->middleware('auth:sanctum');
    Route::post('/mfa/setup', [AdminMfaController::class, 'setup'])
        ->middleware('throttle:mfa-challenge');
    Route::post('/mfa/confirm', [AdminMfaController::class, 'confirm'])
        ->middleware('throttle:mfa-challenge');
    Route::post('/mfa/challenge', [AdminMfaController::class, 'challenge'])
        ->middleware('throttle:mfa-challenge');

    Route::middleware(['auth:sanctum', 'admin.panel'])->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->middleware('admin.capability:dashboard.read');
        Route::get('/dashboard/summary', [AdminDashboardController::class, 'summary'])->middleware('admin.capability:dashboard.read');
        Route::get('/dashboard/revenue-chart', [AdminDashboardController::class, 'revenueChart'])->middleware('admin.capability:dashboard.read');
        Route::get('/dashboard/top-products', [AdminDashboardController::class, 'topProducts'])->middleware('admin.capability:dashboard.read');
        Route::get('/media', [AdminMediaController::class, 'index'])->middleware('admin.capability:media.upload');
        Route::get('/system/queue-health', [AdminSystemController::class, 'queueHealth'])
            ->middleware('admin.capability:system-health.read');
        Route::get('/me', [AuthController::class, 'me'])->middleware('admin.capability:dashboard.read');
        Route::post('/mfa/recovery-codes', [AdminMfaController::class, 'regenerateRecoveryCodes'])
            ->middleware(['admin.capability:dashboard.read', 'throttle:mfa-challenge']);
        Route::delete('/mfa', [AdminMfaController::class, 'disable'])
            ->middleware(['admin.capability:dashboard.read', 'throttle:mfa-challenge']);
        Route::apiResource('categories', CategoryController::class)->except(['destroy'])->middleware('admin.capability:catalog.write');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
            ->middleware('admin.capability:catalog.delete');
        Route::post('/categories/{category}/image', [CategoryController::class, 'uploadImage'])->middleware('admin.capability:media.upload');
        Route::post('/categories/{category}/image/reuse', [CategoryController::class, 'reuseImage'])->middleware('admin.capability:media.upload');
        Route::delete('/categories/{category}/image', [CategoryController::class, 'destroyImage'])
            ->middleware('admin.capability:media.delete');
        Route::apiResource('products', ProductController::class)->except(['destroy'])->middleware('admin.capability:catalog.write');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])
            ->middleware('admin.capability:catalog.delete');
        Route::post('/products/{product}/image', [ProductController::class, 'uploadImage'])->middleware(['admin.capability:media.upload', 'throttle:uploads']);
        Route::post('/products/{product}/images', [ProductController::class, 'uploadImages'])->middleware(['admin.capability:media.upload', 'throttle:uploads']);
        Route::post('/products/{product}/images/reuse', [ProductController::class, 'reuseImage'])->middleware('admin.capability:media.upload');
        Route::delete('/product-images/{image}', [ProductController::class, 'destroyImage'])->middleware('admin.capability:media.delete');
        Route::patch('/product-images/{image}/primary', [ProductController::class, 'setPrimaryImage'])->middleware('admin.capability:media.upload');
        Route::patch('/products/{product}/images/order', [ProductController::class, 'reorderImages'])->middleware('admin.capability:media.upload');
        Route::get('/site-settings', [AdminSiteSettingController::class, 'show'])->middleware('admin.capability:site-settings.write');
        Route::put('/site-settings', [AdminSiteSettingController::class, 'update'])->middleware('admin.capability:site-settings.write');
        Route::apiResource('banners', BannerController::class)->except(['destroy'])->middleware('admin.capability:catalog.write');
        Route::delete('/banners/{banner}', [BannerController::class, 'destroy'])->middleware('admin.capability:catalog.delete');
        Route::get('/reviews', [ReviewController::class, 'adminIndex'])->middleware('admin.capability:reviews.moderate');
        Route::patch('/reviews/{review}/visibility', [ReviewController::class, 'updateVisibility'])->middleware('admin.capability:reviews.moderate');
        Route::get('/analytics/overview', [AnalyticsController::class, 'overview'])->middleware('admin.capability:analytics.read');
        Route::get('/orders', [OrderController::class, 'index'])->middleware('admin.capability:orders.read');
        Route::get('/orders/export', [OrderController::class, 'exportCsv'])
            ->middleware(['admin.capability:orders.read', 'throttle:admin-export', 'deprecated:api/admin/orders/export.csv']);
        Route::get('/orders/export.csv', [OrderController::class, 'exportCsv'])->middleware(['admin.capability:orders.read', 'throttle:admin-export']);
        Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('admin.capability:orders.read');
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->middleware('admin.capability:orders.transition');
        Route::apiResource('coupons', CouponController::class)->middleware('admin.capability:coupons.manage');
        Route::get('/coupons/{coupon}/usage-stats', [CouponController::class, 'usageStats'])->middleware('admin.capability:coupons.manage');
        Route::get('/users', [AdminUserController::class, 'index'])
            ->middleware('admin.capability:users.manage');
        Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole'])
            ->middleware('admin.capability:users.manage');
        Route::get('/users/{user}/orders', [AdminUserController::class, 'orders'])
            ->middleware('admin.capability:users.manage');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])
            ->middleware('admin.capability:users.manage');
    });
});
