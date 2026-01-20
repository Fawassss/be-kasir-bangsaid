<?php

use Illuminate\Support\Facades\Route;

// Controllers
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\RajaOngkirController;
use App\Http\Controllers\ProfileController;

// Controllers Auth
use App\Http\Controllers\Auth\AuthController;

// Controllers Admin
use App\Http\Controllers\Admin\ProductSizePriceAdjustmentController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SizeMeasurementController;
use App\Http\Controllers\Admin\SizeChartController;
use App\Http\Controllers\Admin\MeasurementAttributeController;
use App\Http\Controllers\Admin\SizeController;
use App\Http\Controllers\Admin\SizeTypeController;
use App\Http\Controllers\Admin\ColorController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ShippingSettingController;
use App\Http\Controllers\Admin\ProductQuantityDiscountController;
use App\Http\Controllers\Admin\PrintTypeController;

// Controllers User
use App\Http\Controllers\User\CartController;
use App\Http\Controllers\User\ShippingAddressController;
use App\Http\Controllers\User\CartItemDesignController;
use App\Http\Controllers\User\OrderController;
use App\Http\Controllers\User\PaymentController;

// Public Routes
Route::get('/', [WelcomeController::class, 'index']);

# Auth Routes (Public)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:api')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/profile', [AuthController::class, 'profile']);
    });

    # Profile photo routes (Cashier & Admin can access)
    Route::prefix('profile')->group(function () {
        Route::post('/photo', [ProfileController::class, 'uploadPhoto']);
        Route::delete('/photo', [ProfileController::class, 'deletePhoto']);
    });

    # Public authenticated routes (Cashier & Admin can access)
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/{id}', [CategoryController::class, 'show']);
    });

    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/{id}', [ProductController::class, 'show']);
    });

    Route::prefix('transactions')->group(function () {
        Route::get('/', [\App\Http\Controllers\TransactionController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\TransactionController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\TransactionController::class, 'show']);
    });

    # Admin Routes (Admin only)
    Route::middleware('admin')->prefix('admin')->group(function () {

        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::post('/', [UserController::class, 'store']);
            Route::get('/{id}', [UserController::class, 'show']);
            Route::put('/{id}', [UserController::class, 'update']);
            Route::delete('/{id}', [UserController::class, 'destroy']);
        });

        Route::prefix('categories')->group(function () {
            Route::post('/', [CategoryController::class, 'store']);
            Route::put('/{id}', [CategoryController::class, 'update']);
            Route::delete('/{id}', [CategoryController::class, 'destroy']);
        });

        Route::prefix('products')->group(function () {
            Route::post('/', [ProductController::class, 'store']);
            Route::post('/{id}', [ProductController::class, 'update']);
            Route::delete('/{id}', [ProductController::class, 'destroy']);
        });

        Route::prefix('reports')->group(function () {
            Route::get('/summary', [\App\Http\Controllers\Admin\ReportController::class, 'summary']);
            Route::get('/products', [\App\Http\Controllers\Admin\ReportController::class, 'productReport']);
            Route::get('/top-products', [\App\Http\Controllers\Admin\ReportController::class, 'topProducts']);
            Route::get('/daily-sales', [\App\Http\Controllers\Admin\ReportController::class, 'dailySales']);
        });

    });
});