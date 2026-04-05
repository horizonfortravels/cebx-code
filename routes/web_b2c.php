<?php

use App\Http\Controllers\Web\B2CAuthWebController;
use App\Http\Controllers\Web\PortalAddressBookController;
use App\Http\Controllers\Web\PortalWorkspaceController;
use App\Http\Controllers\Web\ShipmentDocumentWebController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| B2C Web Routes — بوابة الأفراد (individual)
|--------------------------------------------------------------------------
| تسجيل الدخول GET/POST /b2c/login يعتمد على web.php (AuthWebController)
| وعرض pages.auth.login-b2c (نفس تصميم بوابة الأعمال: لوحتان).
| هنا: logout والمسارات المحمية فقط.
*/

Route::prefix('b2c')->name('b2c.')->middleware('portal:b2c')->group(function () {

    Route::post('/logout', [B2CAuthWebController::class, 'logout'])
        ->middleware('auth:web')
        ->name('logout');

    // مسارات محمية
    Route::middleware(['auth:web', 'userType:external', 'tenant', 'ensureAccountType:individual'])->group(function () {

        Route::get('/dashboard', [PortalWorkspaceController::class, 'b2cDashboard'])->name('dashboard');

        Route::prefix('shipments')->name('shipments.')->group(function () {
            Route::get('/', [PortalWorkspaceController::class, 'b2cShipments'])->name('index');
            Route::get('/export', [PortalWorkspaceController::class, 'exportB2cShipments'])->name('export');
            Route::get('/create', [PortalWorkspaceController::class, 'b2cShipmentDraft'])
                ->name('create');
            Route::post('/validate-address', [PortalWorkspaceController::class, 'validateB2cShipmentAddress'])
                ->name('address-validation');
            Route::post('/', [PortalWorkspaceController::class, 'storeB2cShipmentDraft'])
                ->name('store');
            Route::get('/{id}/offers', [PortalWorkspaceController::class, 'b2cShipmentOffers'])
                ->name('offers');
            Route::post('/{id}/offers/fetch', [PortalWorkspaceController::class, 'fetchB2cShipmentOffers'])
                ->name('offers.fetch');
            Route::post('/{id}/offers/select', [PortalWorkspaceController::class, 'selectB2cShipmentOffer'])
                ->name('offers.select');
            Route::get('/{id}/declaration', [PortalWorkspaceController::class, 'b2cShipmentDeclaration'])
                ->name('declaration');
            Route::post('/{id}/declaration', [PortalWorkspaceController::class, 'submitB2cShipmentDeclaration'])
                ->name('declaration.submit');
            Route::post('/{id}/wallet-preflight', [PortalWorkspaceController::class, 'triggerB2cShipmentWalletPreflight'])
                ->name('preflight');
            Route::post('/{id}/issue', [PortalWorkspaceController::class, 'issueB2cShipmentAtCarrier'])
                ->name('issue');
            Route::get('/{id}/documents', [ShipmentDocumentWebController::class, 'b2cIndex'])
                ->name('documents.index');
            Route::get('/{id}/documents/{documentId}/view/{previewName?}', [ShipmentDocumentWebController::class, 'b2cPreview'])
                ->name('documents.preview');
            Route::get('/{id}/documents/{documentId}/{downloadName?}', [ShipmentDocumentWebController::class, 'b2cDownload'])
                ->name('documents.download');
            Route::get('/{id}', [PortalWorkspaceController::class, 'b2cShipmentShow'])->name('show');
        });

        Route::prefix('tracking')->name('tracking.')->group(function () {
            Route::get('/', [PortalWorkspaceController::class, 'b2cTracking'])->name('index');
            Route::get('/{trackingNumber}', [PortalWorkspaceController::class, 'b2cTrackingShow'])->name('show');
        });

        Route::prefix('wallet')->name('wallet.')->group(function () {
            Route::get('/', [PortalWorkspaceController::class, 'b2cWallet'])->name('index');
        });

        Route::prefix('addresses')->name('addresses.')->group(function () {
            Route::get('/', [PortalAddressBookController::class, 'b2cIndex'])->name('index');
            Route::get('/create', [PortalAddressBookController::class, 'b2cCreate'])->name('create');
            Route::post('/', [PortalAddressBookController::class, 'storeB2c'])->name('store');
            Route::get('/{id}/edit', [PortalAddressBookController::class, 'b2cEdit'])->name('edit');
            Route::match(['put', 'patch'], '/{id}', [PortalAddressBookController::class, 'updateB2c'])->name('update');
            Route::delete('/{id}', [PortalAddressBookController::class, 'destroyB2c'])->name('destroy');
        });

        Route::prefix('support')->name('support.')->group(function () {
            Route::get('/', [PortalWorkspaceController::class, 'b2cSupport'])->name('index');
        });

        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [PortalWorkspaceController::class, 'b2cSettings'])->name('index');
        });
    });
});
