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
use App\Http\Controllers\Api\V1\AdminController;
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

/*
|--------------------------------------------------------------------------
| API Routes â€” v1
|--------------------------------------------------------------------------
|
| FR-IAM-001: Multi-tenant account registration
| FR-IAM-002: User management within account
| FR-IAM-003: RBAC â€” Roles & Permissions management
| FR-IAM-010: Account types (individual/organization)
| FR-IAM-011: Invitation system for users
| FR-IAM-006: Audit Log (comprehensive, append-only)
| FR-IAM-013: Organization/team audit context
| FR-IAM-012: Financial data masking
| FR-IAM-014: KYC status display & capabilities
| FR-IAM-016: KYC document access restriction
| FR-IAM-008: Account settings management
| FR-IAM-009: Multi-store management
| FR-IAM-017+019+020: Wallet/billing permissions & payment masking
| FR-ST-001â†’010: Store integrations & order sync
|
*/

// â”€â”€ Public Routes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::prefix('v1')->middleware('throttle:10,1')->group(function () {

    // Authentication (rate limited: 10 attempts per minute)
    Route::post('/login', [AuthController::class, 'login'])
         ->middleware('throttle:5,1')
         ->name('api.v1.login');

    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
         ->middleware('throttle:3,1')
         ->name('api.v1.forgot-password');

    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
         ->middleware('throttle:5,1')
         ->name('api.v1.reset-password');

    // Account Registration (public)
    Route::post('/register', [AccountController::class, 'register'])
         ->name('api.v1.register');

    // â”€â”€ FR-IAM-011: Invitation Public Endpoints (invitee) â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/invitations/preview/{token}', [InvitationController::class, 'preview'])
         ->name('api.v1.invitations.preview');

    Route::post('/invitations/accept/{token}', [InvitationController::class, 'accept'])
         ->name('api.v1.invitations.accept');

});


// Phase 2A route split: authenticated APIs are separated by actor type.
require base_path('routes/api_external.php');
require base_path('routes/api_internal.php');


// â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
// Public Routes (No Auth) â€” Webhooks
// â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
Route::prefix('v1/webhooks')->group(function () {
        // FR-TR-001/002: DHL Tracking Webhooks (public, no auth â€” signature verified in service)
    Route::post('/dhl/tracking', [TrackingController::class, 'handleDhlWebhook'])
         ->name('api.v1.webhooks.dhl-tracking');

    // FR-TR-007: External tracking API (API key auth)
    Route::get('/track/{token}', [TrackingController::class, 'apiTrack'])
         ->name('api.v1.tracking.public-track');

    Route::post('/{platform}/{storeId}', [WebhookController::class, 'handle'])
         ->name('api.v1.webhooks.handle');
});

