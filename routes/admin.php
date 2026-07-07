<?php

use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NodeController;
use App\Http\Controllers\Admin\NodeTemplateController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VerifyController;
use App\Http\Controllers\Admin\VmController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'admin', \Illuminate\Routing\Middleware\SubstituteBindings::class])->prefix('admin')->group(function () {
    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index']);

    // Users
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::put('users/{user}', [UserController::class, 'update']);
    Route::post('users/{user}/disable', [UserController::class, 'disable']);
    Route::post('users/{user}/enable', [UserController::class, 'enable']);
    Route::delete('users/{user}', [UserController::class, 'destroy']);

    // VMs
    Route::get('vms', [VmController::class, 'index']);
    Route::get('vms/{vm}', [VmController::class, 'show']);
    Route::put('vms/{vm}', [VmController::class, 'update']);
    Route::post('vms/{vm}/start', [VmController::class, 'start']);
    Route::post('vms/{vm}/stop', [VmController::class, 'stop']);
    Route::post('vms/{vm}/restart', [VmController::class, 'restart']);
    Route::post('vms/{vm}/suspend', [VmController::class, 'suspend']);
    Route::post('vms/{vm}/unsuspend', [VmController::class, 'unsuspend']);
    Route::delete('vms/{vm}', [VmController::class, 'destroy']);
    Route::post('vms/batch', [VmController::class, 'batch']);

    // Nodes
    Route::get('nodes', [NodeController::class, 'index']);
    Route::post('nodes/test-connection', [NodeController::class, 'testConnection']);
    Route::get('nodes/{node}', [NodeController::class, 'show']);
    Route::post('nodes', [NodeController::class, 'store']);
    Route::put('nodes/{node}', [NodeController::class, 'update']);
    Route::delete('nodes/{node}', [NodeController::class, 'destroy']);
    Route::post('nodes/{node}/test', [NodeController::class, 'test']);
    Route::post('nodes/{node}/sync-vms', [NodeController::class, 'syncVms']);
    Route::post('nodes/{node}/sync-templates', [NodeController::class, 'syncTemplates']);
    Route::get('nodes/{node}/templates', [NodeTemplateController::class, 'index']);
    Route::put('nodes/{node}/nat-config', [NodeController::class, 'updateNatConfig']);

    // Products
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::post('products', [ProductController::class, 'store']);
    Route::put('products/{product}', [ProductController::class, 'update']);
    Route::delete('products/{product}', [ProductController::class, 'destroy']);
    Route::put('products/{product}/status', [ProductController::class, 'toggleStatus']);
    Route::put('products/sort', [ProductController::class, 'sort']);

    // Orders
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders/{order}/mark-paid', [OrderController::class, 'markPaid']);
    Route::post('orders/{order}/refund', [OrderController::class, 'refund']);
    Route::get('orders-stats', [OrderController::class, 'stats']);

    // Tickets
    Route::get('tickets', [TicketController::class, 'index']);
    Route::get('tickets/{ticket}', [TicketController::class, 'show']);
    Route::post('tickets/{ticket}/reply', [TicketController::class, 'reply']);
    Route::put('tickets/{ticket}/status', [TicketController::class, 'updateStatus']);
    Route::put('tickets/{ticket}/priority', [TicketController::class, 'updatePriority']);

    // Coupons
    Route::get('coupons', [CouponController::class, 'index']);
    Route::post('coupons', [CouponController::class, 'store']);
    Route::put('coupons/{coupon}', [CouponController::class, 'update']);
    Route::delete('coupons/{coupon}', [CouponController::class, 'destroy']);
    Route::put('coupons/{coupon}/status', [CouponController::class, 'toggleStatus']);
    Route::post('coupons/batch', [CouponController::class, 'batch']);

    // Announcements
    Route::get('announcements', [AnnouncementController::class, 'index']);
    Route::post('announcements', [AnnouncementController::class, 'store']);
    Route::put('announcements/{announcement}', [AnnouncementController::class, 'update']);
    Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy']);
    Route::put('announcements/{announcement}/pin', [AnnouncementController::class, 'togglePin']);
    Route::put('announcements/{announcement}/status', [AnnouncementController::class, 'toggleStatus']);

    // Verifications
    Route::get('verifications', [VerifyController::class, 'index']);
    Route::get('verifications/{verification}', [VerifyController::class, 'show']);
    Route::post('verifications/{verification}/approve', [VerifyController::class, 'approve']);
    Route::post('verifications/{verification}/reject', [VerifyController::class, 'reject']);

    // Backups
    Route::get('backups', [BackupController::class, 'index']);
    Route::post('backups', [BackupController::class, 'store']);
    Route::get('backups/{backup}/download', [BackupController::class, 'download']);
    Route::delete('backups/{backup}', [BackupController::class, 'destroy']);
    Route::post('backups/{backup}/restore', [BackupController::class, 'restore']);
    Route::get('backup-settings', [BackupController::class, 'settings']);
    Route::put('backup-settings', [BackupController::class, 'updateSettings']);

    // Settings
    Route::get('settings/email-templates', [SettingController::class, 'emailTemplates']);
    Route::put('settings/email-templates/{template}', [SettingController::class, 'updateEmailTemplate']);
    Route::get('settings/email-templates/{template}/preview', [SettingController::class, 'previewTemplate']);
    Route::get('settings/{group}', [SettingController::class, 'show']);
    Route::put('settings/{group}', [SettingController::class, 'update']);
    Route::post('settings/test-smtp', [SettingController::class, 'testSmtp']);
    Route::post('settings/test-epay', [SettingController::class, 'testEpay']);
});
