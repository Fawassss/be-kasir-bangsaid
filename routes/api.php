<?php

use Illuminate\Support\Facades\Route;

// Controllers
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\RajaOngkirController;

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
use App\Http\Controllers\Admin\CategoriesController;
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

    # Admin Routes (Admin only)
    Route::middleware('admin')->prefix('admin')->group(function () {

        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::post('/', [UserController::class, 'store']);
            Route::get('/{id}', [UserController::class, 'show']);
            Route::put('/{id}', [UserController::class, 'update']);
            Route::delete('/{id}', [UserController::class, 'destroy']);
        });

    });
});