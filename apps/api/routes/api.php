<?php

use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Api\V1\Admin\SupplierController as AdminSupplierController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:120,1')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'data' => [
                'service' => 'unisell-api',
                'status' => 'ok',
                'version' => 'v1',
            ],
        ]);
    });

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:15,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/profile', [AuthController::class, 'profile']);
            Route::put('/theme', [AuthController::class, 'updateThemePreferences']);
        });
    });

    // Public Storefront Products
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/categories', [ProductController::class, 'categories']);
        Route::get('/search', [ProductController::class, 'search']);
        Route::get('/{slug}', [ProductController::class, 'show']);
    });

    // Unified Orders (guest or authenticated)
    Route::prefix('orders')->group(function () {
        Route::post('/', [OrderController::class, 'store'])->middleware('throttle:30,1');
        Route::get('/history', [OrderController::class, 'history']);
        Route::get('/{orderNo}', [OrderController::class, 'show']);
    });

    // Payments & Webhook
    Route::prefix('payments')->group(function () {
        Route::post('/', [PaymentController::class, 'create'])->middleware('throttle:30,1');
        Route::post('/webhook', [PaymentController::class, 'webhook']);
        Route::post('/simulate-paid', [PaymentController::class, 'simulateLocalPaid']);
    });

    // Admin & Staff Protected Routes
    Route::prefix('admin')
        ->middleware(['auth:sanctum', 'role:admin,staff'])
        ->group(function () {
            Route::get('/dashboard', [AdminDashboardController::class, 'index']);

            // Suppliers & Packages
            Route::get('/suppliers', [AdminSupplierController::class, 'index']);
            Route::post('/suppliers', [AdminSupplierController::class, 'store']);
            Route::get('/suppliers/{supplier}', [AdminSupplierController::class, 'show']);
            Route::put('/suppliers/{supplier}', [AdminSupplierController::class, 'update']);
            Route::get('/suppliers/{supplier}/packages', [AdminSupplierController::class, 'packages']);
            Route::post('/suppliers/{supplier}/packages', [AdminSupplierController::class, 'storePackage']);
            Route::get('/suppliers/{supplier}/health', [AdminSupplierController::class, 'health']);

            // Subscriptions
            Route::get('/subscriptions', [AdminSubscriptionController::class, 'index']);
            Route::post('/subscriptions/subscribe', [AdminSubscriptionController::class, 'subscribe']);
            Route::post('/subscriptions/renew', [AdminSubscriptionController::class, 'renew']);
            Route::post('/subscriptions/cancel', [AdminSubscriptionController::class, 'cancel']);

            // Product Aggregator & Pricing
            Route::get('/supplier-products', [AdminProductController::class, 'supplierProducts']);
            Route::post('/suppliers/{supplier}/sync-products', [AdminProductController::class, 'syncSupplier']);
            Route::post('/suppliers/{supplier}/publish-all', [AdminProductController::class, 'publishAllFromSupplier']);
            Route::post('/suppliers/{supplier}/manual-products', [AdminProductController::class, 'storeManualSupplierProduct']);
            Route::get('/products', [AdminProductController::class, 'index']);
            Route::post('/products', [AdminProductController::class, 'store']);
            Route::post('/products/{product}/publish', [AdminProductController::class, 'publish']);
            Route::put('/products/{product}/pricing', [AdminProductController::class, 'updatePricing']);
            Route::post('/categories', [AdminProductController::class, 'storeCategory']);
            Route::post('/pricing/simulate', [AdminProductController::class, 'simulatePricing']);

            // Orders & Fulfillment
            Route::get('/orders', [AdminOrderController::class, 'index']);
            Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
            Route::post('/orders/{order}/retry', [AdminOrderController::class, 'retry']);
            Route::post('/orders/{order}/manual-deliver', [AdminOrderController::class, 'manualDeliver']);
            Route::post('/orders/{order}/refund', [AdminOrderController::class, 'refund']);

            // Reports
            Route::get('/reports', [AdminReportController::class, 'summary']);
        });
});
