<?php

use App\Http\Controllers\API\AuditLogController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BankDetailController;
use App\Http\Controllers\API\CompanyDetailController;
use App\Http\Controllers\API\CountryController;
use App\Http\Controllers\API\CurrencyController;
use App\Http\Controllers\API\CustomerController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\HealthController;
use App\Http\Controllers\API\AddressTypeController;
use App\Http\Controllers\API\DepartmentController;
use App\Http\Controllers\API\MasterController;
use App\Http\Controllers\API\QuotationStatusController;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\API\SettingsController;
use App\Http\Controllers\API\StateController;
use App\Http\Controllers\API\TaxController;
use App\Http\Controllers\API\UnitController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\QuotationController;
use App\Http\Controllers\API\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:300,1')->group(function (): void {
    Route::get('/health', HealthController::class)->middleware('health.token');

    Route::prefix('auth')->middleware('throttle:10,15')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
        Route::get('/reset-password/validate', [AuthController::class, 'validateResetToken'])->name('password.reset.validate');
    });

    Route::middleware(['auth:sanctum', 'validate.token'])->group(function (): void {
        Route::prefix('auth')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::patch('/change-password', [AuthController::class, 'changePassword']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
        });

        Route::prefix('masters')->group(function (): void {
            Route::get('modules', [RoleController::class, 'modules']);
            Route::get('roles/{role}/permissions', [RoleController::class, 'getPermissions']);
            Route::put('roles/{role}/permissions', [RoleController::class, 'updatePermissions']);
            Route::apiResource('roles', RoleController::class);
            Route::apiResource('departments', DepartmentController::class)->except(['show']);
            Route::apiResource('units', UnitController::class)->except(['show']);
            Route::apiResource('currencies', CurrencyController::class)->except(['show']);
            Route::apiResource('taxes', TaxController::class)->only(['index', 'update']);
            Route::apiResource('address-types', AddressTypeController::class)->except(['show']);

            Route::get('countries/{country}/states', [CountryController::class, 'states']);
            Route::apiResource('countries', CountryController::class)->except(['show']);
            Route::apiResource('states', StateController::class)->except(['show']);

            Route::put('quotation-statuses/reorder', [QuotationStatusController::class, 'reorder']);
            Route::apiResource('quotation-statuses', QuotationStatusController::class)->except(['show']);

            Route::patch('bank-details/{bank_detail}/set-primary', [BankDetailController::class, 'setPrimary']);
            Route::apiResource('bank-details', BankDetailController::class)->except(['show']);

            Route::get('company', [CompanyDetailController::class, 'show']);
            Route::put('company', [CompanyDetailController::class, 'update']);
            Route::post('company/logo', [CompanyDetailController::class, 'uploadLogo']);

            Route::get('settings/{key}', [SettingsController::class, 'show']);
            Route::put('settings/{key}', [SettingsController::class, 'update']);
        });

        Route::get('users/check-email', [UserController::class, 'checkEmail']);
        Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::apiResource('users', UserController::class);

        Route::get('customers/search', [CustomerController::class, 'search']);
        Route::get('customers/check-email', [CustomerController::class, 'checkEmail']);
        Route::get('customers/check-tin', [CustomerController::class, 'checkTin']);
        Route::post('customers/quick-create', [CustomerController::class, 'quickCreate']);
        Route::get('customers/{customer}/stats', [CustomerController::class, 'stats']);
        Route::patch('customers/{customer}/toggle-status', [CustomerController::class, 'toggleStatus']);
        Route::apiResource('customers', CustomerController::class);
        Route::get('products/search', [ProductController::class, 'search']);
        Route::get('products/check-model', [ProductController::class, 'checkModel']);
        Route::patch('products/{product}/toggle-status', [ProductController::class, 'toggleStatus']);
        Route::patch('products/{product}/reorder-images', [ProductController::class, 'reorderImages']);
        Route::post('products/{product}/duplicate', [ProductController::class, 'duplicate']);
        Route::apiResource('products', ProductController::class);
        Route::get('quotations/status-counts', [QuotationController::class, 'statusCounts']);
        Route::get('quotations/stats', [QuotationController::class, 'stats']);
        Route::get('quotations/expiry-alerts', [QuotationController::class, 'expiryAlerts']);
        Route::get('quotations/expiry-summary', [QuotationController::class, 'expirySummary']);
        Route::get('quotations/{quotation}/allowed-statuses', [QuotationController::class, 'allowedStatuses']);
        Route::get('quotations/{quotation}/pdf', [QuotationController::class, 'downloadPdf']);
        Route::post('quotations/{quotation}/email', [QuotationController::class, 'sendEmail']);
        Route::patch('quotations/{quotation}/status', [QuotationController::class, 'changeStatus']);
        Route::post('quotations/{quotation}/duplicate', [QuotationController::class, 'duplicate']);
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

        Route::prefix('audit-logs')->controller(AuditLogController::class)->group(function (): void {
            Route::get('/export', 'export');
            Route::delete('/cleanup', 'cleanup');
            Route::get('/', 'index');
        });
    });
});
