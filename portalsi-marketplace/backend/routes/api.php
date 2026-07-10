<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SellerController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TripayCallbackController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\WithdrawalController;
use Illuminate\Support\Facades\Route;

/* ===== Public ===== */
Route::get('/settings/public',          [SettingsController::class, 'publicSettings']);
Route::get('/settings/email-logo.png',  [SettingsController::class, 'emailLogo']);
Route::get('/home',                     [CatalogController::class, 'homeData']);
Route::get('/categories',               [CatalogController::class, 'categories']);
Route::get('/tags',                     [TagController::class, 'index']);
Route::get('/products',                 [CatalogController::class, 'products']);
Route::get('/search/suggest',           [CatalogController::class, 'searchSuggest']);
Route::get('/products/{id}/share-image',[CatalogController::class, 'productShareImage']);
Route::get('/products/{id}',            [CatalogController::class, 'product']);
Route::get('/vendors',                  [CatalogController::class, 'vendors']);
Route::get('/vendors/{id}',             [CatalogController::class, 'vendor']);
Route::get('/payment-methods',          [OrderController::class,   'paymentMethods']);
Route::get('/shipping-options',         [OrderController::class,   'shippingOptions']);
Route::get('/faqs',                     [FaqController::class, 'index']);
Route::get('/reports/categories',       [ReportController::class, 'categories']);

