<?php

use App\Http\Controllers\Web\AuthWebController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\InternalAdminWebController;
use App\Http\Controllers\Web\InternalSmtpSettingsController;
use App\Http\Controllers\Web\OrderWebController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\PublicTrackingPortalController;
use App\Http\Controllers\Web\ShipmentWebController;
use App\Http\Controllers\Web\StoreWebController;
use App\Http\Controllers\Web\SupportWebController;
use App\Http\Controllers\Web\UserWebController;
use App\Http\Controllers\Web\WalletWebController;
use App\Support\Internal\InternalControlPlane;
use Illuminate\Support\Facades\Route;

Route::get('/track/{token}', [PublicTrackingPortalController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('public.tracking.show');

Route::get('/login', [AuthWebController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthWebController::class, 'login']);
Route::get('/b2c/login', [AuthWebController::class, 'showB2cLogin'])->name('b2c.login');
Route::post('/b2c/login', [AuthWebController::class, 'loginB2c'])->name('b2c.login.submit');
Route::get('/b2b/login', [AuthWebController::class, 'showB2bLogin'])->name('b2b.login');
Route::post('/b2b/login', [AuthWebController::class, 'loginB2b'])->name('b2b.login.submit');
Route::get('/admin/login', [AuthWebController::class, 'showAdminLogin'])->name('admin.login');
Route::post('/admin/login', [AuthWebController::class, 'loginAdmin'])->name('admin.login.submit');
Route::post('/logout', [AuthWebController::class, 'logout'])->name('logout');

Route::middleware(['auth:web', 'userType:internal'])->prefix('internal')->name('internal.')->group(function (): void {
    Route::get('/', [InternalAdminWebController::class, 'home'])->name('home');

    Route::middleware([
        'permission:tenancy.context.select',
        'internalSurface:' . InternalControlPlane::SURFACE_TENANT_CONTEXT,
    ])->group(function (): void {
        Route::get('/tenant-context', [InternalAdminWebController::class, 'tenantContext'])->name('tenant-context');
        Route::post('/tenant-context', [InternalAdminWebController::class, 'storeTenantContext'])->name('tenant-context.store');
        Route::post('/tenant-context/clear', [InternalAdminWebController::class, 'clearTenantContext'])->name('tenant-context.clear');
    });

    Route::middleware([
        'permission:notifications.channels.manage',
        'internalSurface:' . InternalControlPlane::SURFACE_SMTP_SETTINGS,
    ])->group(function (): void {
        Route::get('/smtp-settings', [InternalSmtpSettingsController::class, 'edit'])->name('smtp-settings.edit');
        Route::put('/smtp-settings', [InternalSmtpSettingsController::class, 'update'])->name('smtp-settings.update');
        Route::post('/smtp-settings/test-connection', [InternalSmtpSettingsController::class, 'testConnection'])->name('smtp-settings.test-connection');
        Route::post('/smtp-settings/test-email', [InternalSmtpSettingsController::class, 'sendTestEmail'])->name('smtp-settings.test-email');
    });
});

Route::middleware(['auth:web', 'userType:external', 'tenant', 'legacyExternalSurface'])->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/shipments', [ShipmentWebController::class, 'index'])->name('shipments.index');
    Route::post('/shipments', [ShipmentWebController::class, 'store'])->name('shipments.store');
    Route::get('/shipments/export', [ShipmentWebController::class, 'export'])->name('shipments.export');
    Route::get('/shipments/{shipment}', [ShipmentWebController::class, 'show'])->name('shipments.show');
    Route::patch('/shipments/{shipment}/cancel', [ShipmentWebController::class, 'cancel'])->name('shipments.cancel');
    Route::post('/shipments/{shipment}/return', [ShipmentWebController::class, 'createReturn'])->name('shipments.return');
    Route::get('/shipments/{shipment}/label', [ShipmentWebController::class, 'label'])->name('shipments.label');

    Route::get('/orders', [OrderWebController::class, 'index'])->name('orders.index');
    Route::post('/orders', [OrderWebController::class, 'store'])->name('orders.store');
    Route::post('/orders/{order}/ship', [OrderWebController::class, 'ship'])->name('orders.ship');
    Route::patch('/orders/{order}/cancel', [OrderWebController::class, 'cancel'])->name('orders.cancel');

    Route::get('/stores', [StoreWebController::class, 'index'])->name('stores.index');
    Route::post('/stores', [StoreWebController::class, 'store'])->name('stores.store');
    Route::post('/stores/{store}/sync', [StoreWebController::class, 'sync'])->name('stores.sync');
    Route::post('/stores/{store}/test', [StoreWebController::class, 'test'])->name('stores.test');
    Route::delete('/stores/{store}', [StoreWebController::class, 'destroy'])->name('stores.destroy');

    Route::get('/wallet', [WalletWebController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/topup', [WalletWebController::class, 'topup'])->name('wallet.topup');
    Route::post('/wallet/hold', [WalletWebController::class, 'hold'])->name('wallet.hold');

    Route::get('/users', [UserWebController::class, 'index'])->name('users.index');
    Route::post('/users', [UserWebController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}/toggle', [UserWebController::class, 'toggle'])->name('users.toggle');
    Route::delete('/users/{user}', [UserWebController::class, 'destroy'])->name('users.destroy');

    Route::get('/support', [SupportWebController::class, 'index'])->name('support.index');
    Route::post('/support', [SupportWebController::class, 'store'])->name('support.store');
    Route::get('/support/{ticket}', [SupportWebController::class, 'show'])->name('support.show');
    Route::post('/support/{ticket}/reply', [SupportWebController::class, 'reply'])->name('support.reply');
    Route::patch('/support/{ticket}/resolve', [SupportWebController::class, 'resolve'])->name('support.resolve');

    Route::get('/roles', [PageController::class, 'roles'])->name('roles.index');
    Route::post('/roles', [PageController::class, 'rolesStore'])->name('roles.store');

    Route::get('/invitations', [PageController::class, 'invitations'])->name('invitations.index');
    Route::post('/invitations', [PageController::class, 'invitationsStore'])->name('invitations.store');

    Route::get('/notifications', [PageController::class, 'notifications'])->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [PageController::class, 'notificationsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [PageController::class, 'notificationsReadAll'])->name('notifications.readAll');

    Route::get('/addresses', [PageController::class, 'addresses'])->name('addresses.index');
    Route::post('/addresses', [PageController::class, 'addressesStore'])->name('addresses.store');
    Route::patch('/addresses/{address}/default', [PageController::class, 'addressesDefault'])->name('addresses.default');
    Route::delete('/addresses/{address}', [PageController::class, 'addressesDestroy'])->name('addresses.destroy');

    Route::get('/settings', [PageController::class, 'settings'])->name('settings.index');
    Route::put('/settings', [PageController::class, 'settingsUpdate'])->name('settings.update');

    Route::get('/audit', [PageController::class, 'audit'])->name('audit.index');
    Route::get('/audit/export', [PageController::class, 'auditExport'])->name('audit.export');

    Route::get('/reports', [PageController::class, 'reports'])->name('reports.index');
    Route::get('/reports/export/{type}', [PageController::class, 'reportsExport'])
        ->name('reports.export')
        ->where('type', 'shipments|revenue|carriers|stores|operations|financial');

    Route::get('/kyc', [PageController::class, 'kyc'])->name('kyc.index');
    Route::get('/pricing', [PageController::class, 'pricing'])->name('pricing.index');
    Route::post('/pricing', [PageController::class, 'pricingStore'])->name('pricing.store');
    Route::get('/tracking', [PageController::class, 'tracking'])->name('tracking.index');
    Route::get('/financial', [PageController::class, 'financial'])->name('financial.index');
    Route::get('/organizations', [PageController::class, 'organizations'])->name('organizations.index');
    Route::post('/organizations', [PageController::class, 'organizationsStore'])->name('organizations.store');
    Route::get('/risk', [PageController::class, 'risk'])->name('risk.index');
    Route::get('/dg', [PageController::class, 'dg'])->name('dg.index');

    Route::get('/containers', [PageController::class, 'containers'])->name('containers.index');
    Route::post('/containers', [PageController::class, 'containersStore'])->name('containers.store');
    Route::get('/customs', [PageController::class, 'customs'])->name('customs.index');
    Route::post('/customs', [PageController::class, 'customsStore'])->name('customs.store');
    Route::get('/drivers', [PageController::class, 'drivers'])->name('drivers.index');
    Route::get('/claims', [PageController::class, 'claims'])->name('claims.index');
    Route::post('/claims', [PageController::class, 'claimsStore'])->name('claims.store');
    Route::get('/vessels', [PageController::class, 'vessels'])->name('vessels.index');
    Route::post('/vessels', [PageController::class, 'vesselsStore'])->name('vessels.store');
    Route::get('/schedules', [PageController::class, 'schedules'])->name('schedules.index');
    Route::get('/branches', [PageController::class, 'branches'])->name('branches.index');
    Route::get('/companies', [PageController::class, 'companies'])->name('companies.index');
    Route::get('/hscodes', [PageController::class, 'hscodes'])->name('hscodes.index');
});

Route::prefix('admin')
    ->name('admin.')
    ->middleware([
        'auth:web',
        'userType:internal',
        'permission:admin.access',
        'internalSurface:' . InternalControlPlane::SURFACE_ADMIN_DASHBOARD,
    ])
    ->group(function (): void {
        Route::get('/', [InternalAdminWebController::class, 'index'])->name('index');

        Route::middleware([
            'permission:tenancy.context.select',
            'internalSurface:' . InternalControlPlane::SURFACE_TENANT_CONTEXT,
        ])->group(function (): void {
            Route::get('/tenant-context', [InternalAdminWebController::class, 'tenantContext'])->name('tenant-context');
            Route::post('/tenant-context', [InternalAdminWebController::class, 'storeTenantContext'])->name('tenant-context.store');
            Route::post('/tenant-context/clear', [InternalAdminWebController::class, 'clearTenantContext'])->name('tenant-context.clear');
        });

        Route::middleware('tenant')->group(function (): void {
            Route::get('/users', [InternalAdminWebController::class, 'users'])
                ->middleware([
                    'permission:users.read',
                    'internalSurface:' . InternalControlPlane::SURFACE_ACCOUNT_USERS,
                ])
                ->name('users');
            Route::get('/roles', [InternalAdminWebController::class, 'roles'])
                ->middleware([
                    'permission:roles.read',
                    'internalSurface:' . InternalControlPlane::SURFACE_ACCOUNT_ROLES,
                ])
                ->name('roles');
            Route::get('/reports', [InternalAdminWebController::class, 'reports'])
                ->middleware([
                    'permission:reports.read',
                    'internalSurface:' . InternalControlPlane::SURFACE_ACCOUNT_REPORTS,
                ])
                ->name('reports');
        });
    });
