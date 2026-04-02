<?php

use App\Http\Controllers\Web\AuthWebController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\InternalAccountManagementController;
use App\Http\Controllers\Web\InternalAccountMembersController;
use App\Http\Controllers\Web\InternalAccountReadCenterController;
use App\Http\Controllers\Web\InternalAccountSupportActionsController;
use App\Http\Controllers\Web\InternalAdminWebController;
use App\Http\Controllers\Web\InternalBillingReadCenterController;
use App\Http\Controllers\Web\InternalKycDecisionController;
use App\Http\Controllers\Web\InternalKycReadCenterController;
use App\Http\Controllers\Web\InternalKycRestrictionController;
use App\Http\Controllers\Web\InternalShipmentDocumentController;
use App\Http\Controllers\Web\InternalShipmentReadCenterController;
use App\Http\Controllers\Web\InternalStaffManagementController;
use App\Http\Controllers\Web\InternalStaffReadCenterController;
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
Route::get('/reset-password/{token}', [AuthWebController::class, 'showResetPassword'])->name('password.reset');
Route::post('/reset-password', [AuthWebController::class, 'resetPassword'])->name('password.update');
Route::post('/logout', [AuthWebController::class, 'logout'])->name('logout');

Route::middleware(['auth:web', 'userType:internal'])->prefix('internal')->name('internal.')->group(function (): void {
    Route::get('/', [InternalAdminWebController::class, 'home'])->name('home');

    Route::middleware([
        'permission:accounts.read',
        'internalSurface:' . InternalControlPlane::SURFACE_EXTERNAL_ACCOUNTS_INDEX,
    ])->group(function (): void {
        Route::get('/accounts', [InternalAccountReadCenterController::class, 'index'])->name('accounts.index');
    });

    Route::middleware([
        'permission:users.read',
        'internalSurface:' . InternalControlPlane::SURFACE_INTERNAL_STAFF_INDEX,
    ])->group(function (): void {
        Route::get('/staff', [InternalStaffReadCenterController::class, 'index'])->name('staff.index');
    });

    Route::middleware([
        'permission:kyc.read',
        'internalSurface:' . InternalControlPlane::SURFACE_INTERNAL_KYC_INDEX,
    ])->group(function (): void {
        Route::get('/kyc', [InternalKycReadCenterController::class, 'index'])->name('kyc.index');
    });

    Route::middleware([
        'permission:wallet.balance',
        'permission:wallet.ledger',
        'internalSurface:' . InternalControlPlane::SURFACE_INTERNAL_BILLING_INDEX,
    ])->group(function (): void {
        Route::get('/billing', [InternalBillingReadCenterController::class, 'index'])->name('billing.index');
    });

    Route::middleware([
        'permission:shipments.read',
        'internalSurface:' . InternalControlPlane::SURFACE_INTERNAL_SHIPMENTS_INDEX,
    ])->group(function (): void {
        Route::get('/shipments', [InternalShipmentReadCenterController::class, 'index'])->name('shipments.index');
    });

    Route::middleware([
        'permission:users.manage',
        'permission:roles.assign',
        'internalSurface:' . InternalControlPlane::SURFACE_INTERNAL_STAFF_CREATE,
    ])->group(function (): void {
        Route::get('/staff/create', [InternalStaffManagementController::class, 'create'])->name('staff.create');
        Route::post('/staff', [InternalStaffManagementController::class, 'store'])->name('staff.store');
    });

    Route::middleware([
        'permission:accounts.create',
        'internalSurface:' . InternalControlPlane::SURFACE_EXTERNAL_ACCOUNTS_CREATE,
    ])->group(function (): void {
        Route::get('/accounts/create', [InternalAccountManagementController::class, 'create'])->name('accounts.create');
        Route::post('/accounts', [InternalAccountManagementController::class, 'store'])->name('accounts.store');
    });

    Route::middleware([
        'permission:accounts.read',
        'internalSurface:' . InternalControlPlane::SURFACE_EXTERNAL_ACCOUNTS_DETAIL,
    ])->group(function (): void {
        Route::get('/accounts/{account}', [InternalAccountReadCenterController::class, 'show'])->name('accounts.show');
    });

    Route::middleware([
        'permission:users.read',
        'internalSurface:' . InternalControlPlane::SURFACE_INTERNAL_STAFF_DETAIL,
    ])->group(function (): void {
        Route::get('/staff/{user}', [InternalStaffReadCenterController::class, 'show'])->name('staff.show');
    });

    Route::middleware([
        'permission:kyc.read',
        'internalSurface:' . InternalControlPlane::SURFACE_INTERNAL_KYC_DETAIL,
    ])->group(function (): void {
        Route::get('/kyc/{account}', [InternalKycReadCenterController::class, 'show'])->name('kyc.show');
    });

    Route::middleware([
        'permission:wallet.balance',
        'permission:wallet.ledger',
        'internalSurface:' . InternalControlPlane::SURFACE_INTERNAL_BILLING_DETAIL,
    ])->group(function (): void {
        Route::get('/billing/{account}', [InternalBillingReadCenterController::class, 'show'])->name('billing.show');
        Route::get('/billing/{account}/preflights/{hold}', [InternalBillingReadCenterController::class, 'showPreflight'])->name('billing.preflights.show');
        Route::get('/billing/{account}/ledger/{entry}', [InternalBillingReadCenterController::class, 'showLedger'])->name('billing.ledger.show');
    });

    Route::middleware([
        'permission:shipments.read',
        'internalSurface:' . InternalControlPlane::SURFACE_INTERNAL_SHIPMENTS_DETAIL,
    ])->group(function (): void {
        Route::get('/shipments/{shipment}', [InternalShipmentReadCenterController::class, 'show'])->name('shipments.show');
    });

    Route::middleware([
        'permission:shipments.documents.read',
        'internalSurface:' . InternalControlPlane::SURFACE_INTERNAL_SHIPMENTS_DOCUMENTS,
    ])->group(function (): void {
        Route::get('/shipments/{shipment}/documents', [InternalShipmentDocumentController::class, 'index'])
            ->name('shipments.documents.index');
        Route::get('/shipments/{shipment}/documents/{document}/preview/{previewName?}', [InternalShipmentDocumentController::class, 'preview'])
            ->name('shipments.documents.preview');
        Route::get('/shipments/{shipment}/documents/{document}/{downloadName?}', [InternalShipmentDocumentController::class, 'download'])
            ->name('shipments.documents.download');
    });

    Route::middleware([
        'permission:kyc.manage',
        'internalSurface:' . InternalControlPlane::SURFACE_INTERNAL_KYC_REVIEW,
    ])->group(function (): void {
        Route::post('/kyc/{account}/approve', [InternalKycDecisionController::class, 'approve'])->name('kyc.approve');
        Route::post('/kyc/{account}/reject', [InternalKycDecisionController::class, 'reject'])->name('kyc.reject');
    });

    Route::middleware([
        'permission:kyc.manage',
        'internalSurface:' . InternalControlPlane::SURFACE_INTERNAL_KYC_RESTRICTIONS,
    ])->group(function (): void {
        Route::post('/kyc/{account}/restrictions/{feature}', [InternalKycRestrictionController::class, 'sync'])
            ->name('kyc.restrictions.sync');
    });

    Route::middleware([
        'permission:users.manage',
        'permission:roles.assign',
        'internalSurface:' . InternalControlPlane::SURFACE_INTERNAL_STAFF_UPDATE,
    ])->group(function (): void {
        Route::get('/staff/{user}/edit', [InternalStaffManagementController::class, 'edit'])->name('staff.edit');
        Route::put('/staff/{user}', [InternalStaffManagementController::class, 'update'])->name('staff.update');
    });

    Route::middleware([
        'permission:users.manage',
        'internalSurface:' . InternalControlPlane::SURFACE_INTERNAL_STAFF_LIFECYCLE,
    ])->group(function (): void {
        Route::post('/staff/{user}/activate', [InternalStaffManagementController::class, 'activate'])->name('staff.activate');
        Route::post('/staff/{user}/deactivate', [InternalStaffManagementController::class, 'deactivate'])->name('staff.deactivate');
        Route::post('/staff/{user}/suspend', [InternalStaffManagementController::class, 'suspend'])->name('staff.suspend');
        Route::post('/staff/{user}/unsuspend', [InternalStaffManagementController::class, 'unsuspend'])->name('staff.unsuspend');
    });

    Route::middleware([
        'permission:users.manage',
        'internalSurface:' . InternalControlPlane::SURFACE_INTERNAL_STAFF_SUPPORT_ACTIONS,
    ])->group(function (): void {
        Route::post('/staff/{user}/password-reset', [InternalStaffManagementController::class, 'passwordReset'])
            ->name('staff.password-reset');
    });

    Route::middleware([
        'permission:accounts.support.manage',
        'internalSurface:' . InternalControlPlane::SURFACE_EXTERNAL_ACCOUNTS_SUPPORT_ACTIONS,
    ])->group(function (): void {
        Route::post('/accounts/{account}/password-reset', [InternalAccountSupportActionsController::class, 'passwordReset'])
            ->name('accounts.password-reset');
        Route::post('/accounts/{account}/invitations/{invitation}/resend', [InternalAccountSupportActionsController::class, 'resendInvitation'])
            ->name('accounts.invitations.resend');
    });

    Route::middleware([
        'permission:accounts.members.manage',
        'internalSurface:' . InternalControlPlane::SURFACE_EXTERNAL_ACCOUNTS_MEMBER_ADMIN,
    ])->group(function (): void {
        Route::post('/accounts/{account}/members/invite', [InternalAccountMembersController::class, 'invite'])
            ->name('accounts.members.invite');
        Route::post('/accounts/{account}/members/{member}/deactivate', [InternalAccountMembersController::class, 'deactivate'])
            ->name('accounts.members.deactivate');
        Route::post('/accounts/{account}/members/{member}/reactivate', [InternalAccountMembersController::class, 'reactivate'])
            ->name('accounts.members.reactivate');
    });

    Route::middleware([
        'permission:accounts.update',
        'internalSurface:' . InternalControlPlane::SURFACE_EXTERNAL_ACCOUNTS_UPDATE,
    ])->group(function (): void {
        Route::get('/accounts/{account}/edit', [InternalAccountManagementController::class, 'edit'])->name('accounts.edit');
        Route::put('/accounts/{account}', [InternalAccountManagementController::class, 'update'])->name('accounts.update');
    });

    Route::middleware([
        'permission:accounts.lifecycle.manage',
        'internalSurface:' . InternalControlPlane::SURFACE_EXTERNAL_ACCOUNTS_LIFECYCLE,
    ])->group(function (): void {
        Route::post('/accounts/{account}/activate', [InternalAccountManagementController::class, 'activate'])->name('accounts.activate');
        Route::post('/accounts/{account}/deactivate', [InternalAccountManagementController::class, 'deactivate'])->name('accounts.deactivate');
        Route::post('/accounts/{account}/suspend', [InternalAccountManagementController::class, 'suspend'])->name('accounts.suspend');
        Route::post('/accounts/{account}/unsuspend', [InternalAccountManagementController::class, 'unsuspend'])->name('accounts.unsuspend');
    });

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
