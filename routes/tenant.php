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
use App\Http\Controllers\Tenant\DocumentController;
use App\Http\Controllers\Tenant\OperationsController;
use App\Http\Controllers\Tenant\TransactionController;
use App\Http\Controllers\Tenant\InvoiceController;
use App\Http\Controllers\Tenant\InventoryItemController;
use App\Http\Controllers\Tenant\InventoryUnitController;
use App\Http\Controllers\Tenant\BoatMakeController;
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

        Route::prefix('operations')->name('operations.')->group(function () {
            Route::get('/', [OperationsController::class, 'index'])->name('index');
        });

        Route::prefix('transactions')->name('transactions.')->group(function () {
            Route::resource('/', TransactionController::class)->parameters(['' => 'transaction']);
        });

        Route::prefix('invoices')->name('invoices.')->group(function () {
            Route::resource('/', InvoiceController::class)->parameters(['' => 'invoice']);
        });

        // Route::prefix('inventory')->group(function () {
        //     Route::get('/', [InventoryController::class, 'index'])->name('inventory.index');
        //     Route::get('/make', [InventoryController::class, 'make'])->name('inventory.make');
        //     Route::get('/type', [InventoryController::class, 'type'])->name('inventory.type');
        // });

        Route::prefix('inventoryitems')->name('inventoryitems.')->group(function () {
            Route::resource('/', InventoryItemController::class)->parameters(['' => 'inventoryitem']);
        });
        Route::prefix('boat-make')->name('boatmake.')->group(function () {
            Route::resource('/', BoatMakeController::class)->parameters(['' => 'boatmake']);
        });

        Route::prefix('inventoryunits')->name('inventoryunits.')->group(function () {
            Route::resource('/', InventoryUnitController::class)->parameters(['' => 'inventoryunit']);
        });

        Route::prefix('tasks')->name('tasks.')->group(function () {
            Route::resource('/', TaskController::class)->parameters(['' => 'task']);
        });

        Route::prefix('documents')->name('documents.')->group(function () {
            Route::resource('/', DocumentController::class)->parameters(['' => 'document']);
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
