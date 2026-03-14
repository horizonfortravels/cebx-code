<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AdminController;

Route::prefix('v1')->middleware(['auth:sanctum', 'userType:internal'])->group(function () {
    Route::get('/internal/ping', function (Request $request) {
        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'internal_route_ok',
                'user_id' => $request->user()?->id,
                'user_type' => $request->user()?->user_type,
            ],
        ]);
    })->name('api.v1.internal.ping');

    Route::get('/internal/tenant-context/ping', function () {
        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'internal_tenant_context_ok',
                'current_account_id' => app()->bound('current_account_id') ? app('current_account_id') : null,
            ],
        ]);
    })->middleware('tenantContext:required')->name('api.v1.internal.tenant-context.ping');

    Route::middleware('permission:admin.access')->group(function () {
        // FR-ADM-001: System settings
        Route::get('/admin/settings/{group}', [AdminController::class, 'getSettings'])
            ->name('api.v1.admin.settings');
        Route::put('/admin/settings', [AdminController::class, 'updateSetting'])
            ->name('api.v1.admin.update-setting');
        Route::post('/admin/test-carrier', [AdminController::class, 'testCarrierConnection'])
            ->name('api.v1.admin.test-carrier');

        // FR-ADM-002/006: Health
        Route::get('/admin/integration-health', [AdminController::class, 'integrationHealth'])
            ->name('api.v1.admin.integration-health');
        Route::get('/admin/system-health', [AdminController::class, 'systemHealth'])
            ->name('api.v1.admin.system-health');

        // FR-ADM-003: Users
        Route::get('/admin/users', [AdminController::class, 'listUsers'])
            ->middleware('permission:users.read')
            ->name('api.v1.admin.users');
        Route::post('/admin/users/{userId}/suspend', [AdminController::class, 'suspendUser'])
            ->middleware('permission:users.manage')
            ->name('api.v1.admin.suspend-user');
        Route::post('/admin/users/{userId}/activate', [AdminController::class, 'activateUser'])
            ->middleware('permission:users.manage')
            ->name('api.v1.admin.activate-user');

        // FR-ADM-005: Tax rules
        Route::get('/admin/tax-rules', [AdminController::class, 'listTaxRules'])
            ->name('api.v1.admin.tax-rules');
        Route::post('/admin/tax-rules', [AdminController::class, 'createTaxRule'])
            ->name('api.v1.admin.create-tax-rule');

        // FR-ADM-006: Role templates
        Route::get('/admin/role-templates', [AdminController::class, 'listRoleTemplates'])
            ->name('api.v1.admin.role-templates');
        Route::post('/admin/role-templates', [AdminController::class, 'createRoleTemplate'])
            ->name('api.v1.admin.create-role-template');

        // FR-ADM-010: Feature flags
        Route::get('/admin/feature-flags', [AdminController::class, 'listFeatureFlags'])
            ->name('api.v1.admin.feature-flags');
        Route::post('/admin/feature-flags', [AdminController::class, 'createFeatureFlag'])
            ->name('api.v1.admin.create-feature-flag');
        Route::put('/admin/feature-flags/{flagId}/toggle', [AdminController::class, 'toggleFeatureFlag'])
            ->name('api.v1.admin.toggle-feature-flag');
        Route::get('/admin/feature-flags/{key}/check', [AdminController::class, 'checkFeatureFlag'])
            ->name('api.v1.admin.check-feature-flag');

        Route::middleware('tenantContext:required')->group(function () {
            // FR-ADM-009: API keys
            Route::post('/admin/api-keys', [AdminController::class, 'createApiKey'])
                ->name('api.v1.admin.create-api-key');
            Route::get('/admin/api-keys', [AdminController::class, 'listApiKeys'])
                ->name('api.v1.admin.api-keys');
            Route::delete('/admin/api-keys/{keyId}', [AdminController::class, 'revokeApiKey'])
                ->name('api.v1.admin.revoke-api-key');
            Route::post('/admin/api-keys/{keyId}/rotate', [AdminController::class, 'rotateApiKey'])
                ->name('api.v1.admin.rotate-api-key');

            // FR-ADM-008: Support tickets
            Route::post('/support/tickets', [AdminController::class, 'createTicket'])
                ->middleware('permission:tickets.manage')
                ->name('api.v1.support.create');
            Route::get('/support/tickets', [AdminController::class, 'listTickets'])
                ->middleware('permission:tickets.read')
                ->name('api.v1.support.list');
            Route::get('/support/tickets/{ticketId}', [AdminController::class, 'getTicket'])
                ->middleware('permission:tickets.read')
                ->name('api.v1.support.get');
            Route::post('/support/tickets/{ticketId}/reply', [AdminController::class, 'replyToTicket'])
                ->middleware('permission:tickets.manage')
                ->name('api.v1.support.reply');
            Route::post('/support/tickets/{ticketId}/assign', [AdminController::class, 'assignTicket'])
                ->middleware('permission:tickets.manage')
                ->name('api.v1.support.assign');
            Route::post('/support/tickets/{ticketId}/resolve', [AdminController::class, 'resolveTicket'])
                ->middleware('permission:tickets.manage')
                ->name('api.v1.support.resolve');
        });
    });
});
