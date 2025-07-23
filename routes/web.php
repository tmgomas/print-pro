<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\ReportController;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Home/Landing Page
Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    // Login Routes
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);

    // Registration Routes
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);

    // Password Reset Routes
    Route::get('forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    // Logout
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Email Verification Routes
    Route::get('verify-email', [AuthController::class, 'verificationNotice'])->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Password Confirmation
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Authenticated Users)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    
    /*
    |--------------------------------------------------------------------------
    | Single Dashboard Route - All users go here
    |--------------------------------------------------------------------------
    */
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Dashboard AJAX routes for widgets and data
    Route::get('dashboard/widget/{widget}', [DashboardController::class, 'getWidgetData'])->name('dashboard.widget');
    Route::post('dashboard/settings', [DashboardController::class, 'updateWidgetSettings'])->name('dashboard.settings');

    /*
    |--------------------------------------------------------------------------
    | User Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage users')->group(function () {
        Route::resource('users', UserManagementController::class);
        Route::post('users/bulk-action', [UserManagementController::class, 'bulkAction'])->name('users.bulk-action');
        Route::patch('users/{id}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('users.toggle-status');
    });

    /*
    |--------------------------------------------------------------------------
    | Company Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage companies')->group(function () {
        Route::resource('companies', CompanyController::class);
        Route::patch('companies/{id}/toggle-status', [CompanyController::class, 'toggleStatus'])->name('companies.toggle-status');
        Route::get('companies/{id}/branches', [CompanyController::class, 'branches'])->name('companies.branches');
        Route::get('companies/{id}/users', [CompanyController::class, 'users'])->name('companies.users');
        Route::get('companies/{id}/settings', [CompanyController::class, 'settings'])->name('companies.settings');
        Route::put('companies/{id}/settings', [CompanyController::class, 'updateSettings'])->name('companies.update-settings');
    });

    /*
    |--------------------------------------------------------------------------
    | Branch Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage branches')->group(function () {
        Route::resource('branches', BranchController::class);
        Route::patch('branches/{id}/toggle-status', [BranchController::class, 'toggleStatus'])->name('branches.toggle-status');
        Route::get('branches/{id}/users', [BranchController::class, 'users'])->name('branches.users');
        Route::get('branches/{id}/settings', [BranchController::class, 'settings'])->name('branches.settings');
        Route::put('branches/{id}/settings', [BranchController::class, 'updateSettings'])->name('branches.update-settings');
    });

    /*
    |--------------------------------------------------------------------------
    | Product Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage products')->group(function () {
        Route::resource('products', ProductController::class);
        Route::patch('products/{id}/toggle-status', [ProductController::class, 'toggleStatus'])->name('products.toggle-status');
        Route::post('products/bulk-action', [ProductController::class, 'bulkAction'])->name('products.bulk-action');
        Route::get('products/{id}/pricing', [ProductController::class, 'pricing'])->name('products.pricing');
        Route::put('products/{id}/pricing', [ProductController::class, 'updatePricing'])->name('products.update-pricing');
    });

    /*
    |--------------------------------------------------------------------------
    | Customer Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage customers')->group(function () {
        Route::resource('customers', CustomerController::class);
        Route::patch('customers/{id}/toggle-status', [CustomerController::class, 'toggleStatus'])->name('customers.toggle-status');
        Route::get('customers/{id}/orders', [CustomerController::class, 'orders'])->name('customers.orders');
        Route::get('customers/{id}/invoices', [CustomerController::class, 'invoices'])->name('customers.invoices');
        Route::get('customers/{id}/payments', [CustomerController::class, 'payments'])->name('customers.payments');
    });

    /*
    |--------------------------------------------------------------------------
    | Order Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage orders')->group(function () {
        Route::resource('orders', OrderController::class);
        Route::patch('orders/{id}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
        Route::post('orders/{id}/duplicate', [OrderController::class, 'duplicate'])->name('orders.duplicate');
        Route::get('orders/{id}/invoice', [OrderController::class, 'generateInvoice'])->name('orders.generate-invoice');
        Route::post('orders/bulk-action', [OrderController::class, 'bulkAction'])->name('orders.bulk-action');
    });

    /*
    |--------------------------------------------------------------------------
    | Invoice Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage invoices')->group(function () {
        Route::resource('invoices', InvoiceController::class);
        Route::get('invoices/{id}/pdf', [InvoiceController::class, 'generatePDF'])->name('invoices.pdf');
        Route::post('invoices/{id}/send', [InvoiceController::class, 'sendEmail'])->name('invoices.send');
        Route::patch('invoices/{id}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.update-status');
    });

    /*
    |--------------------------------------------------------------------------
    | Payment Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage payments')->group(function () {
        Route::resource('payments', PaymentController::class);
        Route::post('payments/{id}/verify', [PaymentController::class, 'verify'])->name('payments.verify');
        Route::post('payments/{id}/reject', [PaymentController::class, 'reject'])->name('payments.reject');
        Route::get('payments/{id}/receipt', [PaymentController::class, 'generateReceipt'])->name('payments.receipt');
    });

    /*
    |--------------------------------------------------------------------------
    | Production Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage production')->group(function () {
        Route::resource('production', ProductionController::class);
        Route::patch('production/{id}/status', [ProductionController::class, 'updateStatus'])->name('production.update-status');
        Route::get('production/queue', [ProductionController::class, 'queue'])->name('production.queue');
        Route::post('production/{id}/assign', [ProductionController::class, 'assignStaff'])->name('production.assign');
    });

    /*
    |--------------------------------------------------------------------------
    | Delivery Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage delivery')->group(function () {
        Route::resource('delivery', DeliveryController::class);
        Route::patch('delivery/{id}/status', [DeliveryController::class, 'updateStatus'])->name('delivery.update-status');
        Route::get('delivery/schedule', [DeliveryController::class, 'schedule'])->name('delivery.schedule');
        Route::post('delivery/{id}/assign-driver', [DeliveryController::class, 'assignDriver'])->name('delivery.assign-driver');
        Route::get('delivery/{id}/track', [DeliveryController::class, 'track'])->name('delivery.track');
    });

    /*
    |--------------------------------------------------------------------------
    | Report Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:view reports')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
        Route::get('reports/production', [ReportController::class, 'production'])->name('reports.production');
        Route::get('reports/delivery', [ReportController::class, 'delivery'])->name('reports.delivery');
        Route::get('reports/financial', [ReportController::class, 'financial'])->name('reports.financial');
        Route::post('reports/export', [ReportController::class, 'export'])->name('reports.export');
    });
});

/*
|--------------------------------------------------------------------------
| Settings Routes
|--------------------------------------------------------------------------
*/
require __DIR__.'/settings.php';