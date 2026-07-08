<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CustomerController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\HealthController;
use App\Http\Controllers\API\MasterController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\QuotationController;
use App\Http\Controllers\API\SettingController;
use App\Http\Controllers\API\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:300,1')->group(function (): void {
    Route::get('/health', HealthController::class);

    Route::prefix('auth')->middleware('throttle:10,15')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::prefix('auth')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::patch('/change-password', [AuthController::class, 'changePassword']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
        });

        Route::prefix('masters')->group(function (): void {
            Route::apiResource('roles', MasterController::class);
            Route::apiResource('departments', MasterController::class);
            Route::apiResource('units', MasterController::class);
            Route::apiResource('taxes', MasterController::class);
            Route::apiResource('address-types', MasterController::class);
            Route::apiResource('countries', MasterController::class);
            Route::apiResource('states', MasterController::class);
            Route::apiResource('quotation-statuses', MasterController::class);
            Route::apiResource('bank-details', MasterController::class);
            Route::apiResource('company', MasterController::class)->only(['index', 'store', 'show', 'update']);
            Route::apiResource('settings', SettingController::class);
        });

        Route::apiResource('users', UserController::class);
        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('products', ProductController::class);
        Route::apiResource('quotations', QuotationController::class);

        Route::prefix('dashboard')->controller(DashboardController::class)->group(function (): void {
            Route::get('/all', 'all');
            Route::get('/kpis', 'kpis');
            Route::get('/quotation-by-status', 'quotationByStatus');
            Route::get('/quotation-trend', 'quotationTrend');
            Route::get('/top-products', 'topProducts');
            Route::get('/recent-activity', 'recentActivity');
        });

        Route::get('/search', [MasterController::class, 'search']);
    });
});
