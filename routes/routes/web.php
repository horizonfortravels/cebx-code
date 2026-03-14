<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\AuthWebController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\ShipmentWebController;
use App\Http\Controllers\Web\OrderWebController;
use App\Http\Controllers\Web\StoreWebController;
use App\Http\Controllers\Web\WalletWebController;
use App\Http\Controllers\Web\UserWebController;
use App\Http\Controllers\Web\SupportWebController;
use App\Http\Controllers\Web\PageController;

/*
|--------------------------------------------------------------------------
| Web Routes — Shipping Gateway (Blade)
|--------------------------------------------------------------------------
|
| جميع المسارات تحت prefix /b2b/
| مثال: /b2b/login, /b2b/shipments, /b2b/orders
|
*/

// ══════════════════════════════════════════════════════════════
// Gateway Portal — صفحة اختيار البوابة (الجذر /)
// ══════════════════════════════════════════════════════════════
Route::get('/', function () {
    return view('pages.gateway.index');
})->name('gateway');

// ── PWA Offline Page ──
Route::get('/offline', function () {
    return view('offline');
})->name('offline');

// ══════════════════════════════════════════════════════════════
// B2B Portal — /b2b/*
// ══════════════════════════════════════════════════════════════
Route::prefix('b2b')->group(function () {

    // ── Auth ──
    Route::get('/login', [AuthWebController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthWebController::class, 'login']);
    Route::post('/logout', [AuthWebController::class, 'logout'])->name('logout');

    // ── Protected Routes (web guard + tenant) ──
    Route::middleware(['auth:web', 'tenant'])->group(function () {

        // Dashboard
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // ── Shipments ──
        Route::get('/shipments', [ShipmentWebController::class, 'index'])->name('shipments.index');
        Route::post('/shipments', [ShipmentWebController::class, 'store'])->name('shipments.store');
        Route::get('/shipments/export', [ShipmentWebController::class, 'export'])->name('shipments.export');
        Route::get('/shipments/{shipment}', [ShipmentWebController::class, 'show'])->name('shipments.show');
        Route::patch('/shipments/{shipment}/cancel', [ShipmentWebController::class, 'cancel'])->name('shipments.cancel');
        Route::post('/shipments/{shipment}/return', [ShipmentWebController::class, 'createReturn'])->name('shipments.return');
        Route::get('/shipments/{shipment}/label', [ShipmentWebController::class, 'label'])->name('shipments.label');

        // ── Orders ──
        Route::get('/orders', [OrderWebController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [OrderWebController::class, 'show'])->name('orders.show');

        // ── Stores ──
        Route::get('/stores', [StoreWebController::class, 'index'])->name('stores.index');
        Route::post('/stores', [StoreWebController::class, 'store'])->name('stores.store');
        Route::post('/stores/{store}/sync', [StoreWebController::class, 'sync'])->name('stores.sync');
        Route::post('/stores/{store}/test', [StoreWebController::class, 'test'])->name('stores.test');
        Route::delete('/stores/{store}', [StoreWebController::class, 'destroy'])->name('stores.destroy');

        // ── Wallet ──
        Route::get('/wallet', [WalletWebController::class, 'index'])->name('wallet.index');
        Route::post('/wallet/topup', [WalletWebController::class, 'topup'])->name('wallet.topup');

        // ── Users ──
        Route::get('/users', [UserWebController::class, 'index'])->name('users.index');
        Route::post('/users', [UserWebController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}/toggle', [UserWebController::class, 'toggle'])->name('users.toggle');

        // ── Support ──
        Route::get('/support', [SupportWebController::class, 'index'])->name('support.index');
        Route::get('/support/{ticket}', [SupportWebController::class, 'show'])->name('support.show');
        Route::post('/support', [SupportWebController::class, 'store'])->name('support.store');
        Route::post('/support/{ticket}/reply', [SupportWebController::class, 'reply'])->name('support.reply');

        // ── Generic Pages (via PageController) ──
        Route::get('/notifications', [PageController::class, 'notifications'])->name('notifications.index');
        Route::get('/reports', [PageController::class, 'reports'])->name('reports.index');
        Route::get('/reports/export/{type}', [PageController::class, 'reportsExport'])->name('reports.export');
        Route::get('/audit', [PageController::class, 'audit'])->name('audit.index');
        Route::get('/roles', [PageController::class, 'roles'])->name('roles.index');
        Route::get('/invitations', [PageController::class, 'invitations'])->name('invitations.index');
        Route::get('/addresses', [PageController::class, 'addresses'])->name('addresses.index');
        Route::get('/settings', [PageController::class, 'settings'])->name('settings.index');
        Route::get('/admin', [PageController::class, 'admin'])->name('admin.index');
        Route::get('/kyc', [PageController::class, 'kyc'])->name('kyc.index');
        Route::get('/pricing', [PageController::class, 'pricing'])->name('pricing.index');
        Route::post('/pricing', [PageController::class, 'pricingStore'])->name('pricing.store');
        Route::get('/tracking', [PageController::class, 'tracking'])->name('tracking.index');
        Route::get('/financial', [PageController::class, 'financial'])->name('financial.index');
        Route::get('/organizations', [PageController::class, 'organizations'])->name('organizations.index');
        Route::post('/organizations', [PageController::class, 'organizationsStore'])->name('organizations.store');
        Route::get('/risk', [PageController::class, 'risk'])->name('risk.index');
        Route::get('/dg', [PageController::class, 'dg'])->name('dg.index');

        // ── Phase 2 Modules ──
        Route::get('/containers', [PageController::class, 'containers'])->name('containers.index');
        Route::get('/customs', [PageController::class, 'customs'])->name('customs.index');
        Route::get('/drivers', [PageController::class, 'drivers'])->name('drivers.index');
        Route::get('/claims', [PageController::class, 'claims'])->name('claims.index');
        Route::get('/vessels', [PageController::class, 'vessels'])->name('vessels.index');
        Route::get('/schedules', [PageController::class, 'schedules'])->name('schedules.index');
        Route::get('/branches', [PageController::class, 'branches'])->name('branches.index');
        Route::get('/companies', [PageController::class, 'companies'])->name('companies.index');
        Route::get('/hscodes', [PageController::class, 'hscodes'])->name('hscodes.index');
    });
});