Route::post('/auth/register',           [AuthController::class, 'register']);
Route::post('/auth/login',              [AuthController::class, 'login']);
Route::post('/auth/forgot-password',    [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password',     [AuthController::class, 'resetPassword']);
Route::post('/auth/verify-email',       [AuthController::class, 'verifyEmail']);
Route::post('/auth/confirm-email',      [AuthController::class, 'confirmChangeEmail']);
Route::post('/tripay/callback',         TripayCallbackController::class);

/* ===== Authenticated ===== */
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout',          [AuthController::class, 'logout']);
    Route::get('/auth/me',               [AuthController::class, 'me']);
    Route::put('/auth/profile',          [AuthController::class, 'updateProfile']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
    Route::post('/auth/request-change-email', [AuthController::class, 'requestChangeEmail']);
    Route::post('/auth/resend-verification', [AuthController::class, 'resendVerification']);

    Route::get('/addresses',            [AddressController::class, 'index']);
    Route::post('/addresses',           [AddressController::class, 'store']);
    Route::put('/addresses/{id}',       [AddressController::class, 'update']);
    Route::delete('/addresses/{id}',    [AddressController::class, 'destroy']);

    Route::get('/wishlist',             [WishlistController::class, 'index']);
    Route::post('/wishlist/toggle',     [WishlistController::class, 'toggle']);

    Route::get('/refunds',              [RefundController::class, 'index']);
    Route::get('/orders',               [OrderController::class, 'index']);
    Route::get('/orders/active-count',  [OrderController::class, 'activeCount']);
    Route::get('/orders/{id}',          [OrderController::class, 'show']);
    Route::post('/checkout',            [OrderController::class, 'checkout']);
    Route::post('/checkout/shipping-rates', [OrderController::class, 'shippingRates']);
    Route::post('/checkout/apply-voucher', [OrderController::class, 'applyVoucher']);
    Route::post('/orders/{id}/refresh', [OrderController::class, 'refreshStatus']);
    Route::post('/orders/{id}/simulate',[OrderController::class, 'simulatePay']);
    Route::post('/orders/{id}/done',    [OrderController::class, 'markDone']);
    Route::post('/orders/{id}/return',  [OrderController::class, 'requestReturn']);

    /* Reviews */
    Route::get('/products/{id}/can-review',  [ReviewController::class, 'eligibility']);
    Route::post('/products/{id}/reviews',    [ReviewController::class, 'store']);
    Route::delete('/reviews/{id}',           [ReviewController::class, 'destroy']);

    /* Chat */
    Route::get('/chats',                [ChatController::class, 'index']);
    Route::get('/chats/unread-count',   [ChatController::class, 'unreadCount']);
    Route::get('/chats/{id}',           [ChatController::class, 'show']);
    Route::post('/chats',               [ChatController::class, 'store']);
    Route::post('/chats/{id}/messages', [ChatController::class, 'sendMessage']);

    /* Seller */
    Route::post('/seller/register',         [SellerController::class, 'register']);
    Route::get('/seller/dashboard',         [SellerController::class, 'dashboard']);
    Route::put('/seller/profile',           [SellerController::class, 'updateProfile']);
    Route::post('/seller/username',         [SellerController::class, 'updateUsername']);
    Route::post('/seller/dismiss-warning',   [SellerController::class, 'dismissWarning']);
    Route::post('/seller/finish-tour',        [SellerController::class, 'finishTour']);

    /* Reports & Notifications */
    Route::post('/reports',                  [ReportController::class, 'store']);
    Route::get('/notifications',             [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count',[NotificationController::class, 'unreadCount']);
    Route::get('/notifications/{id}',        [NotificationController::class, 'show']);
    Route::post('/notifications/{id}/read',  [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all',   [NotificationController::class, 'markAllRead']);
    Route::delete('/notifications/{id}',     [NotificationController::class, 'destroy']);
    Route::get('/seller/products',          [SellerController::class, 'products']);
    Route::post('/seller/products',         [SellerController::class, 'storeProduct']);
    Route::put('/seller/products/{id}',     [SellerController::class, 'updateProduct']);
    Route::delete('/seller/products/{id}',  [SellerController::class, 'deleteProduct']);
    Route::get('/seller/orders',            [SellerController::class, 'orders']);
    Route::post('/seller/orders/{id}/process', [SellerController::class, 'processOrder']);
    Route::post('/seller/orders/{id}/ship', [SellerController::class, 'shipOrder']);
    Route::post('/seller/orders/{id}/arrive', [SellerController::class, 'arriveOrder']);
    Route::get('/seller/vouchers',          [SellerController::class, 'vouchers']);
    Route::post('/seller/vouchers',         [SellerController::class, 'storeVoucher']);
    Route::put('/seller/vouchers/{id}',     [SellerController::class, 'updateVoucher']);
    Route::delete('/seller/vouchers/{id}',  [SellerController::class, 'deleteVoucher']);

    /* Withdraw seller */
    Route::get('/seller/withdraw',          [WithdrawalController::class, 'balance']);
    Route::post('/seller/withdraw',         [WithdrawalController::class, 'request']);
    Route::delete('/seller/withdraw/{id}',  [WithdrawalController::class, 'cancel']);
    Route::get('/wallet',                    [WithdrawalController::class, 'userBalance']);
    Route::post('/wallet/withdraw',          [WithdrawalController::class, 'userRequest']);

    /* Follow */
    Route::post('/vendors/{id}/follow',     [FollowController::class, 'toggle']);
    Route::get('/vendors/{id}/follow',      [FollowController::class, 'status']);

    /* Admin */
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/stats',                  [AdminController::class, 'stats']);
        Route::get('/fresh-start/summary',    [AdminController::class, 'freshStartSummary']);
        Route::post('/fresh-start',           [AdminController::class, 'freshStart']);
        Route::get('/users',                  [AdminController::class, 'users']);
        Route::get('/users/{id}',             [AdminController::class, 'userDetail']);
        Route::put('/users/{id}',             [AdminController::class, 'updateUser']);
        Route::delete('/users/{id}',          [AdminController::class, 'deleteUser']);
        Route::get('/vendors',                [AdminController::class, 'vendors']);
        Route::post('/vendors/{id}/verify',   [AdminController::class, 'verifyVendor']);
        Route::post('/vendors/{id}/badge',    [AdminController::class, 'setBadge']);
        Route::post('/vendors/{id}/moderation', [AdminController::class, 'setModeration']);
        Route::delete('/vendors/{id}',        [AdminController::class, 'deleteVendor']);

        Route::get('/withdrawals',            [WithdrawalController::class, 'adminList']);
        Route::post('/withdrawals/{id}',      [WithdrawalController::class, 'adminProcess']);

        Route::get('/faqs',                   [FaqController::class, 'adminList']);
        Route::put('/faqs',                   [FaqController::class, 'adminSave']);

        Route::get('/payment-methods',        [PaymentMethodController::class, 'adminList']);
        Route::put('/payment-methods',        [PaymentMethodController::class, 'adminSave']);

        Route::get('/reports',                [ReportController::class, 'adminGroupedList']);
        Route::post('/reports/{id}',          [ReportController::class, 'adminResolve']);

        Route::get('/orders',                 [AdminController::class, 'orders']);
        Route::get('/orders/{id}',            [AdminController::class, 'orderDetail']);
        Route::put('/orders/{id}',            [AdminController::class, 'updateOrder']);
        Route::get('/returns',                [AdminController::class, 'returns']);
        Route::post('/returns/{id}',          [AdminController::class, 'approveReturn']);
        Route::get('/settings',               [SettingsController::class, 'adminSettings']);
        Route::put('/settings',               [SettingsController::class, 'adminSave']);
        Route::post('/settings/logo',         [SettingsController::class, 'uploadLogo']);
        Route::post('/settings/hero',         [SettingsController::class, 'uploadHero']);
        Route::post('/settings/seo-image',    [SettingsController::class, 'uploadSeoImage']);
        Route::get('/shipping-options',       [AdminController::class, 'shippingOptions']);
        Route::put('/shipping-options',       [AdminController::class, 'saveShippingOptions']);

        Route::get('/tags',                    [AdminController::class, 'tags']);
        Route::post('/tags',                   [AdminController::class, 'saveTag']);
        Route::put('/tags/{id}',               [AdminController::class, 'saveTag']);
        Route::delete('/tags/{id}',            [AdminController::class, 'deleteTag']);
        Route::get('/categories',              [AdminController::class, 'adminCategories']);
        Route::post('/categories',             [AdminController::class, 'saveCategory']);
        Route::put('/categories/{id}',         [AdminController::class, 'saveCategory']);
        Route::delete('/categories/{id}',      [AdminController::class, 'deleteCategory']);
    });
});
