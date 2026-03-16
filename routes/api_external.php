<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AccountTypeController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\FinancialDataController;
use App\Http\Controllers\Api\V1\KycController;
use App\Http\Controllers\Api\V1\AccountSettingsController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\WalletBillingController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\ShipmentController;
use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\RateController;
use App\Http\Controllers\Api\V1\CarrierController;
use App\Http\Controllers\Api\V1\TrackingController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\PricingController;
use App\Http\Controllers\Api\V1\KycComplianceController;
use App\Http\Controllers\Api\V1\BillingWalletController;
use App\Http\Controllers\Api\V1\DgComplianceController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\VesselScheduleController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\ShipmentWorkflowController;
use App\Http\Controllers\Api\V1\LastMileDeliveryController;
use App\Http\Controllers\Api\V1\InsuranceController;
use App\Http\Controllers\Api\V1\SLAController;
use App\Http\Controllers\Api\V1\SupportTicketController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\IntegrationController;
use App\Http\Controllers\Api\V1\ContentDeclarationController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\CustomsController;
use App\Http\Controllers\Api\V1\ContainerController;
use App\Http\Controllers\Api\V1\ClaimController;
use App\Http\Controllers\Api\V1\RiskController;
use App\Http\Controllers\Api\V1\DriverController;
use App\Http\Controllers\Api\V1\IncotermController;
use App\Http\Controllers\Api\V1\HsCodeController;
use App\Http\Controllers\Api\V1\TariffController;

