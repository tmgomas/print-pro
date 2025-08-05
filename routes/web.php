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
use App\Http\Controllers\InvoiceController; // ✅ This should be present
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentVerificationController; // ✅ Add this if missing
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

    // Email Verification
    Route::get('verify-email', [VerifyEmailController::class, '__invoke'])->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', [VerifyEmailController::class, 'verify'])->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])->name('verification.send');

    // Password Confirmation
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    /*
    |--------------------------------------------------------------------------
    | Dashboard Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | User Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage users')->group(function () {
        Route::resource('users', UserManagementController::class);
        Route::patch('users/{user}/activate', [UserManagementController::class, 'activate'])->name('users.activate');
        Route::patch('users/{user}/deactivate', [UserManagementController::class, 'deactivate'])->name('users.deactivate');
        Route::patch('users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('users.reset-password');
    });

    /*
    |--------------------------------------------------------------------------
    | Company Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage companies')->group(function () {
        Route::resource('companies', CompanyController::class);
        Route::patch('companies/{company}/activate', [CompanyController::class, 'activate'])->name('companies.activate');
        Route::patch('companies/{company}/deactivate', [CompanyController::class, 'deactivate'])->name('companies.deactivate');
    });

    /*
    |--------------------------------------------------------------------------
    | Branch Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage branches')->group(function () {
        Route::resource('branches', BranchController::class);
        Route::patch('branches/{branch}/activate', [BranchController::class, 'activate'])->name('branches.activate');
        Route::patch('branches/{branch}/deactivate', [BranchController::class, 'deactivate'])->name('branches.deactivate');
    });

    /*
    |--------------------------------------------------------------------------
    | Product Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage products')->group(function () {
        Route::resource('products', ProductController::class);
        Route::patch('products/{product}/activate', [ProductController::class, 'activate'])->name('products.activate');
        Route::patch('products/{product}/deactivate', [ProductController::class, 'deactivate'])->name('products.deactivate');
        Route::get('products/{product}/specifications', [ProductController::class, 'getSpecifications'])->name('products.specifications');
    });

    /*
    |--------------------------------------------------------------------------
    | Customer Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage customers')->group(function () {
        Route::resource('customers', CustomerController::class);
        Route::patch('customers/{customer}/activate', [CustomerController::class, 'activate'])->name('customers.activate');
        Route::patch('customers/{customer}/deactivate', [CustomerController::class, 'deactivate'])->name('customers.deactivate');
        Route::get('customers/{customer}/credit-history', [CustomerController::class, 'getCreditHistory'])->name('customers.credit-history');
    });

    /*
    |--------------------------------------------------------------------------
    | Order Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage orders')->group(function () {
        Route::resource('orders', OrderController::class);
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
        Route::post('orders/{order}/convert-to-invoice', [OrderController::class, 'convertToInvoice'])->name('orders.convert-to-invoice');
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
        Route::post('invoices/{id}/record-payment', [InvoiceController::class, 'recordPayment'])->name('invoices.record-payment');
        Route::post('invoices/{id}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate');
        Route::get('invoices/{id}/api', [InvoiceController::class, 'apiShow'])->name('invoices.api-show');
        Route::post('invoices/bulk-action', [InvoiceController::class, 'bulkAction'])->name('invoices.bulk-action');
    });

    /*
    |--------------------------------------------------------------------------
    | Payment Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage payments')->group(function () {
        Route::resource('payments', PaymentController::class);
Route::post('payments/{payment}/verify', [PaymentController::class, 'verify'])
    ->name('payments.verify');
Route::post('payments/{payment}/reject', [PaymentController::class, 'reject'])
    ->name('payments.reject');
       
        Route::get('payments/{id}/receipt', [PaymentController::class, 'generateReceipt'])->name('payments.receipt');
    });

    /*
    |--------------------------------------------------------------------------
    | Payment Verification Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage payments')->group(function () {
        Route::resource('payment-verifications', PaymentVerificationController::class);
       Route::post('payment-verifications/{id}/verify', [PaymentVerificationController::class, 'verify'])
    ->name('payment-verifications.verify');
Route::post('payment-verifications/{id}/reject', [PaymentVerificationController::class, 'reject'])
    ->name('payment-verifications.reject');
    });

    /*
    |--------------------------------------------------------------------------
    | Production Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage production')->group(function () {
        Route::resource('productions', ProductionController::class);
        Route::patch('productions/{production}/status', [ProductionController::class, 'updateStatus'])->name('productions.update-status');
        Route::post('productions/{production}/assign', [ProductionController::class, 'assignStaff'])->name('productions.assign');
    });

    /*
    |--------------------------------------------------------------------------
    | Delivery Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage deliveries')->group(function () {
        Route::resource('deliveries', DeliveryController::class);
        Route::patch('deliveries/{delivery}/status', [DeliveryController::class, 'updateStatus'])->name('deliveries.update-status');
        Route::post('deliveries/{delivery}/assign', [DeliveryController::class, 'assignDeliveryPerson'])->name('deliveries.assign');
        Route::get('deliveries/{delivery}/tracking', [DeliveryController::class, 'getTrackingInfo'])->name('deliveries.tracking');
    });

    /*
    |--------------------------------------------------------------------------
    | Report Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:view reports')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/sales', [ReportController::class, 'salesReport'])->name('reports.sales');
        Route::get('reports/payments', [ReportController::class, 'paymentsReport'])->name('reports.payments');
        Route::get('reports/production', [ReportController::class, 'productionReport'])->name('reports.production');
        Route::get('reports/delivery', [ReportController::class, 'deliveryReport'])->name('reports.delivery');
        Route::get('reports/financial', [ReportController::class, 'financialReport'])->name('reports.financial');
    });

    /*
    |--------------------------------------------------------------------------
    | API Routes for AJAX calls
    |--------------------------------------------------------------------------
    */
    Route::prefix('api')->group(function () {
        Route::get('customers/search', [CustomerController::class, 'search'])->name('api.customers.search');
        Route::get('products/search', [ProductController::class, 'search'])->name('api.products.search');
        Route::get('invoices/{id}/payment-data', [InvoiceController::class, 'getPaymentData'])->name('api.invoices.payment-data');
    });
});

/*
|--------------------------------------------------------------------------
| Public API Routes (if needed)
|--------------------------------------------------------------------------
*/
Route::prefix('webhook')->group(function () {
    // Payment gateway webhooks
    Route::post('payhere', [PaymentController::class, 'payhereWebhook'])->name('webhook.payhere');
    Route::post('stripe', [PaymentController::class, 'stripeWebhook'])->name('webhook.stripe');
});

/*
|--------------------------------------------------------------------------
| Fallback Route
|--------------------------------------------------------------------------
*/
Route::fallback(function () {
    return Inertia::render('errors/404');
});