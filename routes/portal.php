<?php

declare(strict_types=1);

use App\Http\Controllers\FaviconController;
use App\Http\Controllers\Portal\CustomerAuthController;
use App\Http\Controllers\Portal\CustomerPortalController;
use App\Http\Controllers\Portal\CustomerRegistrationController;
use App\Http\Controllers\Portal\VendorAuthController;
use App\Http\Controllers\Portal\VendorEmailVerificationController;
use App\Http\Controllers\Portal\VendorPortalController;
use App\Http\Controllers\Portal\VendorRegistrationController;
use App\Http\Controllers\Portal\VerifyVendorEmailController;
use App\Http\Controllers\Tenant\PortalController;
use App\Http\Middleware\EnsureVendorContactEmailVerified;
use App\Http\Middleware\EnsureVendorHasManufacturerPortalAccess;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    PreventAccessFromCentralDomains::class,
    InitializeTenancyByDomain::class,
])->group(function () {

    Route::get('favicon.ico', FaviconController::class);

    // Token-based access (no auth required, single shared record)
    Route::get('/portal/view/{token}', [PortalController::class, 'show'])
        ->middleware('portal.token')
        ->name('portal.token');

    // Customer auth (guest only)
    Route::middleware('guest:customer')->prefix('portal')->group(function () {
        Route::get('/login', [CustomerAuthController::class, 'create'])->name('portal.login');
        Route::post('/login', [CustomerAuthController::class, 'store']);
        Route::get('/register', [CustomerRegistrationController::class, 'create'])->name('portal.register');
        Route::post('/register', [CustomerRegistrationController::class, 'store']);
    });

    // Authenticated customer portal
    Route::middleware('auth:customer')->prefix('portal')->name('portal.')->group(function () {
        Route::get('/', [CustomerPortalController::class, 'index'])->name('index');
        Route::get('/estimates', [CustomerPortalController::class, 'estimates'])->name('estimates');
        Route::get('/estimates/{id}', [CustomerPortalController::class, 'estimateShow'])->name('estimate.show');
        Route::post('/estimates/{id}/approve', [CustomerPortalController::class, 'approveEstimate'])->name('estimate.approve');
        Route::post('/estimates/{id}/decline', [CustomerPortalController::class, 'declineEstimate'])->name('estimate.decline');
        Route::get('/invoices', [CustomerPortalController::class, 'invoices'])->name('invoices');
        Route::get('/invoices/{invoice}', [CustomerPortalController::class, 'invoiceShow'])->name('invoices.show');
        Route::get('/service-tickets', [CustomerPortalController::class, 'serviceTickets'])->name('servicetickets');
        Route::get('/service-tickets/{uuid}', [CustomerPortalController::class, 'serviceTicketShow'])->name('servicetickets.show');
        Route::get('/documents', [CustomerPortalController::class, 'documents'])->name('documents');
        Route::get('/documents/{document}/download', [CustomerPortalController::class, 'downloadDocument'])->name('documents.download');
        Route::post('/document-requests/{documentRequest}/fulfill', [CustomerPortalController::class, 'fulfillDocumentRequest'])
            ->name('document-requests.fulfill');
        Route::get('/spec-sheets', [CustomerPortalController::class, 'specSheets'])->name('specSheets.index');
        Route::get('/spec-sheets/{uuid}', [CustomerPortalController::class, 'specSheetShow'])->name('specSheet.show');
        Route::post('/spec-sheets/{uuid}/options', [CustomerPortalController::class, 'storeSpecSheetOptionSelections'])->name('specSheet.options.save');
        Route::post('/logout', [CustomerAuthController::class, 'destroy'])->name('logout');
    });

    Route::get('/vendor/portal/email/verify/{id}/{hash}', VerifyVendorEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('vendor.portal.verification.verify');

    Route::middleware('guest:vendor')->prefix('vendor/portal')->group(function () {
        Route::get('/login', [VendorAuthController::class, 'create'])->name('vendor.portal.login');
        Route::post('/login', [VendorAuthController::class, 'store']);
        Route::get('/register', [VendorRegistrationController::class, 'create'])->name('vendor.portal.register');
        Route::post('/register', [VendorRegistrationController::class, 'store']);
    });

    Route::middleware('auth:vendor')->prefix('vendor/portal')->name('vendor.portal.')->group(function () {
        Route::post('/logout', [VendorAuthController::class, 'destroy'])->name('logout');
        Route::get('/email/verify', [VendorEmailVerificationController::class, 'show'])->name('verification.notice');
        Route::post('/email/verification-notification', [VendorEmailVerificationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('verification.send');
    });

    Route::middleware(['auth:vendor', EnsureVendorContactEmailVerified::class, EnsureVendorHasManufacturerPortalAccess::class])
        ->prefix('vendor/portal')
        ->name('vendor.portal.')
        ->group(function () {
            Route::get('/no-manufacturer-access', [VendorPortalController::class, 'noManufacturerPortalAccess'])->name('no-access');
            Route::get('/', [VendorPortalController::class, 'index'])->name('index');
            Route::get('/warranty-claims', [VendorPortalController::class, 'warrantyClaims'])->name('warranty-claims.index');
            Route::get('/warranty-claims/{warranty_claim}', [VendorPortalController::class, 'warrantyClaimShow'])->name('warranty-claims.show');
            Route::get('/warranty-claims/{warranty_claim}/documents/{document}/download', [VendorPortalController::class, 'downloadWarrantyClaimDocument'])
                ->name('warranty-claims.documents.download');
            Route::post('/warranty-claims/{warranty_claim}/line-feedback', [VendorPortalController::class, 'saveWarrantyClaimLineFeedback'])->name('warranty-claims.line-feedback');
            Route::post('/warranty-claims/{warranty_claim}/approve', [VendorPortalController::class, 'approveWarrantyClaim'])->name('warranty-claims.approve');
            Route::post('/warranty-claims/{warranty_claim}/reject', [VendorPortalController::class, 'rejectWarrantyClaim'])->name('warranty-claims.reject');
        });
});