// External API routes (Phase 2A split).
Route::prefix('v1')->middleware(['auth:sanctum', 'userType:external', 'tenantContext'])->group(function () {

    // ── Authentication (authenticated) ───────────────────────────
    Route::post('/logout', [AuthController::class, 'logout'])
         ->middleware('permission:account.manage')
         ->name('api.v1.logout');

    Route::post('/logout-all', [AuthController::class, 'logoutAll'])
         ->middleware('permission:account.manage')
         ->name('api.v1.logout-all');

    Route::get('/me', [AuthController::class, 'me'])
         ->middleware('permission:account.read')
         ->name('api.v1.me');

    Route::put('/change-password', [AuthController::class, 'changePassword'])
         ->middleware('permission:account.manage')
         ->name('api.v1.change-password');

    // ── FR-IAM-001: Account ───────────────────────────────────────
    Route::get('/account', [AccountController::class, 'show'])
         ->middleware('permission:account.read')
         ->name('api.v1.account.show');

    // ── FR-IAM-008: Account Settings ─────────────────────────────
    Route::get('/account/settings', [AccountSettingsController::class, 'show'])
         ->middleware('permission:account.read')
         ->name('api.v1.account.settings');

    Route::put('/account/settings', [AccountSettingsController::class, 'update'])
         ->middleware('permission:account.manage')
         ->name('api.v1.account.settings.update');

    Route::post('/account/settings/reset', [AccountSettingsController::class, 'reset'])
         ->middleware('permission:account.manage')
         ->name('api.v1.account.settings.reset');

    Route::get('/account/settings/options', [AccountSettingsController::class, 'options'])
         ->middleware('permission:account.read')
         ->name('api.v1.account.settings.options');

    // ── FR-IAM-010: Account Type, Organization Profile, KYC ──────
    Route::get('/account/type', [AccountTypeController::class, 'show'])
         ->middleware('permission:account.read')
         ->name('api.v1.account.type');

    Route::post('/account/type-change', [AccountTypeController::class, 'requestTypeChange'])
         ->middleware('permission:account.manage')
         ->name('api.v1.account.type-change');

    Route::get('/account/organization', [AccountTypeController::class, 'organizationProfile'])
         ->middleware('permission:account.read')
         ->name('api.v1.account.organization');

    Route::put('/account/organization', [AccountTypeController::class, 'updateOrganizationProfile'])
         ->middleware('permission:account.manage')
         ->name('api.v1.account.organization.update');

    Route::get('/account/kyc', [AccountTypeController::class, 'kycStatus'])
         ->middleware('permission:account.read')
         ->name('api.v1.account.kyc');

    Route::post('/account/kyc/submit', [AccountTypeController::class, 'submitKyc'])
         ->middleware('permission:account.manage')
         ->name('api.v1.account.kyc.submit');

    // ── FR-IAM-002: User Management ──────────────────────────────
    Route::get('/users/changelog', [UserController::class, 'changelog'])
         ->middleware('permission:users.read')
         ->name('api.v1.users.changelog');

    Route::apiResource('users', UserController::class)
         ->middleware('permission:users.read', ['only' => ['index', 'show']])
         ->middleware('permission:users.manage', ['except' => ['index', 'show']])
         ->names('api.v1.users');

    Route::patch('/users/{id}/disable', [UserController::class, 'disable'])
         ->middleware('permission:users.manage')
         ->name('api.v1.users.disable');

    Route::patch('/users/{id}/enable', [UserController::class, 'enable'])
         ->middleware('permission:users.manage')
         ->name('api.v1.users.enable');

    // ── FR-IAM-003: RBAC — Roles & Permissions ───────────────────
    Route::get('/permissions', [RoleController::class, 'permissionsCatalog'])
         ->middleware('permission:roles.read')
         ->name('api.v1.permissions.catalog');

    Route::get('/roles/templates', [RoleController::class, 'templates'])
         ->middleware('permission:roles.read')
         ->name('api.v1.roles.templates');

    Route::post('/roles/from-template', [RoleController::class, 'createFromTemplate'])
         ->middleware('permission:roles.manage')
         ->name('api.v1.roles.from-template');

    Route::apiResource('roles', RoleController::class)
         ->middleware('permission:roles.read', ['only' => ['index', 'show']])
         ->middleware('permission:roles.manage', ['except' => ['index', 'show']])
         ->names('api.v1.roles');

    Route::post('/roles/{roleId}/assign/{userId}', [RoleController::class, 'assignToUser'])
         ->middleware('permission:roles.assign')
         ->name('api.v1.roles.assign');

    Route::delete('/roles/{roleId}/revoke/{userId}', [RoleController::class, 'revokeFromUser'])
         ->middleware('permission:roles.assign')
         ->name('api.v1.roles.revoke');

    Route::get('/users/{id}/permissions', [RoleController::class, 'userPermissions'])
         ->middleware('permission:users.read')
         ->name('api.v1.users.permissions');

    // ── FR-IAM-011: Invitation Management (authenticated) ────────
    Route::get('/invitations', [InvitationController::class, 'index'])
         ->middleware('permission:users.read')
         ->name('api.v1.invitations.index');

    Route::post('/invitations', [InvitationController::class, 'store'])
         ->middleware('permission:users.invite')
         ->name('api.v1.invitations.store');

    Route::get('/invitations/{invitationId}', [InvitationController::class, 'show'])
         ->middleware('permission:users.read')
         ->name('api.v1.invitations.show');

    Route::patch('/invitations/{invitationId}/cancel', [InvitationController::class, 'cancel'])
         ->middleware('permission:users.invite')
         ->name('api.v1.invitations.cancel');

    Route::post('/invitations/{invitationId}/resend', [InvitationController::class, 'resend'])
         ->middleware('permission:users.invite')
         ->name('api.v1.invitations.resend');

    // ── FR-IAM-009: Multi-Store Management ──────────────────────
    Route::get('/stores/stats', [StoreController::class, 'stats'])
         ->middleware('permission:stores.read')
         ->name('api.v1.stores.stats');

    Route::get('/stores', [StoreController::class, 'index'])
         ->middleware('permission:stores.read')
         ->name('api.v1.stores.index');

    Route::post('/stores', [StoreController::class, 'store'])
         ->middleware('permission:stores.manage')
         ->name('api.v1.stores.store');

    Route::get('/stores/{storeId}', [StoreController::class, 'show'])
         ->middleware('permission:stores.read')
         ->name('api.v1.stores.show');

    Route::put('/stores/{storeId}', [StoreController::class, 'update'])
         ->middleware('permission:stores.manage')
         ->name('api.v1.stores.update');

    Route::delete('/stores/{storeId}', [StoreController::class, 'destroy'])
         ->middleware('permission:stores.manage')
         ->name('api.v1.stores.destroy');

    Route::post('/stores/{storeId}/set-default', [StoreController::class, 'setDefault'])
         ->middleware('permission:stores.manage')
         ->name('api.v1.stores.set-default');

    Route::post('/stores/{storeId}/toggle-status', [StoreController::class, 'toggleStatus'])
         ->middleware('permission:stores.manage')
         ->name('api.v1.stores.toggle-status');

    // ── ST Module: Store Connections & Sync ───────────────────────
    Route::post('/stores/{storeId}/test-connection', [OrderController::class, 'testConnection'])
         ->middleware('permission:stores.manage')
         ->name('api.v1.stores.test-connection');

    Route::post('/stores/{storeId}/register-webhooks', [OrderController::class, 'registerWebhooks'])
         ->middleware('permission:webhooks.manage')
         ->name('api.v1.stores.register-webhooks');

    Route::post('/stores/{storeId}/sync', [OrderController::class, 'syncStore'])
         ->middleware('permission:stores.manage')
         ->name('api.v1.stores.sync');

    // ── ST Module: Orders ─────────────────────────────────────────
    Route::get('/orders', [OrderController::class, 'index'])
         ->middleware('permission:orders.read')
         ->name('api.v1.orders.index');

    Route::get('/orders/stats', [OrderController::class, 'stats'])
         ->middleware('permission:orders.read')
         ->name('api.v1.orders.stats');

    Route::get('/orders/{orderId}', [OrderController::class, 'show'])
         ->middleware('permission:orders.read')
         ->name('api.v1.orders.show');

    Route::post('/orders', [OrderController::class, 'store'])
         ->middleware('permission:orders.manage')
         ->name('api.v1.orders.store');

    Route::put('/orders/{orderId}/status', [OrderController::class, 'updateStatus'])
         ->middleware('permission:orders.manage')
         ->name('api.v1.orders.update-status');

    Route::post('/orders/{orderId}/cancel', [OrderController::class, 'cancel'])
         ->middleware('permission:orders.manage')
         ->name('api.v1.orders.cancel');

    // ── FR-IAM-014 + FR-IAM-016: KYC Status & Documents ─────────
    Route::get('/kyc/status', [KycController::class, 'status'])
         ->middleware('permission:kyc.read')
         ->name('api.v1.kyc.status');

    Route::post('/kyc/approve', [KycController::class, 'approve'])
         ->middleware('permission:kyc.manage')
         ->name('api.v1.kyc.approve');

    Route::post('/kyc/reject', [KycController::class, 'reject'])
         ->middleware('permission:kyc.manage')
         ->name('api.v1.kyc.reject');

    Route::post('/kyc/resubmit', [KycController::class, 'resubmit'])
         ->middleware('permission:kyc.manage')
         ->name('api.v1.kyc.resubmit');

    Route::get('/kyc/documents', [KycController::class, 'listDocuments'])
         ->middleware('permission:kyc.documents.read')
         ->name('api.v1.kyc.documents');

    Route::post('/kyc/documents/upload', [KycController::class, 'uploadDocument'])
         ->middleware('permission:kyc.documents.manage')
         ->name('api.v1.kyc.documents.upload');

    Route::get('/kyc/documents/{id}/download', [KycController::class, 'downloadDocument'])
         ->middleware('permission:kyc.documents.read')
         ->name('api.v1.kyc.documents.download');

    Route::delete('/kyc/documents/{id}', [KycController::class, 'purgeDocument'])
         ->middleware('permission:kyc.documents.manage')
         ->name('api.v1.kyc.documents.purge');

    // ── FR-IAM-012: Financial Data Masking ───────────────────────
    Route::get('/financial/visibility', [FinancialDataController::class, 'visibility'])
         ->middleware('permission:financial.read')
         ->name('api.v1.financial.visibility');

    Route::get('/financial/sensitive-fields', [FinancialDataController::class, 'sensitiveFields'])
         ->middleware('permission:financial.read')
         ->name('api.v1.financial.sensitive-fields');

    Route::post('/financial/mask-card', [FinancialDataController::class, 'maskCard'])
         ->middleware('permission:financial.manage')
         ->name('api.v1.financial.mask-card');

    Route::post('/financial/filter', [FinancialDataController::class, 'filterData'])
         ->middleware('permission:financial.manage')
         ->name('api.v1.financial.filter');

    // ── FR-IAM-017+019+020: Wallet & Billing ─────────────────────
    Route::get('/wallet', [WalletBillingController::class, 'wallet'])
         ->middleware('permission:wallet.balance')
         ->name('api.v1.wallet');

    Route::get('/wallet/ledger', [WalletBillingController::class, 'ledger'])
         ->middleware('permission:wallet.ledger')
         ->name('api.v1.wallet.ledger');

    Route::post('/wallet/topup', [WalletBillingController::class, 'topUp'])
         ->middleware('permission:wallet.topup')
         ->name('api.v1.wallet.topup');

    Route::put('/wallet/threshold', [WalletBillingController::class, 'configureThreshold'])
         ->middleware('permission:wallet.configure')
         ->name('api.v1.wallet.threshold');

    Route::get('/wallet/permissions', [WalletBillingController::class, 'permissions'])
         ->middleware('permission:wallet.manage')
         ->name('api.v1.wallet.permissions');

    Route::get('/billing/methods', [WalletBillingController::class, 'paymentMethods'])
         ->middleware('permission:billing.view')
         ->name('api.v1.billing.methods');

    Route::post('/billing/methods', [WalletBillingController::class, 'addPaymentMethod'])
         ->middleware('permission:billing.manage')
         ->name('api.v1.billing.methods.add');

    Route::delete('/billing/methods/{id}', [WalletBillingController::class, 'removePaymentMethod'])
         ->middleware('permission:billing.manage')
         ->name('api.v1.billing.methods.remove');

    // ── SH Module: Shipments (FR-SH-001→019) ─────────────────────
    Route::get('/shipments', [ShipmentController::class, 'index'])
         ->middleware('permission:shipments.read')
         ->name('api.v1.shipments.index');

    Route::get('/shipments/stats', [ShipmentController::class, 'stats'])
         ->middleware('permission:shipments.read')
         ->name('api.v1.shipments.stats');

    Route::get('/shipments/{shipmentId}', [ShipmentController::class, 'show'])
         ->middleware('permission:shipments.read')
         ->name('api.v1.shipments.show');

    Route::post('/shipments', [ShipmentController::class, 'store'])
         ->middleware('permission:shipments.create')
         ->name('api.v1.shipments.store');

    Route::post('/shipments/from-order/{orderId}', [ShipmentController::class, 'createFromOrder'])
         ->middleware('permission:shipments.create')
         ->name('api.v1.shipments.from-order');

    Route::post('/shipments/bulk', [ShipmentController::class, 'bulkCreate'])
         ->middleware('permission:shipments.manage')
         ->name('api.v1.shipments.bulk');

    Route::post('/shipments/{shipmentId}/validate', [ShipmentController::class, 'validate'])
         ->middleware('permission:shipments.update_draft')
         ->name('api.v1.shipments.validate');

    Route::post('/shipments/{shipmentId}/wallet-preflight', [ShipmentController::class, 'walletPreflight'])
         ->middleware('permission:billing.manage')
         ->name('api.v1.shipments.wallet-preflight');

    Route::put('/shipments/{shipmentId}/status', [ShipmentController::class, 'updateStatus'])
         ->middleware('permission:shipments.manage')
         ->name('api.v1.shipments.update-status');

    Route::post('/shipments/{shipmentId}/cancel', [ShipmentController::class, 'cancel'])
         ->middleware('permission:shipments.manage')
         ->name('api.v1.shipments.cancel');

    Route::get('/shipments/{shipmentId}/label', [ShipmentController::class, 'label'])
         ->middleware('permission:shipments.print_label')
         ->name('api.v1.shipments.label');

    Route::post('/shipments/{shipmentId}/return', [ShipmentController::class, 'createReturn'])
         ->middleware('permission:shipments.manage')
         ->name('api.v1.shipments.return');

    Route::post('/shipments/{shipmentId}/parcels', [ShipmentController::class, 'addParcel'])
         ->middleware('permission:shipments.update_draft')
         ->name('api.v1.shipments.parcels.add');

    Route::delete('/shipments/{shipmentId}/parcels/{parcelId}', [ShipmentController::class, 'removeParcel'])
         ->middleware('permission:shipments.update_draft')
         ->name('api.v1.shipments.parcels.remove');

    // ── SH Module: Address Book (FR-SH-004) ───────────────────────
    Route::get('/addresses', [AddressController::class, 'index'])
         ->middleware('permission:addresses.read')
         ->name('api.v1.addresses.index');

    Route::post('/addresses', [AddressController::class, 'store'])
         ->middleware('permission:addresses.manage')
         ->name('api.v1.addresses.store');

    Route::delete('/addresses/{id}', [AddressController::class, 'destroy'])
         ->middleware('permission:addresses.manage')
         ->name('api.v1.addresses.destroy');

    // ── RT Module: Rates & Pricing (FR-RT-001→012) ────────────────
    Route::post('/shipments/{shipmentId}/rates', [RateController::class, 'fetchRates'])
         ->middleware('permission:rates.read')
         ->name('api.v1.rates.fetch');

    Route::post('/shipments/{shipmentId}/reprice', [RateController::class, 'reprice'])
         ->middleware('permission:rates.read')
         ->name('api.v1.rates.reprice');

    Route::get('/shipments/{shipmentId}/offers', [RateController::class, 'shipmentOffers'])
         ->middleware('permission:quotes.read')
         ->name('api.v1.shipments.offers');

    Route::get('/rate-quotes/{quoteId}', [RateController::class, 'showQuote'])
         ->middleware('permission:quotes.read')
         ->name('api.v1.rate-quotes.show');

    Route::post('/rate-quotes/{quoteId}/select', [RateController::class, 'selectOption'])
         ->middleware('permission:quotes.manage')
         ->name('api.v1.rate-quotes.select');

    Route::get('/pricing-rules', [RateController::class, 'listRules'])
         ->middleware('permission:rates.read')
         ->name('api.v1.pricing-rules.index');

    Route::post('/pricing-rules', [RateController::class, 'createRule'])
         ->middleware('permission:pricing_rules.manage')
         ->name('api.v1.pricing-rules.store');

    Route::put('/pricing-rules/{id}', [RateController::class, 'updateRule'])
         ->middleware('permission:pricing_rules.manage')
         ->name('api.v1.pricing-rules.update');

    Route::delete('/pricing-rules/{id}', [RateController::class, 'deleteRule'])
         ->middleware('permission:pricing_rules.manage')
         ->name('api.v1.pricing-rules.destroy');

    // ── FR-IAM-006 + FR-IAM-013: Audit Log ──────────────────────
    Route::get('/audit-logs/categories', [AuditLogController::class, 'categories'])
         ->middleware('permission:audit.view')
         ->name('api.v1.audit-logs.categories');

    Route::get('/audit-logs/statistics', [AuditLogController::class, 'statistics'])
         ->middleware('permission:audit.view')
         ->name('api.v1.audit-logs.statistics');

    Route::post('/audit-logs/export', [AuditLogController::class, 'export'])
         ->middleware('permission:audit.export')
         ->name('api.v1.audit-logs.export');

    Route::get('/audit-logs/entity/{entityType}/{entityId}', [AuditLogController::class, 'entityTrail'])
         ->middleware('permission:audit.view')
         ->name('api.v1.audit-logs.entity-trail');

    Route::get('/audit-logs/trace/{requestId}', [AuditLogController::class, 'requestTrace'])
         ->middleware('permission:audit.view')
         ->name('api.v1.audit-logs.trace');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])
         ->middleware('permission:audit.view')
         ->name('api.v1.audit-logs.index');

    Route::get('/audit-logs/{id}', [AuditLogController::class, 'show'])
         ->middleware('permission:audit.view')
         ->name('api.v1.audit-logs.show');

    // ═══════════════════════════════════════════════════════════════
    // CR Module — Carrier Integration & Labels (FR-CR-001→008)
    // ═══════════════════════════════════════════════════════════════

    // FR-CR-001: Create shipment at carrier
    Route::post('/shipments/{shipmentId}/carrier/create', [CarrierController::class, 'createAtCarrier'])
         ->middleware('permission:shipments.manage')
         ->name('api.v1.carrier.create');

    // FR-CR-005: Re-fetch label
    Route::post('/shipments/{shipmentId}/carrier/refetch', [CarrierController::class, 'refetchLabel'])
         ->middleware('permission:shipments.manage')
         ->name('api.v1.carrier.refetch-label');

    // FR-CR-006: Cancel at carrier
    Route::post('/shipments/{shipmentId}/carrier/cancel', [CarrierController::class, 'cancelAtCarrier'])
         ->middleware('permission:shipments.manage')
         ->name('api.v1.carrier.cancel');

    // FR-CR-003: Retry failed creation
    Route::post('/shipments/{shipmentId}/carrier/retry', [CarrierController::class, 'retryCreation'])
         ->middleware('permission:shipments.manage')
         ->name('api.v1.carrier.retry');

    // Carrier status
    Route::get('/shipments/{shipmentId}/carrier/status', [CarrierController::class, 'carrierStatus'])
         ->middleware('permission:shipments.read')
         ->name('api.v1.carrier.status');

    // FR-CR-004: Carrier errors
    Route::get('/shipments/{shipmentId}/carrier/errors', [CarrierController::class, 'carrierErrors'])
         ->middleware('permission:shipments.read')
         ->name('api.v1.carrier.errors');

    // FR-CR-008: Documents (list & download)
    Route::get('/shipments/{shipmentId}/documents', [CarrierController::class, 'listDocuments'])
         ->middleware('permission:shipments.read')
         ->name('api.v1.carrier.documents');

    Route::get('/shipments/{shipmentId}/documents/{documentId}', [CarrierController::class, 'downloadDocument'])
         ->middleware('permission:shipments.read')
         ->name('api.v1.carrier.document-download');

    // ═══════════════════════════════════════════════════════════════
    // TR Module — Tracking & Status Normalization (FR-TR-001→007)
    // ═══════════════════════════════════════════════════════════════

    // FR-TR-005: Tracking timeline
    Route::get('/shipments/{shipmentId}/tracking/timeline', [TrackingController::class, 'timeline'])
         ->middleware('permission:tracking.read')
         ->name('api.v1.tracking.timeline');

    Route::get('/shipments/{shipmentId}/tracking/events', [TrackingController::class, 'events'])
         ->middleware('permission:tracking.read')
         ->name('api.v1.tracking.events');

    Route::get('/shipments/{shipmentId}/tracking/status', [TrackingController::class, 'status'])
         ->middleware('permission:tracking.read')
         ->name('api.v1.tracking.status');

    // FR-TR-005: Search/filter by status
    Route::get('/tracking/search', [TrackingController::class, 'search'])
         ->middleware('permission:tracking.read')
         ->name('api.v1.tracking.search');

    // FR-TR-006: Status dashboard
    Route::get('/tracking/dashboard', [TrackingController::class, 'dashboard'])
         ->middleware('permission:tracking.read')
         ->name('api.v1.tracking.dashboard');

    // FR-TR-004: Subscribe to tracking updates
    Route::post('/shipments/{shipmentId}/tracking/subscribe', [TrackingController::class, 'subscribe'])
         ->middleware('permission:tracking.manage')
         ->name('api.v1.tracking.subscribe');

    // FR-TR-004: Unsubscribe
    Route::delete('/tracking/subscriptions/{subscriptionId}', [TrackingController::class, 'unsubscribe'])
         ->middleware('permission:tracking.manage')
         ->name('api.v1.tracking.unsubscribe');

    // FR-TR-004: Status mappings
    Route::get('/tracking/status-mappings', [TrackingController::class, 'statusMappings'])
         ->middleware('permission:tracking.read')
         ->name('api.v1.tracking.status-mappings');

    // FR-TR-001: Manual poll
    Route::post('/tracking/poll/{trackingNumber}', [TrackingController::class, 'manualPoll'])
         ->middleware('permission:tracking.manage')
         ->name('api.v1.tracking.manual-poll');

    // FR-TR-007: Exceptions
    Route::get('/shipments/{shipmentId}/exceptions', [TrackingController::class, 'exceptions'])
         ->middleware('permission:tracking.read')
         ->name('api.v1.tracking.exceptions');

    Route::post('/exceptions/{exceptionId}/acknowledge', [TrackingController::class, 'acknowledgeException'])
         ->middleware('permission:tracking.manage')
         ->name('api.v1.tracking.exception-acknowledge');

    Route::post('/exceptions/{exceptionId}/resolve', [TrackingController::class, 'resolveException'])
         ->middleware('permission:tracking.manage')
         ->name('api.v1.tracking.exception-resolve');

    Route::post('/exceptions/{exceptionId}/escalate', [TrackingController::class, 'escalateException'])
         ->middleware('permission:tracking.manage')
         ->name('api.v1.tracking.exception-escalate');

    // ═══════════════════════════════════════════════════════════════
    // NTF Module — Notifications (FR-NTF-001→009)
    // ═══════════════════════════════════════════════════════════════

    // FR-NTF-008: Notification log
    Route::get('/notifications', [NotificationController::class, 'index'])
         ->middleware('permission:notifications.read')
         ->name('api.v1.notifications.index');

    // FR-NTF-001: In-app notifications
    Route::get('/notifications/in-app', [NotificationController::class, 'inApp'])
         ->middleware('permission:notifications.read')
         ->name('api.v1.notifications.in-app');

    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])
         ->middleware('permission:notifications.read')
         ->name('api.v1.notifications.unread-count');

    Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'markRead'])
         ->middleware('permission:notifications.manage')
         ->name('api.v1.notifications.mark-read');

    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
         ->middleware('permission:notifications.manage')
         ->name('api.v1.notifications.read-all');

    // FR-NTF-003: Preferences
    Route::get('/notifications/preferences', [NotificationController::class, 'getPreferences'])
         ->middleware('permission:notifications.read')
         ->name('api.v1.notifications.preferences');

    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences'])
         ->middleware('permission:notifications.manage')
         ->name('api.v1.notifications.update-preferences');

    // FR-NTF-004: Templates
    Route::get('/notifications/templates', [NotificationController::class, 'listTemplates'])
         ->middleware('permission:notifications.templates.manage')
         ->name('api.v1.notifications.templates');

    Route::post('/notifications/templates', [NotificationController::class, 'createTemplate'])
         ->middleware('permission:notifications.templates.manage')
         ->name('api.v1.notifications.create-template');

    Route::put('/notifications/templates/{templateId}', [NotificationController::class, 'updateTemplate'])
         ->middleware('permission:notifications.templates.manage')
         ->name('api.v1.notifications.update-template');

    Route::post('/notifications/templates/{templateId}/preview', [NotificationController::class, 'previewTemplate'])
         ->middleware('permission:notifications.templates.manage')
         ->name('api.v1.notifications.preview-template');

    // FR-NTF-009: Channels
    Route::get('/notifications/channels', [NotificationController::class, 'listChannels'])
         ->middleware('permission:notifications.channels.manage')
         ->name('api.v1.notifications.channels');

    Route::post('/notifications/channels', [NotificationController::class, 'configureChannel'])
         ->middleware('permission:notifications.channels.manage')
         ->name('api.v1.notifications.configure-channel');

    // FR-NTF-002: Test send
    Route::post('/notifications/test', [NotificationController::class, 'testSend'])
         ->middleware('permission:notifications.manage')
         ->name('api.v1.notifications.test');

    // FR-NTF-007: Schedules
    Route::post('/notifications/schedules', [NotificationController::class, 'createSchedule'])
         ->middleware('permission:notifications.schedules.manage')
         ->name('api.v1.notifications.create-schedule');

    Route::get('/notifications/schedules', [NotificationController::class, 'listSchedules'])
         ->middleware('permission:notifications.schedules.manage')
         ->name('api.v1.notifications.schedules');

    // ═══════════════════════════════════════════════════════════════
    // PAY Module — Payments & Subscriptions (FR-PAY-001→011)
    // ═══════════════════════════════════════════════════════════════

    // FR-PAY-001/004: Wallet top-up
    Route::post('/payments/topup', [PaymentController::class, 'topUp'])
         ->middleware('permission:payments.manage')
         ->name('api.v1.payments.topup');

    // FR-PAY-001/002: Charge shipping
    Route::post('/payments/charge-shipping', [PaymentController::class, 'chargeShipping'])
         ->middleware('permission:payments.manage')
         ->name('api.v1.payments.charge-shipping');

    // FR-PAY-008: Wallet & transactions
    Route::get('/payments/wallet', [PaymentController::class, 'walletSummary'])
         ->middleware('permission:payments.read')
         ->name('api.v1.payments.wallet');

    Route::get('/payments/transactions', [PaymentController::class, 'transactions'])
         ->middleware('permission:payments.read')
         ->name('api.v1.payments.transactions');

    // FR-PAY-010: Refund
    Route::post('/payments/refund', [PaymentController::class, 'refund'])
         ->middleware('permission:payments.manage')
         ->name('api.v1.payments.refund');

    // FR-PAY-005: Invoices
    Route::get('/payments/invoices', [PaymentController::class, 'listInvoices'])
         ->middleware('permission:payments.read')
         ->name('api.v1.payments.invoices');

    Route::get('/payments/invoices/{invoiceId}', [PaymentController::class, 'getInvoice'])
         ->middleware('permission:payments.read')
         ->name('api.v1.payments.invoice');

    // FR-PAY-007: Promo codes
    Route::post('/payments/promo/validate', [PaymentController::class, 'validatePromo'])
         ->middleware('permission:payments.manage')
         ->name('api.v1.payments.validate-promo');

    Route::post('/payments/promo', [PaymentController::class, 'createPromo'])
         ->middleware('permission:payments.manage')
         ->name('api.v1.payments.create-promo');

    // FR-PAY-004: Gateways
    Route::get('/payments/gateways', [PaymentController::class, 'listGateways'])
         ->middleware('permission:payments.read')
         ->name('api.v1.payments.gateways');

    // FR-PAY-011: Balance alerts
    Route::post('/payments/balance-alerts', [PaymentController::class, 'setBalanceAlert'])
         ->middleware('permission:payments.manage')
         ->name('api.v1.payments.set-alert');

    Route::get('/payments/balance-alerts', [PaymentController::class, 'getBalanceAlerts'])
         ->middleware('permission:payments.read')
         ->name('api.v1.payments.alerts');

    // FR-PAY-006: Tax calculator
    Route::get('/payments/tax-calculate', [PaymentController::class, 'calculateTax'])
         ->middleware('permission:payments.read')
         ->name('api.v1.payments.tax');

    // FR-PAY-003: Subscriptions
    Route::post('/subscriptions/subscribe', [PaymentController::class, 'subscribe'])
         ->middleware('permission:subscriptions.manage')
         ->name('api.v1.subscriptions.subscribe');

    Route::post('/subscriptions/cancel', [PaymentController::class, 'cancelSubscription'])
         ->middleware('permission:subscriptions.manage')
         ->name('api.v1.subscriptions.cancel');

    Route::post('/subscriptions/renew', [PaymentController::class, 'renewSubscription'])
         ->middleware('permission:subscriptions.manage')
         ->name('api.v1.subscriptions.renew');

    Route::get('/subscriptions/status', [PaymentController::class, 'subscriptionStatus'])
         ->middleware('permission:subscriptions.read')
         ->name('api.v1.subscriptions.status');

    Route::get('/subscriptions/plans', [PaymentController::class, 'listPlans'])
         ->middleware('permission:subscriptions.read')
         ->name('api.v1.subscriptions.plans');

    // ═══════════════════════════════════════════════════════════════
    // RPT Module — Reports & Analytics (FR-RPT-001→010)
    // ═══════════════════════════════════════════════════════════════

    // FR-RPT-001: Shipment dashboard
    Route::get('/reports/shipment-dashboard', [ReportController::class, 'shipmentDashboard'])
         ->middleware('permission:reports.read')
         ->name('api.v1.reports.shipment-dashboard');

    // FR-RPT-002: Profit report
    Route::get('/reports/profit', [ReportController::class, 'profitReport'])
         ->middleware('permission:reports.read')
         ->name('api.v1.reports.profit');

    // FR-RPT-003: Export
    Route::post('/reports/export', [ReportController::class, 'createExport'])
         ->middleware('permission:reports.export')
         ->name('api.v1.reports.export');

    Route::get('/reports/exports', [ReportController::class, 'listExports'])
         ->middleware('permission:reports.read')
         ->name('api.v1.reports.exports');

    // FR-RPT-004: Exception report
    Route::get('/reports/exceptions', [ReportController::class, 'exceptionReport'])
         ->middleware('permission:reports.read')
         ->name('api.v1.reports.exceptions');

    // FR-RPT-005: Operational & Financial
    Route::get('/reports/operational', [ReportController::class, 'operationalReport'])
         ->middleware('permission:reports.read')
         ->name('api.v1.reports.operational');

    Route::get('/reports/financial', [ReportController::class, 'financialReport'])
         ->middleware('permission:reports.read')
         ->name('api.v1.reports.financial');

    // FR-RPT-006: Grouped data
    Route::get('/reports/grouped', [ReportController::class, 'groupedData'])
         ->middleware('permission:reports.read')
         ->name('api.v1.reports.grouped');

    // FR-RPT-007: Charts & analytics
    Route::get('/reports/carrier-performance', [ReportController::class, 'carrierPerformance'])
         ->middleware('permission:reports.read')
         ->name('api.v1.reports.carrier-performance');

    Route::get('/reports/store-performance', [ReportController::class, 'storePerformance'])
         ->middleware('permission:reports.read')
         ->name('api.v1.reports.store-performance');

    Route::get('/reports/revenue', [ReportController::class, 'revenueChart'])
         ->middleware('permission:reports.read')
         ->name('api.v1.reports.revenue');

    // FR-RPT-008: Scheduled reports
    Route::post('/reports/schedules', [ReportController::class, 'createSchedule'])
         ->middleware('permission:reports.manage')
         ->name('api.v1.reports.create-schedule');

    Route::get('/reports/schedules', [ReportController::class, 'listSchedules'])
         ->middleware('permission:reports.read')
         ->name('api.v1.reports.schedules');

    Route::delete('/reports/schedules/{scheduleId}', [ReportController::class, 'cancelSchedule'])
         ->middleware('permission:reports.manage')
         ->name('api.v1.reports.cancel-schedule');

    // FR-RPT-009: Wallet report
    Route::get('/reports/wallet', [ReportController::class, 'walletReport'])
         ->middleware('permission:reports.read')
         ->name('api.v1.reports.wallet');

    // FR-RPT-010: Generic reports API
    Route::get('/reports/api/{type}', [ReportController::class, 'reportApi'])
         ->middleware('permission:reports.read')
         ->name('api.v1.reports.api');

    // Saved reports
    Route::post('/reports/saved', [ReportController::class, 'saveReport'])
         ->middleware('permission:reports.manage')
         ->name('api.v1.reports.save');

    Route::get('/reports/saved', [ReportController::class, 'listSavedReports'])
         ->middleware('permission:reports.read')
         ->name('api.v1.reports.saved');

    // ═══════════════════════════════════════════════════════════════
    // ADM Module — Platform Administration (FR-ADM-001→010)
    // SECURITY: Protected with admin.access permission middleware
    // ═══════════════════════════════════════════════════════════════

    // ═══════════════════════════════════════════════════════════════
    // ORG Module — Organizations & Teams (FR-ORG-001→010)
    // ═══════════════════════════════════════════════════════════════

    // FR-ORG-001: Create organization
    Route::post('/organizations', [OrganizationController::class, 'create'])
         ->middleware('permission:organizations.manage')
         ->name('api.v1.organizations.create');

    Route::get('/organizations', [OrganizationController::class, 'listForAccount'])
         ->middleware('permission:organizations.read')
         ->name('api.v1.organizations.list');

    // FR-ORG-002: Profile management
    Route::get('/organizations/{orgId}', [OrganizationController::class, 'show'])
         ->middleware('permission:organizations.read')
         ->name('api.v1.organizations.show');

    Route::put('/organizations/{orgId}', [OrganizationController::class, 'update'])
         ->middleware('permission:organizations.manage')
         ->name('api.v1.organizations.update');

    // FR-ORG-003: Invitations
    Route::post('/organizations/{orgId}/invites', [OrganizationController::class, 'invite'])
         ->middleware('permission:organizations.manage')
         ->name('api.v1.organizations.invite');

    Route::get('/organizations/{orgId}/invites', [OrganizationController::class, 'listInvites'])
         ->middleware('permission:organizations.read')
         ->name('api.v1.organizations.invites');

    Route::post('/organizations/invites/accept', [OrganizationController::class, 'acceptInvite'])
         ->middleware('permission:organizations.manage')
         ->name('api.v1.organizations.accept-invite');

    Route::delete('/organizations/invites/{inviteId}', [OrganizationController::class, 'cancelInvite'])
         ->middleware('permission:organizations.manage')
         ->name('api.v1.organizations.cancel-invite');

    Route::post('/organizations/invites/{inviteId}/resend', [OrganizationController::class, 'resendInvite'])
         ->middleware('permission:organizations.manage')
         ->name('api.v1.organizations.resend-invite');

    // FR-ORG-004: Permission catalog
    Route::get('/organizations/permissions/catalog', [OrganizationController::class, 'permissionCatalog'])
         ->middleware('permission:organizations.read')
         ->name('api.v1.organizations.permission-catalog');

    // FR-ORG-005: Financial access
    Route::put('/organizations/members/{memberId}/financial-access', [OrganizationController::class, 'setFinancialAccess'])
         ->middleware('permission:organizations.manage')
         ->name('api.v1.organizations.financial-access');

    // FR-ORG-006: Permission check
    Route::post('/organizations/{orgId}/check-permission', [OrganizationController::class, 'checkPermission'])
         ->middleware('permission:organizations.manage')
         ->name('api.v1.organizations.check-permission');

    // FR-ORG-007: Members & ownership
    Route::get('/organizations/{orgId}/members', [OrganizationController::class, 'listMembers'])
         ->middleware('permission:organizations.read')
         ->name('api.v1.organizations.members');

    Route::post('/organizations/{orgId}/transfer-ownership', [OrganizationController::class, 'transferOwnership'])
         ->middleware('permission:organizations.manage')
         ->name('api.v1.organizations.transfer-ownership');

    Route::post('/organizations/members/{memberId}/suspend', [OrganizationController::class, 'suspendMember'])
         ->middleware('permission:organizations.manage')
         ->name('api.v1.organizations.suspend-member');

    Route::delete('/organizations/members/{memberId}', [OrganizationController::class, 'removeMember'])
         ->middleware('permission:organizations.manage')
         ->name('api.v1.organizations.remove-member');

    Route::put('/organizations/members/{memberId}/role', [OrganizationController::class, 'updateMemberRole'])
         ->middleware('permission:organizations.manage')
         ->name('api.v1.organizations.update-member-role');

    // FR-ORG-008: Verification
    Route::post('/organizations/{orgId}/submit-verification', [OrganizationController::class, 'submitVerification'])
         ->middleware('permission:organizations.manage')
         ->name('api.v1.organizations.submit-verification');

    // FR-ORG-009/010: Wallet
    Route::get('/organizations/{orgId}/wallet', [OrganizationController::class, 'walletSummary'])
         ->middleware('permission:organizations.read')
         ->name('api.v1.organizations.wallet');

    Route::post('/organizations/{orgId}/wallet/topup', [OrganizationController::class, 'topUpWallet'])
         ->middleware('permission:organizations.manage')
         ->name('api.v1.organizations.wallet-topup');

    Route::put('/organizations/{orgId}/wallet/settings', [OrganizationController::class, 'updateWalletSettings'])
         ->middleware('permission:organizations.manage')
         ->name('api.v1.organizations.wallet-settings');

    // ═══════════════════════════════════════════════════════════════
    // BRP Module — Business Rules: Pricing (FR-BRP-001→008)
    // ═══════════════════════════════════════════════════════════════

    // FR-BRP-001: Calculate price
    Route::post('/pricing/calculate', [PricingController::class, 'calculate'])
         ->middleware('permission:pricing.read')
         ->name('api.v1.pricing.calculate');

    // FR-BRP-006: Breakdowns
    Route::get('/pricing/breakdowns', [PricingController::class, 'listBreakdowns'])
         ->middleware('permission:pricing.read')
         ->name('api.v1.pricing.breakdowns');

    Route::get('/pricing/breakdowns/{entityType}/{entityId}', [PricingController::class, 'getBreakdown'])
         ->middleware('permission:pricing.read')
         ->name('api.v1.pricing.breakdown');

    // FR-BRP-008: Rule sets
    Route::post('/pricing/rule-sets', [PricingController::class, 'createRuleSet'])
         ->middleware('permission:pricing.manage')
         ->name('api.v1.pricing.create-rule-set');

    Route::get('/pricing/rule-sets', [PricingController::class, 'listRuleSets'])
         ->middleware('permission:pricing.read')
         ->name('api.v1.pricing.rule-sets');

    Route::get('/pricing/rule-sets/{ruleSetId}', [PricingController::class, 'getRuleSet'])
         ->middleware('permission:pricing.read')
         ->name('api.v1.pricing.rule-set');

    Route::post('/pricing/rule-sets/{ruleSetId}/activate', [PricingController::class, 'activateRuleSet'])
         ->middleware('permission:pricing.manage')
         ->name('api.v1.pricing.activate-rule-set');

    // FR-BRP-005: Rounding
    Route::post('/pricing/rounding', [PricingController::class, 'setRounding'])
         ->middleware('permission:pricing.manage')
         ->name('api.v1.pricing.set-rounding');

    // FR-BRP-007: Expired plan policy
    Route::post('/pricing/expired-policy', [PricingController::class, 'setExpiredPolicy'])
         ->middleware('permission:pricing.manage')
         ->name('api.v1.pricing.set-expired-policy');

    // ═══════════════════════════════════════════════════════════════
    // KYC Module — Compliance & Verification (FR-KYC-001→008)
    // ═══════════════════════════════════════════════════════════════

    // FR-KYC-001: Cases
    Route::post('/kyc/cases', [KycComplianceController::class, 'createCase'])
         ->middleware('permission:kyc.manage')
         ->name('api.v1.kyc.create-case');

    Route::get('/kyc/cases/{caseId}', [KycComplianceController::class, 'getCase'])
         ->middleware('permission:kyc.read')
         ->name('api.v1.kyc.get-case');

    Route::get('/kyc/status', [KycComplianceController::class, 'getStatus'])
         ->middleware('permission:kyc.read')
         ->name('api.v1.kyc.status');

    // FR-KYC-002: Documents
    Route::post('/kyc/cases/{caseId}/documents', [KycComplianceController::class, 'uploadDocument'])
         ->middleware('permission:kyc.documents.manage')
         ->name('api.v1.kyc.upload-document');

    // FR-KYC-003: Submit
    Route::post('/kyc/cases/{caseId}/submit', [KycComplianceController::class, 'submit'])
         ->middleware('permission:kyc.manage')
         ->name('api.v1.kyc.submit');

    // FR-KYC-004: Restrictions
    Route::post('/kyc/restrictions/check', [KycComplianceController::class, 'checkRestriction'])
         ->middleware('permission:kyc.read')
         ->name('api.v1.kyc.check-restriction');

    Route::get('/kyc/restrictions', [KycComplianceController::class, 'listRestrictions'])
         ->middleware('permission:kyc.read')
         ->name('api.v1.kyc.restrictions');

    Route::post('/kyc/restrictions', [KycComplianceController::class, 'createRestriction'])
         ->middleware('permission:kyc.manage')
         ->name('api.v1.kyc.create-restriction');

    // FR-KYC-005: Admin review
    Route::get('/kyc/pending', [KycComplianceController::class, 'listPending'])
         ->middleware('permission:kyc.manage')
         ->name('api.v1.kyc.pending');

    Route::post('/kyc/cases/{caseId}/review', [KycComplianceController::class, 'review'])
         ->middleware('permission:kyc.manage')
         ->name('api.v1.kyc.review');

    // FR-KYC-006: Display
    Route::get('/kyc/display', [KycComplianceController::class, 'statusDisplay'])
         ->middleware('permission:kyc.read')
         ->name('api.v1.kyc.display');

    // FR-KYC-007: Secure download
    Route::get('/kyc/documents/{documentId}/download', [KycComplianceController::class, 'downloadDocument'])
         ->middleware('permission:kyc.documents.read')
         ->name('api.v1.kyc.download-document');

    // FR-KYC-008: Audit
    Route::get('/kyc/cases/{caseId}/audit-log', [KycComplianceController::class, 'auditLog'])
         ->middleware('permission:kyc.read')
         ->name('api.v1.kyc.audit-log');

    Route::get('/kyc/audit-log/export', [KycComplianceController::class, 'exportAuditLog'])
         ->middleware('permission:kyc.manage')
         ->name('api.v1.kyc.export-audit-log');

    // ═══════════════════════════════════════════════════════════════
    // BW Module — Billing & Wallet (FR-BW-001→010)
    // ═══════════════════════════════════════════════════════════════

    // FR-BW-001: Wallet CRUD
    Route::post('/billing/wallets', [BillingWalletController::class, 'create'])
         ->middleware('permission:billing.manage')
         ->name('api.v1.billing.create-wallet');

    Route::get('/billing/wallets/{walletId}', [BillingWalletController::class, 'show'])
         ->middleware('permission:wallet.balance')
         ->name('api.v1.billing.get-wallet');

    Route::get('/billing/my-wallet', [BillingWalletController::class, 'myWallet'])
         ->middleware('permission:wallet.balance')
         ->name('api.v1.billing.my-wallet');

    Route::get('/billing/wallets/{walletId}/balance', [BillingWalletController::class, 'balance'])
         ->middleware('permission:wallet.balance')
         ->name('api.v1.billing.balance');

    Route::get('/billing/wallets/{walletId}/summary', [BillingWalletController::class, 'summary'])
         ->middleware('permission:wallet.balance')
         ->name('api.v1.billing.summary');

    // FR-BW-002/003: Top-up lifecycle
    Route::post('/billing/wallets/{walletId}/topup', [BillingWalletController::class, 'initiateTopup'])
         ->middleware('permission:wallet.topup')
         ->name('api.v1.billing.initiate-topup');

    Route::post('/billing/topups/{topupId}/confirm', [BillingWalletController::class, 'confirmTopup'])
         ->middleware('permission:billing.manage')
         ->name('api.v1.billing.confirm-topup');

    Route::post('/billing/topups/{topupId}/fail', [BillingWalletController::class, 'failTopup'])
         ->middleware('permission:billing.manage')
         ->name('api.v1.billing.fail-topup');

    // FR-BW-004/005: Ledger & Statement
    Route::get('/billing/wallets/{walletId}/statement', [BillingWalletController::class, 'statement'])
         ->middleware('permission:wallet.ledger')
         ->name('api.v1.billing.statement');

    // FR-BW-006: Refunds
    Route::post('/billing/wallets/{walletId}/refund', [BillingWalletController::class, 'refund'])
         ->middleware('permission:billing.manage')
         ->name('api.v1.billing.refund');

    // FR-BW-007: Holds
    Route::post('/billing/wallets/{walletId}/hold', [BillingWalletController::class, 'createHold'])
         ->middleware('permission:billing.manage')
         ->name('api.v1.billing.create-hold');

    Route::post('/billing/holds/{holdId}/capture', [BillingWalletController::class, 'captureHold'])
         ->middleware('permission:billing.manage')
         ->name('api.v1.billing.capture-hold');

    Route::post('/billing/holds/{holdId}/release', [BillingWalletController::class, 'releaseHold'])
         ->middleware('permission:billing.manage')
         ->name('api.v1.billing.release-hold');

    // FR-BW-003: Direct charge
    Route::post('/billing/wallets/{walletId}/charge', [BillingWalletController::class, 'charge'])
         ->middleware('permission:billing.manage')
         ->name('api.v1.billing.charge');

    // FR-BW-004: Reversal
    Route::post('/billing/wallets/{walletId}/reversal', [BillingWalletController::class, 'reversal'])
         ->middleware('permission:billing.manage')
         ->name('api.v1.billing.reversal');

    // FR-BW-008: Threshold & Auto-topup
    Route::put('/billing/wallets/{walletId}/threshold', [BillingWalletController::class, 'setThreshold'])
         ->middleware('permission:wallet.configure')
         ->name('api.v1.billing.set-threshold');

    Route::put('/billing/wallets/{walletId}/auto-topup', [BillingWalletController::class, 'configureAutoTopup'])
         ->middleware('permission:wallet.configure')
         ->name('api.v1.billing.configure-auto-topup');

    // FR-BW-010: Reconciliation
    Route::post('/billing/reconciliation', [BillingWalletController::class, 'reconcile'])
         ->middleware('permission:billing.manage')
         ->name('api.v1.billing.reconcile');

    Route::get('/billing/reconciliation', [BillingWalletController::class, 'reconciliationReports'])
         ->middleware('permission:billing.manage')
         ->name('api.v1.billing.reconciliation-reports');

    // ═══════════════════════════════════════════════════════════════
    // DG Module — Dangerous Goods Compliance (FR-DG-001→009)
    // ═══════════════════════════════════════════════════════════════

    // FR-DG-001: Create Declaration
    Route::post('/dg/declarations', [DgComplianceController::class, 'create'])
         ->middleware('permission:dg.manage')
         ->name('api.v1.dg.create-declaration');

    Route::get('/dg/declarations', [DgComplianceController::class, 'list'])
         ->middleware('permission:dg.read')
         ->name('api.v1.dg.list-declarations');

    Route::get('/dg/declarations/{declarationId}', [DgComplianceController::class, 'show'])
         ->middleware('permission:dg.read')
         ->name('api.v1.dg.get-declaration');

    Route::get('/dg/shipments/{shipmentId}/declaration', [DgComplianceController::class, 'forShipment'])
         ->middleware('permission:dg.read')
         ->name('api.v1.dg.shipment-declaration');

    // FR-DG-002: Set DG Flag
    Route::post('/dg/declarations/{declarationId}/dg-flag', [DgComplianceController::class, 'setDgFlag'])
         ->middleware('permission:dg.manage')
         ->name('api.v1.dg.set-dg-flag');

    // FR-DG-003: Hold Info
    Route::get('/dg/declarations/{declarationId}/hold-info', [DgComplianceController::class, 'holdInfo'])
         ->middleware('permission:dg.read')
         ->name('api.v1.dg.hold-info');

    Route::get('/dg/blocked', [DgComplianceController::class, 'listBlocked'])
         ->middleware('permission:dg.read')
         ->name('api.v1.dg.list-blocked');

    // FR-DG-004: Accept Waiver
    Route::post('/dg/declarations/{declarationId}/accept-waiver', [DgComplianceController::class, 'acceptWaiver'])
         ->middleware('permission:dg.manage')
         ->name('api.v1.dg.accept-waiver');

    // FR-DG-007: Validate for Issuance
    Route::post('/dg/validate-issuance', [DgComplianceController::class, 'validateForIssuance'])
         ->middleware('permission:dg.manage')
         ->name('api.v1.dg.validate-issuance');

    // FR-DG-009: DG Metadata
    Route::post('/dg/declarations/{declarationId}/metadata', [DgComplianceController::class, 'saveDgMetadata'])
         ->middleware('permission:dg.manage')
         ->name('api.v1.dg.save-metadata');

    // FR-DG-006: Waiver Version Management
    Route::post('/dg/waivers', [DgComplianceController::class, 'publishWaiver'])
         ->middleware('permission:dg.manage')
         ->name('api.v1.dg.publish-waiver');

    Route::get('/dg/waivers/active', [DgComplianceController::class, 'activeWaiver'])
         ->middleware('permission:dg.read')
         ->name('api.v1.dg.active-waiver');

    Route::get('/dg/waivers', [DgComplianceController::class, 'listWaiverVersions'])
         ->middleware('permission:dg.read')
         ->name('api.v1.dg.list-waivers');

    // FR-DG-005: Audit Log
    Route::get('/dg/declarations/{declarationId}/audit-log', [DgComplianceController::class, 'auditLog'])
         ->middleware('permission:dg.read')
         ->name('api.v1.dg.audit-log');

    Route::get('/dg/shipments/{shipmentId}/audit-log', [DgComplianceController::class, 'shipmentAuditLog'])
         ->middleware('permission:dg.read')
         ->name('api.v1.dg.shipment-audit-log');

    Route::get('/dg/audit-log/export', [DgComplianceController::class, 'exportAuditLog'])
         ->middleware('permission:dg.manage')
         ->name('api.v1.dg.export-audit-log');

    // ═══════════════════════════════════════════════════════════════
    // Phase 2 Expansion — 11 New Modules
    // ═══════════════════════════════════════════════════════════════

    // ── Companies ────────────────────────────────────────────────
    Route::apiResource('companies', \App\Http\Controllers\Api\V1\CompanyController::class)
         ->middleware('permission:companies.read', ['only' => ['index', 'show']])
         ->middleware('permission:companies.manage', ['except' => ['index', 'show']])
         ->names('api.v1.companies');
    Route::get('/companies/stats', [\App\Http\Controllers\Api\V1\CompanyController::class, 'stats'])
         ->middleware('permission:companies.read')
         ->name('api.v1.companies.stats');

    // ── Branches ─────────────────────────────────────────────────
    Route::get('/branches/stats', [BranchController::class, 'stats'])
         ->middleware('permission:branches.read')
         ->name('api.v1.branches.stats');
    Route::apiResource('branches', BranchController::class)
         ->middleware('permission:branches.read', ['only' => ['index', 'show']])
         ->middleware('permission:branches.manage', ['except' => ['index', 'show']])
         ->names('api.v1.branches');
    Route::get('/branches/{id}/staff', [BranchController::class, 'staff'])
         ->middleware('permission:branches.read')
         ->name('api.v1.branches.staff');
    Route::post('/branches/{id}/staff', [BranchController::class, 'assignStaff'])
         ->middleware('permission:branches.manage')
         ->name('api.v1.branches.assign-staff');

    // ── Customs & Clearance ──────────────────────────────────────
    Route::get('/customs/stats', [CustomsController::class, 'stats'])->middleware('permission:customs.read')->name('api.v1.customs.stats');
    Route::get('/customs/declarations', [CustomsController::class, 'declarations'])->middleware('permission:customs.read')->name('api.v1.customs.declarations');
    Route::get('/customs/declarations/{id}', [CustomsController::class, 'showDeclaration'])->middleware('permission:customs.read')->name('api.v1.customs.show-declaration');
    Route::post('/customs/declarations', [CustomsController::class, 'createDeclaration'])->middleware('permission:customs.manage')->name('api.v1.customs.create-declaration');
    Route::put('/customs/declarations/{id}', [CustomsController::class, 'updateDeclaration'])->middleware('permission:customs.manage')->name('api.v1.customs.update-declaration');
    Route::post('/customs/declarations/{id}/inspect', [CustomsController::class, 'inspect'])->middleware('permission:customs.manage')->name('api.v1.customs.inspect');
    Route::post('/customs/declarations/{id}/clearance', [CustomsController::class, 'issueClearance'])->middleware('permission:customs.manage')->name('api.v1.customs.clearance');
    Route::get('/customs/brokers', [CustomsController::class, 'brokers'])->middleware('permission:customs.read')->name('api.v1.customs.brokers');
    Route::post('/customs/brokers', [CustomsController::class, 'createBroker'])->middleware('permission:customs.manage')->name('api.v1.customs.create-broker');
    Route::put('/customs/brokers/{id}', [CustomsController::class, 'updateBroker'])->middleware('permission:customs.manage')->name('api.v1.customs.update-broker');
    Route::get('/customs/shipments/{shipmentId}/documents', [CustomsController::class, 'documents'])->middleware('permission:customs.read')->name('api.v1.customs.documents');
    Route::post('/customs/shipments/{shipmentId}/documents', [CustomsController::class, 'uploadDocument'])->middleware('permission:customs.manage')->name('api.v1.customs.upload-document');
    Route::patch('/customs/documents/{id}/verify', [CustomsController::class, 'verifyDocument'])->middleware('permission:customs.manage')->name('api.v1.customs.verify-document');
    Route::get('/customs/shipments/{shipmentId}/duties', [CustomsController::class, 'duties'])->middleware('permission:customs.read')->name('api.v1.customs.duties');

    // ── Containers ───────────────────────────────────────────────
    Route::get('/containers/stats', [ContainerController::class, 'stats'])->middleware('permission:containers.read')->name('api.v1.containers.stats');
    Route::apiResource('containers', ContainerController::class)
         ->middleware('permission:containers.read', ['only' => ['index', 'show']])
         ->middleware('permission:containers.manage', ['except' => ['index', 'show']])
         ->names('api.v1.containers');
    Route::get('/containers/{id}/shipments', [ContainerController::class, 'shipments'])->middleware('permission:containers.read')->name('api.v1.containers.shipments');
    Route::post('/containers/{id}/shipments', [ContainerController::class, 'assignShipment'])->middleware('permission:containers.manage')->name('api.v1.containers.assign-shipment');

    // ── Vessels & Schedules ──────────────────────────────────────
    Route::apiResource('vessels', \App\Http\Controllers\Api\V1\VesselController::class)
         ->middleware('permission:vessels.read', ['only' => ['index', 'show']])
         ->middleware('permission:vessels.manage', ['except' => ['index', 'show']])
         ->names('api.v1.vessels');
    Route::apiResource('vessel-schedules', \App\Http\Controllers\Api\V1\VesselScheduleController::class)
         ->middleware('permission:vessel_schedules.read', ['only' => ['index', 'show']])
         ->middleware('permission:vessel_schedules.manage', ['except' => ['index', 'show']])
         ->names('api.v1.vessel-schedules');

    // ── Claims & Risk ────────────────────────────────────────────
    Route::get('/claims/stats', [ClaimController::class, 'stats'])->middleware('permission:claims.read')->name('api.v1.claims.stats');
    Route::apiResource('claims', ClaimController::class)
         ->middleware('permission:claims.read', ['only' => ['index', 'show']])
         ->middleware('permission:claims.manage', ['except' => ['index', 'show']])
         ->names('api.v1.claims');
    Route::post('/claims/{id}/resolve', [ClaimController::class, 'resolve'])->middleware('permission:claims.manage')->name('api.v1.claims.resolve');
    Route::get('/claims/{id}/history', [ClaimController::class, 'history'])->middleware('permission:claims.read')->name('api.v1.claims.history');
    Route::get('/claims/{id}/documents', [ClaimController::class, 'documents'])->middleware('permission:claims.read')->name('api.v1.claims.documents');
    Route::post('/claims/{id}/documents', [ClaimController::class, 'uploadDocument'])->middleware('permission:claims.manage')->name('api.v1.claims.upload-document');
    Route::get('/risk/shipments/{shipmentId}', [RiskController::class, 'shipmentRisk'])->middleware('permission:risk.read')->name('api.v1.risk.shipment');
    Route::get('/risk/stats', [RiskController::class, 'stats'])->middleware('permission:risk.read')->name('api.v1.risk.stats');

    // ── Support Tickets ──────────────────────────────────────────
    Route::get('/support-tickets/stats', [\App\Http\Controllers\Api\V1\SupportTicketController::class, 'stats'])->middleware('permission:tickets.read')->name('api.v1.tickets.stats');
    Route::apiResource('support-tickets', \App\Http\Controllers\Api\V1\SupportTicketController::class)
         ->middleware('permission:tickets.read', ['only' => ['index', 'show']])
         ->middleware('permission:tickets.manage', ['except' => ['index', 'show']]);
    Route::post('/support-tickets/{ticketId}/replies', [\App\Http\Controllers\Api\V1\SupportTicketController::class, 'reply'])->middleware('permission:tickets.manage')->name('api.v1.tickets.reply');
    Route::patch('/support-tickets/{ticketId}/close', [\App\Http\Controllers\Api\V1\SupportTicketController::class, 'close'])->middleware('permission:tickets.manage')->name('api.v1.tickets.close');
    Route::patch('/support-tickets/{ticketId}/assign', [\App\Http\Controllers\Api\V1\SupportTicketController::class, 'assign'])->middleware('permission:tickets.manage')->name('api.v1.tickets.assign');

    // ── Drivers & Last Mile ──────────────────────────────────────
    Route::get('/drivers/stats', [DriverController::class, 'stats'])->middleware('permission:drivers.read')->name('api.v1.drivers.stats');
    Route::apiResource('drivers', DriverController::class)
         ->middleware('permission:drivers.read', ['only' => ['index', 'show']])
         ->middleware('permission:drivers.manage', ['except' => ['index', 'show']])
         ->names('api.v1.drivers');
    Route::patch('/drivers/{id}/toggle', [DriverController::class, 'toggle'])->middleware('permission:drivers.manage')->name('api.v1.drivers.toggle');
    Route::get('/delivery-assignments', [DriverController::class, 'assignments'])->middleware('permission:delivery.read')->name('api.v1.delivery-assignments.index');
    Route::post('/delivery-assignments', [DriverController::class, 'assign'])->middleware('permission:delivery.manage')->name('api.v1.delivery-assignments.store');
    Route::post('/delivery-assignments/{id}/complete', [DriverController::class, 'completeDelivery'])->middleware('permission:delivery.manage')->name('api.v1.delivery-assignments.complete');
    Route::get('/proof-of-deliveries', [DriverController::class, 'pods'])->middleware('permission:proof_of_deliveries.read')->name('api.v1.pods.index');
    Route::get('/proof-of-deliveries/{id}', [DriverController::class, 'getPod'])->middleware('permission:proof_of_deliveries.read')->name('api.v1.pods.show');

    // ── Incoterms ────────────────────────────────────────────────
    Route::apiResource('incoterms', IncotermController::class)
         ->middleware('permission:incoterms.read', ['only' => ['index', 'show']])
         ->middleware('permission:incoterms.manage', ['except' => ['index', 'show']]);

    // ── HS Codes ─────────────────────────────────────────────────
    Route::get('/hs-codes/search', [HsCodeController::class, 'search'])->middleware('permission:hs_codes.read')->name('api.v1.hs-codes.search');
    Route::apiResource('hs-codes', HsCodeController::class)
         ->middleware('permission:hs_codes.read', ['only' => ['index', 'show']])
         ->middleware('permission:hs_codes.manage', ['except' => ['index', 'show']])
         ->names('api.v1.hs-codes');

    // ── Tariff Engine ────────────────────────────────────────────
    Route::post('/tariffs/calculate', [TariffController::class, 'calculate'])->middleware('permission:tariffs.manage')->name('api.v1.tariffs.calculate');
    Route::apiResource('tariffs', TariffController::class)
         ->middleware('permission:tariffs.read', ['only' => ['index', 'show']])
         ->middleware('permission:tariffs.manage', ['except' => ['index', 'show']]);
    Route::get('/tax-rules', [TariffController::class, 'taxRules'])->middleware('permission:tax_rules.read')->name('api.v1.tax-rules.index');
    Route::post('/tax-rules', [TariffController::class, 'createTaxRule'])->middleware('permission:tax_rules.manage')->name('api.v1.tax-rules.store');

    // ═══════════════════════════════════════════════════════════════
    // PHASE 2: COMPANIES & BRANCHES
    // ═══════════════════════════════════════════════════════════════
    Route::prefix('companies')->group(function () {
        Route::get('/', [CompanyController::class, 'index'])->middleware('permission:companies.read')->name('api.v1.companies.index');
        Route::post('/', [CompanyController::class, 'store'])->middleware('permission:companies.manage')->name('api.v1.companies.store');
        Route::get('/{id}', [CompanyController::class, 'show'])->middleware('permission:companies.read')->name('api.v1.companies.show');
        Route::put('/{id}', [CompanyController::class, 'update'])->middleware('permission:companies.manage')->name('api.v1.companies.update');
        Route::delete('/{id}', [CompanyController::class, 'destroy'])->middleware('permission:companies.manage')->name('api.v1.companies.destroy');
        Route::get('/{id}/stats', [CompanyController::class, 'stats'])->middleware('permission:companies.read')->name('api.v1.companies.stats');
        Route::get('/{id}/branches', [CompanyController::class, 'branches'])->middleware('permission:companies.read')->name('api.v1.companies.branches');
    });

    // ═══════════════════════════════════════════════════════════════
    // PHASE 2: VESSELS & SCHEDULES
    // ═══════════════════════════════════════════════════════════════
    Route::prefix('vessels')->group(function () {
        Route::get('/', [VesselScheduleController::class, 'listVessels'])->middleware('permission:vessels.read')->name('api.v1.vessels.index');
        Route::post('/', [VesselScheduleController::class, 'createVessel'])->middleware('permission:vessels.manage')->name('api.v1.vessels.store');
        Route::get('/{id}', [VesselScheduleController::class, 'showVessel'])->middleware('permission:vessels.read')->name('api.v1.vessels.show');
        Route::put('/{id}', [VesselScheduleController::class, 'updateVessel'])->middleware('permission:vessels.manage')->name('api.v1.vessels.update');
        Route::delete('/{id}', [VesselScheduleController::class, 'deleteVessel'])->middleware('permission:vessels.manage')->name('api.v1.vessels.destroy');
    });

    Route::prefix('vessel-schedules')->group(function () {
        Route::get('/', [VesselScheduleController::class, 'listSchedules'])->middleware('permission:vessel_schedules.read')->name('api.v1.schedules.index');
        Route::post('/', [VesselScheduleController::class, 'createSchedule'])->middleware('permission:vessel_schedules.manage')->name('api.v1.schedules.store');
        Route::get('/search', [VesselScheduleController::class, 'search'])->middleware('permission:vessel_schedules.read')->name('api.v1.schedules.search');
        Route::get('/stats', [VesselScheduleController::class, 'scheduleStats'])->middleware('permission:vessel_schedules.read')->name('api.v1.schedules.stats');
        Route::get('/{id}', [VesselScheduleController::class, 'showSchedule'])->middleware('permission:vessel_schedules.read')->name('api.v1.schedules.show');
        Route::put('/{id}', [VesselScheduleController::class, 'updateSchedule'])->middleware('permission:vessel_schedules.manage')->name('api.v1.schedules.update');
        Route::delete('/{id}', [VesselScheduleController::class, 'deleteSchedule'])->middleware('permission:vessel_schedules.manage')->name('api.v1.schedules.destroy');
    });

    // ═══════════════════════════════════════════════════════════════
    // PHASE 2: BOOKING WORKFLOW
    // ═══════════════════════════════════════════════════════════════
    Route::prefix('booking')->group(function () {
        Route::post('/quotes', [BookingController::class, 'getQuotes'])->middleware('permission:booking.manage')->name('api.v1.booking.quotes');
        Route::post('/create', [BookingController::class, 'createBooking'])->middleware('permission:booking.manage')->name('api.v1.booking.create');
        Route::post('/{id}/confirm', [BookingController::class, 'confirmBooking'])->middleware('permission:booking.manage')->name('api.v1.booking.confirm');
        Route::post('/{id}/cancel', [BookingController::class, 'cancelBooking'])->middleware('permission:booking.manage')->name('api.v1.booking.cancel');
    });

    // ═══════════════════════════════════════════════════════════════
    // PHASE 2: SHIPMENT WORKFLOW (Status Transitions)
    // ═══════════════════════════════════════════════════════════════
    Route::prefix('shipment-workflow')->group(function () {
        Route::get('/statuses', [ShipmentWorkflowController::class, 'statuses'])->middleware('permission:shipment_workflow.read')->name('api.v1.workflow.statuses');
        Route::get('/{id}/next-statuses', [ShipmentWorkflowController::class, 'nextStatuses'])->middleware('permission:shipment_workflow.read')->name('api.v1.workflow.next');
        Route::post('/{id}/transition', [ShipmentWorkflowController::class, 'transition'])->middleware('permission:shipment_workflow.manage')->name('api.v1.workflow.transition');
        Route::post('/{id}/receive-origin', [ShipmentWorkflowController::class, 'receiveAtOrigin'])->middleware('permission:shipment_workflow.manage')->name('api.v1.workflow.receive');
        Route::post('/{id}/export-clearance', [ShipmentWorkflowController::class, 'exportClearance'])->middleware('permission:shipment_workflow.manage')->name('api.v1.workflow.export');
        Route::post('/{id}/load-transit', [ShipmentWorkflowController::class, 'loadToTransit'])->middleware('permission:shipment_workflow.manage')->name('api.v1.workflow.transit');
        Route::post('/{id}/import-clearance', [ShipmentWorkflowController::class, 'importClearance'])->middleware('permission:shipment_workflow.manage')->name('api.v1.workflow.import');
        Route::get('/{id}/sla', [ShipmentWorkflowController::class, 'checkSLA'])->middleware('permission:shipment_workflow.read')->name('api.v1.workflow.sla');
        Route::get('/{id}/predict-delay', [ShipmentWorkflowController::class, 'predictDelay'])->middleware('permission:shipment_workflow.read')->name('api.v1.workflow.delay');
    });

    // ═══════════════════════════════════════════════════════════════
    // PHASE 2: LAST MILE DELIVERY
    // ═══════════════════════════════════════════════════════════════
    Route::prefix('delivery')->group(function () {
        Route::get('/dashboard', [LastMileDeliveryController::class, 'dashboard'])->middleware('permission:delivery.read')->name('api.v1.delivery.dashboard');
        Route::get('/pending', [LastMileDeliveryController::class, 'pendingDeliveries'])->middleware('permission:delivery.read')->name('api.v1.delivery.pending');
        Route::post('/{shipmentId}/assign', [LastMileDeliveryController::class, 'assignDriver'])->middleware('permission:delivery.manage')->name('api.v1.delivery.assign');
        Route::post('/{shipmentId}/pod', [LastMileDeliveryController::class, 'recordPOD'])->middleware('permission:delivery.manage')->name('api.v1.delivery.pod');
        Route::post('/{shipmentId}/failed', [LastMileDeliveryController::class, 'failedDelivery'])->middleware('permission:delivery.manage')->name('api.v1.delivery.failed');
        Route::get('/driver/{driverId}/assignments', [LastMileDeliveryController::class, 'driverAssignments'])->middleware('permission:delivery.read')->name('api.v1.delivery.driver-assignments');
    });

    // ═══════════════════════════════════════════════════════════════
    // PHASE 2: INSURANCE
    // ═══════════════════════════════════════════════════════════════
    Route::prefix('insurance')->group(function () {
        Route::post('/quote', [InsuranceController::class, 'quote'])->middleware('permission:insurance.manage')->name('api.v1.insurance.quote');
        Route::post('/{shipmentId}/purchase', [InsuranceController::class, 'purchase'])->middleware('permission:insurance.manage')->name('api.v1.insurance.purchase');
        Route::post('/{shipmentId}/claim', [InsuranceController::class, 'fileClaim'])->middleware('permission:insurance.manage')->name('api.v1.insurance.claim');
    });

    // ═══════════════════════════════════════════════════════════════
    // PHASE 2: SLA MONITORING
    // ═══════════════════════════════════════════════════════════════
    Route::prefix('sla')->group(function () {
        Route::get('/dashboard', [SLAController::class, 'dashboard'])->middleware('permission:sla.read')->name('api.v1.sla.dashboard');
        Route::get('/check/{id}', [SLAController::class, 'check'])->middleware('permission:sla.read')->name('api.v1.sla.check');
        Route::get('/scan-breaches', [SLAController::class, 'scanBreaches'])->middleware('permission:sla.read')->name('api.v1.sla.scan');
        Route::get('/at-risk', [SLAController::class, 'atRisk'])->middleware('permission:sla.read')->name('api.v1.sla.at-risk');
    });

    // ═══════════════════════════════════════════════════════════════
    // PHASE 2: SUPPORT TICKETS
    // ═══════════════════════════════════════════════════════════════
    Route::prefix('tickets')->group(function () {
        Route::get('/', [SupportTicketController::class, 'index'])->middleware('permission:tickets.read')->name('api.v1.tickets.index');
        Route::post('/', [SupportTicketController::class, 'store'])->middleware('permission:tickets.manage')->name('api.v1.tickets.store');
        Route::get('/stats', [SupportTicketController::class, 'stats'])->middleware('permission:tickets.read')->name('api.v1.tickets.stats');
        Route::get('/{ticketId}', [SupportTicketController::class, 'show'])->middleware('permission:tickets.read')->name('api.v1.tickets.show');
        Route::put('/{ticketId}', [SupportTicketController::class, 'update'])->middleware('permission:tickets.manage')->name('api.v1.tickets.update');
        Route::post('/{ticketId}/reply', [SupportTicketController::class, 'reply'])->middleware('permission:tickets.manage')->name('api.v1.tickets.reply');
        Route::post('/{ticketId}/assign', [SupportTicketController::class, 'assign'])->middleware('permission:tickets.manage')->name('api.v1.tickets.assign');
        Route::post('/{ticketId}/escalate', [SupportTicketController::class, 'escalate'])->middleware('permission:tickets.manage')->name('api.v1.tickets.escalate');
    });

    // ═══════════════════════════════════════════════════════════════
    // PHASE 2: GLOBAL ANALYTICS
    // ═══════════════════════════════════════════════════════════════
    Route::prefix('analytics')->middleware('permission:analytics.read')->group(function () {
        Route::get('/overview', [AnalyticsController::class, 'overview'])->name('api.v1.analytics.overview');
        Route::get('/shipment-trends', [AnalyticsController::class, 'shipmentTrends'])->name('api.v1.analytics.trends');
        Route::get('/revenue', [AnalyticsController::class, 'revenue'])->name('api.v1.analytics.revenue');
        Route::get('/carrier-performance', [AnalyticsController::class, 'carrierPerformance'])->name('api.v1.analytics.carriers');
        Route::get('/branch-performance', [AnalyticsController::class, 'branchPerformance'])->name('api.v1.analytics.branches');
        Route::get('/geo-distribution', [AnalyticsController::class, 'geoDistribution'])->name('api.v1.analytics.geo');
        Route::get('/commissions', [AnalyticsController::class, 'commissions'])->name('api.v1.analytics.commissions');
    });

    // ═══════════════════════════════════════════════════════════════
    // PHASE 2: INTEGRATIONS
    // ═══════════════════════════════════════════════════════════════
    Route::prefix('integrations')->group(function () {
        Route::get('/', [IntegrationController::class, 'index'])
            ->middleware('permission:integrations.read')
            ->name('api.v1.integrations.index');
        Route::get('/health', [IntegrationController::class, 'health'])
            ->middleware('permission:integrations.read')
            ->name('api.v1.integrations.health');
        Route::post('/{id}/test', [IntegrationController::class, 'test'])
            ->middleware('permission:integrations.manage')
            ->name('api.v1.integrations.test');
        Route::get('/{id}/logs', [IntegrationController::class, 'logs'])
            ->middleware('permission:integrations.read')
            ->name('api.v1.integrations.logs');
        Route::get('/webhook-config', [IntegrationController::class, 'webhookConfig'])
            ->middleware(['permission:integrations.read', 'permission:webhooks.read'])
            ->name('api.v1.integrations.webhooks');
    });

    // ═══════════════════════════════════════════════════════════════
    // PHASE 2: CONTENT DECLARATIONS
    // ═══════════════════════════════════════════════════════════════
    Route::prefix('content-declarations')->group(function () {
        Route::get('/', [ContentDeclarationController::class, 'index'])->middleware('permission:content_declarations.read')->name('api.v1.declarations.index');
        Route::post('/', [ContentDeclarationController::class, 'store'])->middleware('permission:content_declarations.manage')->name('api.v1.declarations.store');
        Route::get('/{id}', [ContentDeclarationController::class, 'show'])->middleware('permission:content_declarations.read')->name('api.v1.declarations.show');
        Route::put('/{id}', [ContentDeclarationController::class, 'update'])->middleware('permission:content_declarations.manage')->name('api.v1.declarations.update');
        Route::post('/{id}/submit', [ContentDeclarationController::class, 'submit'])->middleware('permission:content_declarations.manage')->name('api.v1.declarations.submit');
        Route::post('/{id}/review', [ContentDeclarationController::class, 'review'])->middleware('permission:content_declarations.manage')->name('api.v1.declarations.review');
        Route::delete('/{id}', [ContentDeclarationController::class, 'destroy'])->middleware('permission:content_declarations.manage')->name('api.v1.declarations.destroy');
    });

    // ═══════════════════════════════════════════════════════════════
    // PHASE 3: ENTERPRISE MODULES — 8 Domain Areas
    // ═══════════════════════════════════════════════════════════════

    // ── 1. Route Optimization Engine ─────────────────────────────
    Route::prefix('route-optimization')->group(function () {
        Route::get('/plans', [\App\Http\Controllers\Api\V1\RouteOptimizationController::class, 'plans'])->middleware('permission:route_optimization.read');
        Route::get('/plans/{id}', [\App\Http\Controllers\Api\V1\RouteOptimizationController::class, 'showPlan'])->middleware('permission:route_optimization.read');
        Route::post('/optimize', [\App\Http\Controllers\Api\V1\RouteOptimizationController::class, 'optimize'])->middleware('permission:route_optimization.manage');
        Route::patch('/plans/{id}/select', [\App\Http\Controllers\Api\V1\RouteOptimizationController::class, 'selectPlan'])->middleware('permission:route_optimization.manage');
        Route::get('/cost-factors', [\App\Http\Controllers\Api\V1\RouteOptimizationController::class, 'costFactors'])->middleware('permission:route_optimization.read');
        Route::post('/cost-factors', [\App\Http\Controllers\Api\V1\RouteOptimizationController::class, 'createCostFactor'])->middleware('permission:route_optimization.manage');
        Route::get('/stats', [\App\Http\Controllers\Api\V1\RouteOptimizationController::class, 'stats'])->middleware('permission:route_optimization.read');
    });

    // ── 2. Capacity & Load Management ────────────────────────────
    Route::prefix('capacity')->group(function () {
        Route::get('/pools', [\App\Http\Controllers\Api\V1\CapacityController::class, 'pools'])->middleware('permission:capacity.read');
        Route::get('/pools/{id}', [\App\Http\Controllers\Api\V1\CapacityController::class, 'showPool'])->middleware('permission:capacity.read');
        Route::post('/pools', [\App\Http\Controllers\Api\V1\CapacityController::class, 'createPool'])->middleware('permission:capacity.manage');
        Route::post('/pools/{id}/book', [\App\Http\Controllers\Api\V1\CapacityController::class, 'book'])->middleware('permission:capacity.manage');
        Route::get('/stats', [\App\Http\Controllers\Api\V1\CapacityController::class, 'stats'])->middleware('permission:capacity.read');
    });

    // ── 3. Profitability Engine ──────────────────────────────────
    Route::prefix('profitability')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Api\V1\ProfitabilityController::class, 'dashboard'])
            ->middleware('permission:profitability.read');
        Route::get('/shipment-costs', [\App\Http\Controllers\Api\V1\ProfitabilityController::class, 'shipmentCosts'])
            ->middleware('permission:profitability.read');
        Route::get('/shipment-costs/{shipmentId}', [\App\Http\Controllers\Api\V1\ProfitabilityController::class, 'shipmentCost'])
            ->middleware('permission:profitability.read');
        Route::post('/shipment-costs', [\App\Http\Controllers\Api\V1\ProfitabilityController::class, 'recordCost'])
            ->middleware('permission:profitability.manage');
        Route::get('/branch-pnl', [\App\Http\Controllers\Api\V1\ProfitabilityController::class, 'branchPnl'])
            ->middleware('permission:profitability.read');
    });

    // ── 4. Multi-Currency Ledger ─────────────────────────────────
    Route::prefix('currency')->group(function () {
        Route::get('/rates', [\App\Http\Controllers\Api\V1\CurrencyController::class, 'rates'])->middleware('permission:currency.read');
        Route::post('/rates', [\App\Http\Controllers\Api\V1\CurrencyController::class, 'setRate'])->middleware('permission:currency.manage');
        Route::post('/convert', [\App\Http\Controllers\Api\V1\CurrencyController::class, 'convert'])->middleware('permission:currency.manage');
        Route::get('/transactions', [\App\Http\Controllers\Api\V1\CurrencyController::class, 'transactions'])->middleware('permission:currency.read');
        Route::get('/fx-report', [\App\Http\Controllers\Api\V1\CurrencyController::class, 'fxReport'])->middleware('permission:currency.read');
    });

    // ── 5. IATA/FIATA Compliance Layer ───────────────────────────
    Route::prefix('compliance')->group(function () {
        Route::get('/documents', [\App\Http\Controllers\Api\V1\ComplianceController::class, 'documents'])
            ->middleware('permission:compliance.read');
        Route::post('/documents', [\App\Http\Controllers\Api\V1\ComplianceController::class, 'createDocument'])
            ->middleware('permission:compliance.manage');
        Route::post('/documents/{id}/validate', [\App\Http\Controllers\Api\V1\ComplianceController::class, 'validateDocument'])
            ->middleware('permission:compliance.manage');
        Route::get('/manifests', [\App\Http\Controllers\Api\V1\ComplianceController::class, 'manifests'])
            ->middleware('permission:compliance.read');
        Route::post('/manifests', [\App\Http\Controllers\Api\V1\ComplianceController::class, 'createManifest'])
            ->middleware('permission:compliance.manage');
        Route::get('/retention-policies', [\App\Http\Controllers\Api\V1\ComplianceController::class, 'retentionPolicies'])
            ->middleware('permission:compliance.read');
        Route::post('/retention-policies', [\App\Http\Controllers\Api\V1\ComplianceController::class, 'setRetentionPolicy'])
            ->middleware('permission:compliance.manage');
        Route::get('/audit-log', [\App\Http\Controllers\Api\V1\ComplianceController::class, 'auditLog'])
            ->middleware('permission:compliance.audit.read');
        Route::get('/audit-log/export', [\App\Http\Controllers\Api\V1\ComplianceController::class, 'exportAudit'])
            ->middleware('permission:compliance.audit.export');
        Route::get('/stats', [\App\Http\Controllers\Api\V1\ComplianceController::class, 'complianceStats'])
            ->middleware('permission:compliance.read');
    });

    // ── 6. Data Intelligence Layer ───────────────────────────────
    Route::prefix('intelligence')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Api\V1\IntelligenceController::class, 'dashboard'])
            ->middleware('permission:intelligence.read');
        Route::get('/snapshots', [\App\Http\Controllers\Api\V1\IntelligenceController::class, 'snapshots'])
            ->middleware('permission:intelligence.read');
        Route::get('/route-profitability', [\App\Http\Controllers\Api\V1\IntelligenceController::class, 'routeProfitability'])
            ->middleware('permission:intelligence.read');
        Route::get('/sla-metrics', [\App\Http\Controllers\Api\V1\IntelligenceController::class, 'slaMetrics'])
            ->middleware('permission:intelligence.read');
        Route::get('/sla-dashboard', [\App\Http\Controllers\Api\V1\IntelligenceController::class, 'slaDashboard'])
            ->middleware('permission:intelligence.read');
        Route::get('/clv', [\App\Http\Controllers\Api\V1\IntelligenceController::class, 'clv'])
            ->middleware('permission:intelligence.read');
        Route::get('/clv-summary', [\App\Http\Controllers\Api\V1\IntelligenceController::class, 'clvSummary'])
            ->middleware('permission:intelligence.read');
        Route::get('/delay-predictions', [\App\Http\Controllers\Api\V1\IntelligenceController::class, 'delayPredictions'])
            ->middleware('permission:intelligence.read');
        Route::post('/delay-predictions', [\App\Http\Controllers\Api\V1\IntelligenceController::class, 'predictDelay'])
            ->middleware('permission:intelligence.manage');
        Route::get('/fraud-signals', [\App\Http\Controllers\Api\V1\IntelligenceController::class, 'fraudSignals'])
            ->middleware('permission:intelligence.read');
        Route::patch('/fraud-signals/{id}', [\App\Http\Controllers\Api\V1\IntelligenceController::class, 'reviewFraud'])
            ->middleware('permission:intelligence.manage');
        Route::get('/fraud-dashboard', [\App\Http\Controllers\Api\V1\IntelligenceController::class, 'fraudDashboard'])
            ->middleware('permission:intelligence.read');
        Route::get('/branch-comparison', [\App\Http\Controllers\Api\V1\IntelligenceController::class, 'branchComparison'])
            ->middleware('permission:intelligence.read');
    });

    // ── 7. Customer Self-Service Portal ──────────────────────────
    Route::prefix('portal')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Api\V1\CustomerPortalController::class, 'portalDashboard'])
            ->middleware('permission:account.read');
        Route::post('/quotes', [\App\Http\Controllers\Api\V1\CustomerPortalController::class, 'getQuote'])
            ->middleware('permission:quotes.manage');
        Route::get('/quotes', [\App\Http\Controllers\Api\V1\CustomerPortalController::class, 'savedQuotes'])
            ->middleware('permission:quotes.read');
        Route::post('/quotes/{id}/convert', [\App\Http\Controllers\Api\V1\CustomerPortalController::class, 'convertQuote'])
            ->middleware('permission:quotes.manage');
        Route::get('/shipment-analytics', [\App\Http\Controllers\Api\V1\CustomerPortalController::class, 'shipmentAnalytics'])
            ->middleware('permission:analytics.read');
        Route::get('/api-keys', [\App\Http\Controllers\Api\V1\CustomerPortalController::class, 'apiKeys'])
            ->middleware('permission:api_keys.read');
        Route::post('/api-keys', [\App\Http\Controllers\Api\V1\CustomerPortalController::class, 'createApiKey'])
            ->middleware('permission:api_keys.manage');
        Route::delete('/api-keys/{id}', [\App\Http\Controllers\Api\V1\CustomerPortalController::class, 'revokeApiKey'])
            ->middleware('permission:api_keys.manage');
    });

});

