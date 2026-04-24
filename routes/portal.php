<?php

declare(strict_types=1);

use App\Http\Controllers\FaviconController;
use App\Http\Controllers\Portal\CustomerAuthController;
use App\Http\Controllers\Portal\CustomerPortalController;
use App\Http\Controllers\Portal\CustomerRegistrationController;
use App\Http\Controllers\Tenant\PortalController;
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
        Route::get('/documents', [CustomerPortalController::class, 'documents'])->name('documents');
        Route::post('/logout', [CustomerAuthController::class, 'destroy'])->name('logout');
    });
});
