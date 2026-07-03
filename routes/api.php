<?php

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\VerifyController;
use App\Http\Controllers\Api\VmController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes - no authentication required
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login'])->name('login');
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);
Route::get('verify-email/{token}', [AuthController::class, 'verifyEmail']);

// Epay callbacks - no authentication required
Route::get('payment/notify', [PaymentController::class, 'notify']);
Route::post('payment/notify', [PaymentController::class, 'notifyPost']);

// Public product listing - no auth required for browsing
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);

// Public announcements - no auth required
Route::get('announcements', [AnnouncementController::class, 'index']);
Route::get('announcements/{announcement}', [AnnouncementController::class, 'show']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::post('resend-verification', [AuthController::class, 'resendVerification']);

    // Profile
    Route::put('profile', [ProfileController::class, 'update']);
    Route::put('password', [ProfileController::class, 'changePassword']);
    Route::get('login-logs', [ProfileController::class, 'loginLogs']);
    Route::put('notifications', [ProfileController::class, 'notifications']);
    Route::post('api-key', [ProfileController::class, 'createApiKey']);
    Route::delete('api-key', [ProfileController::class, 'revokeApiKey']);

    // VMs
    Route::get('vms', [VmController::class, 'index']);
    Route::get('vms/{vm}', [VmController::class, 'show']);
    Route::post('vms/{vm}/start', [VmController::class, 'start']);
    Route::post('vms/{vm}/stop', [VmController::class, 'stop']);
    Route::post('vms/{vm}/restart', [VmController::class, 'restart']);
    Route::post('vms/{vm}/reset-password', [VmController::class, 'resetPassword']);
    Route::post('vms/{vm}/reinstall', [VmController::class, 'reinstall']);
    Route::get('vms/{vm}/vnc', [VmController::class, 'vnc']);
    Route::get('vms/{vm}/metrics', [VmController::class, 'metrics']);
    Route::post('vms/{vm}/renew', [VmController::class, 'renew']);
    Route::delete('vms/{vm}', [VmController::class, 'destroy']);

    // Snapshots
    Route::get('vms/{vm}/snapshots', [VmController::class, 'snapshots']);
    Route::post('vms/{vm}/snapshots', [VmController::class, 'createSnapshot']);
    Route::delete('vms/{vm}/snapshots/{snapshot}', [VmController::class, 'deleteSnapshot']);
    Route::post('vms/{vm}/snapshots/{snapshot}/restore', [VmController::class, 'restoreSnapshot']);

    // NAT Rules
    Route::get('vms/{vm}/nat-rules', [VmController::class, 'natRules']);
    Route::post('vms/{vm}/nat-rules', [VmController::class, 'addNatRule']);
    Route::delete('vms/{vm}/nat-rules/{natRule}', [VmController::class, 'deleteNatRule']);

    // Domains
    Route::get('vms/{vm}/domains', [VmController::class, 'domains']);
    Route::post('vms/{vm}/domains', [VmController::class, 'addDomain']);
    Route::delete('vms/{vm}/domains/{domain}', [VmController::class, 'deleteDomain']);

    // Payments
    Route::post('recharge', [PaymentController::class, 'recharge']);

    // Orders
    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders/{order}/pay', [OrderController::class, 'pay']);
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);

    // Tickets
    Route::get('tickets', [TicketController::class, 'index']);
    Route::post('tickets', [TicketController::class, 'store']);
    Route::get('tickets/{ticket}', [TicketController::class, 'show']);
    Route::post('tickets/{ticket}/reply', [TicketController::class, 'reply']);
    Route::post('tickets/{ticket}/close', [TicketController::class, 'close']);

    // Verification
    Route::get('verification', [VerifyController::class, 'show']);
    Route::post('verification', [VerifyController::class, 'store']);
    Route::put('verification', [VerifyController::class, 'update']);

    // Billing
    Route::get('billing', [BillingController::class, 'index']);
    Route::get('billing/transactions', [BillingController::class, 'transactions']);
    Route::get('billing/expiring', [BillingController::class, 'expiring']);
});
