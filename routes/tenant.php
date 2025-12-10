<?php
declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Inertia\Inertia;


use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\Tenant\LeadController;
use App\Http\Controllers\Tenant\VendorController;
use App\Http\Controllers\Tenant\TaskController;
use App\Http\Controllers\Tenant\UserController;
use App\Http\Controllers\Tenant\RoleController;
use App\Http\Controllers\Tenant\AccountController;
use App\Http\Controllers\Tenant\GeneralController;
use App\Http\Controllers\Tenant\LocationController;
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

        Route::prefix('customers')->name('customers.')->group(function () {
            Route::resource('/', CustomerController::class)->parameters(['' => 'customer']);
        });

        Route::prefix('leads')->name('leads.')->group(function () {
            Route::resource('/', LeadController::class)->parameters(['' => 'lead']);
        });

        Route::prefix('vendors')->name('vendors.')->group(function () {
            Route::resource('/', VendorController::class)->parameters(['' => 'vendor']);
        });

        Route::prefix('tasks')->name('tasks.')->group(function () {
            Route::resource('/', TaskController::class)->parameters(['' => 'task']);
        });

        Route::prefix('users')->name('users.')->group(function () {
            Route::resource('/', UserController::class)->parameters(['' => 'user']);
        });

        Route::prefix('roles')->name('roles.')->group(function () {
            Route::resource('/', RoleController::class)->parameters(['' => 'role']);
        });

        Route::prefix('locations')->name('locations.')->group(function () {
            Route::resource('/', LocationController::class)->parameters(['' => 'location']);
        });

        Route::prefix('account')->name('account.')->group(function () {
            Route::get('/', [AccountController::class, 'index'])->name('index');
        });

        Route::get('/records/lookup', [GeneralController::class, 'lookup'])->name('records.lookup');


        // Profile routes
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        // Logout route
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    });
});
