<?php

use App\Http\Controllers\Web\B2BAuthWebController;
use App\Http\Controllers\Web\PortalWorkspaceController;
use App\Http\Controllers\Web\ShipmentDocumentWebController;
use Illuminate\Support\Facades\Route;

Route::prefix('b2b')->name('b2b.')->middleware('portal:b2b')->group(function (): void {
    Route::post('/logout', [B2BAuthWebController::class, 'logout'])
        ->middleware('auth:web')
        ->name('logout');

    Route::middleware(['auth:web', 'userType:external', 'tenant', 'ensureAccountType:organization'])->group(function (): void {
        Route::get('/dashboard', [PortalWorkspaceController::class, 'b2bDashboard'])->name('dashboard');

        Route::prefix('shipments')->name('shipments.')->group(function (): void {
            Route::get('/', [PortalWorkspaceController::class, 'b2bShipments'])->name('index');
            Route::get('/create', [PortalWorkspaceController::class, 'b2bShipmentDraft'])->name('create');
            Route::post('/', [PortalWorkspaceController::class, 'storeB2bShipmentDraft'])->name('store');
            Route::get('/{id}/offers', [PortalWorkspaceController::class, 'b2bShipmentOffers'])->name('offers');
            Route::post('/{id}/offers/fetch', [PortalWorkspaceController::class, 'fetchB2bShipmentOffers'])->name('offers.fetch');
            Route::post('/{id}/offers/select', [PortalWorkspaceController::class, 'selectB2bShipmentOffer'])->name('offers.select');
            Route::get('/{id}/declaration', [PortalWorkspaceController::class, 'b2bShipmentDeclaration'])->name('declaration');
            Route::post('/{id}/declaration', [PortalWorkspaceController::class, 'submitB2bShipmentDeclaration'])->name('declaration.submit');
            Route::post('/{id}/wallet-preflight', [PortalWorkspaceController::class, 'triggerB2bShipmentWalletPreflight'])->name('preflight');
            Route::post('/{id}/issue', [PortalWorkspaceController::class, 'issueB2bShipmentAtCarrier'])->name('issue');
            Route::get('/{id}/documents', [ShipmentDocumentWebController::class, 'b2bIndex'])->name('documents.index');
            Route::get('/{id}/documents/{documentId}/{downloadName?}', [ShipmentDocumentWebController::class, 'b2bDownload'])->name('documents.download');
            Route::get('/{id}', [PortalWorkspaceController::class, 'b2bShipmentShow'])->name('show');
        });

        Route::prefix('orders')->name('orders.')->group(function (): void {
            Route::get('/', [PortalWorkspaceController::class, 'b2bOrders'])->name('index');
            Route::get('/{id}', function ($id) {
                return view('b2b.dashboard', ['orderId' => $id]);
            })->name('show');
        });

        Route::prefix('stores')->name('stores.')->group(function (): void {
            Route::get('/', function () {
                return view('b2b.dashboard');
            })->name('index');
        });

        Route::prefix('users')->name('users.')->group(function (): void {
            Route::get('/', [PortalWorkspaceController::class, 'b2bUsers'])->name('index');
        });

        Route::prefix('roles')->name('roles.')->group(function (): void {
            Route::get('/', [PortalWorkspaceController::class, 'b2bRoles'])->name('index');
        });

        Route::prefix('invitations')->name('invitations.')->group(function (): void {
            Route::get('/', function () {
                return view('b2b.dashboard');
            })->name('index');
        });

        Route::prefix('wallet')->name('wallet.')->group(function (): void {
            Route::get('/', [PortalWorkspaceController::class, 'b2bWallet'])->name('index');
        });

        Route::prefix('reports')->name('reports.')->group(function (): void {
            Route::get('/', [PortalWorkspaceController::class, 'b2bReports'])->name('index');
        });

        Route::prefix('developer')->name('developer.')->group(function (): void {
            Route::get('/', [PortalWorkspaceController::class, 'b2bDeveloperHome'])
                ->middleware('permission:integrations.read')
                ->name('index');

            Route::get('/integrations', [PortalWorkspaceController::class, 'b2bDeveloperIntegrations'])
                ->middleware('permission:integrations.read')
                ->name('integrations');
            Route::post('/integrations/{integration}/check', [PortalWorkspaceController::class, 'runIntegrationCheck'])
                ->middleware('permission:integrations.manage')
                ->name('integrations.check');

            Route::get('/api-keys', [PortalWorkspaceController::class, 'b2bDeveloperApiKeys'])
                ->middleware('permission:api_keys.read')
                ->name('api-keys');
            Route::post('/api-keys', [PortalWorkspaceController::class, 'storeDeveloperApiKey'])
                ->middleware('permission:api_keys.manage')
                ->name('api-keys.store');
            Route::post('/api-keys/{apiKey}/revoke', [PortalWorkspaceController::class, 'revokeDeveloperApiKey'])
                ->middleware('permission:api_keys.manage')
                ->name('api-keys.revoke');

            Route::get('/webhooks', [PortalWorkspaceController::class, 'b2bDeveloperWebhooks'])
                ->middleware('permission:webhooks.read')
                ->name('webhooks');
        });

        Route::prefix('settings')->name('settings.')->group(function (): void {
            Route::get('/', function () {
                return view('b2b.dashboard');
            })->name('index');
        });
    });
});
