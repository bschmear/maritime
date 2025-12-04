<?php
declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Inertia\Inertia;


use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Tenant\ContactController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
/*
|||--------------------------------------------------------------------------
||| Tenant Routes
|||--------------------------------------------------------------------------
|||
||| These routes are loaded for tenant subdomains (tenant.example.com).
||| These routes are loaded by the TenantRouteServiceProvider.
|||
*/
Route::middleware([
    'web',
    PreventAccessFromCentralDomains::class,
    InitializeTenancyByDomain::class,
])->group(function () {
    Route::middleware(['auth', 'tenant.access'])->group(function () {
        // Tenant dashboard
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Contacts routes
        Route::prefix('contacts')->name('contacts.')->group(function () {
            Route::resource('/', ContactController::class)->parameters(['' => 'contact']);

            // Additional custom routes
            // Route::post('/bulk-delete', [ContactController::class, 'bulkDelete'])->name('bulk-delete');
        });


        // Profile routes
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        // Logout route
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    });
});
