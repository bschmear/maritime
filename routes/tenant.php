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
use App\Http\Controllers\Tenant\InventoryImageController;
use App\Http\Controllers\Tenant\BoatMakeController;
use App\Http\Controllers\Tenant\SubsidiaryController;
use App\Http\Controllers\Tenant\WorkOrderController;
use App\Http\Controllers\Tenant\ServiceItemController;
use App\Http\Controllers\Tenant\ServiceTicketController;
use App\Http\Controllers\Tenant\DeliveryController;
use App\Http\Controllers\Tenant\DeliveryChecklistController;
use App\Http\Controllers\Tenant\DeliveryChecklistTemplateController;
use App\Http\Controllers\Tenant\AssetController;
use App\Http\Controllers\Tenant\AssetUnitController;
use App\Http\Controllers\Tenant\NotificationController;
use App\Http\Controllers\Tenant\QualificationController;
use App\Http\Controllers\Tenant\ScoreController;

// use App\Http\Controllers\Tenant\PortalController;
use App\Http\Controllers\Tenant\PublicController;
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


Route::middleware(['auth:client'])->group(function () {
    // Route::get('/portal', ...);
    // Route::get('/', [PortalController::class, 'index'])->name('portal');
    // Route::get('/documents', DocumentController::class);
    // Route::get('/invoices', InvoiceController::class);
});


Route::middleware([
    'web',
    PreventAccessFromCentralDomains::class,
    InitializeTenancyByDomain::class,
])->group(function () {

    // Public routes — UUID-secured, no auth required
    Route::get('/service-tickets/{uuid}/review', [PublicController::class, 'review'])->name('service-tickets.review');
    Route::post('/service-tickets/{uuid}/approve', [PublicController::class, 'approve'])->name('service-tickets.approve');
    Route::post('/service-tickets/{uuid}/decline', [PublicController::class, 'decline'])->name('service-tickets.decline');

    Route::get('/deliveries/{uuid}/review', [PublicController::class, 'reviewDelivery'])->name('deliveries.review');
    Route::post('/deliveries/{uuid}/sign', [PublicController::class, 'signDelivery'])->name('deliveries.sign');

    Route::middleware(['auth', 'tenant.access'])->group(function () {
        // Tenant dashboard
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        
        Route::resource('subsidiaries', SubsidiaryController::class);

        Route::prefix('customers')->name('customers.')->group(function () {
            Route::resource('/', CustomerController::class)->parameters(['' => 'customer']);
        });

        Route::prefix('leads')->name('leads.')->group(function () {
            Route::post('/{lead}/convert', [LeadController::class, 'convert'])->name('convert');
            Route::resource('/', LeadController::class)->parameters(['' => 'lead']);

        });

        Route::prefix('scores')->group(function () {
            Route::get('/', [ScoreController::class, 'index'])->name('scoresIndex');
            Route::post('/store', [ScoreController::class, 'store'])->name('scoresStore');
            Route::post('/calculate', [ScoreController::class, 'calculate'])->name('scoresCalculate');
            Route::get('/{id}', [ScoreController::class, 'show'])->name('scoresShow');
            Route::put('/{id}', [ScoreController::class, 'update'])->name('scoresUpdate');
            Route::delete('/{id}', [ScoreController::class, 'destroy'])->name('scoresDestroy');
        });

        Route::prefix('qualifications')->name('qualifications.')->group(function () {
            Route::resource('/', QualificationController::class)->parameters(['' => 'qualification']);
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

        Route::prefix('workorders')->name('workorders.')->group(function () {
            Route::get('/location-tax-rate', [WorkOrderController::class, 'getLocationTaxRate'])->name('location-tax-rate');
            Route::get('/service-items/lookup', [WorkOrderController::class, 'lookupServiceItems'])->name('service-items.lookup');
            Route::get('/{id}/preview', [WorkOrderController::class, 'preview'])->name('preview.view');
            Route::resource('/', WorkOrderController::class)->parameters(['' => 'workorder']);
        });
        Route::prefix('serviceitems')->name('serviceitems.')->group(function () {
            Route::resource('/', ServiceItemController::class)->parameters(['' => 'serviceitem']);
        });
     
        Route::prefix('deliveries')->name('deliveries.')->group(function () {
            Route::get('/work-order-details/{workorder}', [DeliveryController::class, 'workOrderDetails'])->name('work-order-details');
            Route::get('/customer-details/{customer}', [DeliveryController::class, 'customerDetails'])->name('customer-details');
            Route::post('/{delivery}/send-signature-request', [DeliveryController::class, 'sendSignatureRequest'])->name('send-signature-request');
            Route::post('/{delivery}/mark-delivered', [DeliveryController::class, 'markAsDelivered'])->name('mark-delivered');
            Route::resource('/', DeliveryController::class)->parameters(['' => 'delivery']);
        });

        // Delivery Checklists
        Route::prefix('deliveries/{delivery}/checklist')->name('deliveries.checklist.')->group(function () {
            Route::get('/', [DeliveryChecklistController::class, 'index'])->name('index');
            Route::post('/', [DeliveryChecklistController::class, 'store'])->name('store');
            Route::post('/items', [DeliveryChecklistController::class, 'addItem'])->name('add-item');
            Route::put('/items/{item}', [DeliveryChecklistController::class, 'updateItem'])->name('update-item');
            Route::delete('/items/{item}', [DeliveryChecklistController::class, 'removeItem'])->name('remove-item');
        });

        // Delivery Checklist Templates
        Route::prefix('delivery-checklist-templates')->name('delivery-checklist-templates.')->group(function () {
            Route::get('/', [DeliveryChecklistTemplateController::class, 'index'])->name('index');
            Route::post('/', [DeliveryChecklistTemplateController::class, 'store'])->name('store');
            Route::get('/{template}', [DeliveryChecklistTemplateController::class, 'show'])->name('show');
            Route::put('/{template}', [DeliveryChecklistTemplateController::class, 'update'])->name('update');
            Route::delete('/{template}', [DeliveryChecklistTemplateController::class, 'destroy'])->name('destroy');

            // Template Items
            Route::post('/{template}/items', [DeliveryChecklistTemplateController::class, 'addItem'])->name('add-item');
            Route::put('/items/{item}', [DeliveryChecklistTemplateController::class, 'updateItem'])->name('update-item');
            Route::delete('/items/{item}', [DeliveryChecklistTemplateController::class, 'deleteItem'])->name('delete-item');

            // Categories
            Route::get('/categories', [DeliveryChecklistTemplateController::class, 'getCategories'])->name('categories.index');
            Route::post('/categories', [DeliveryChecklistTemplateController::class, 'createCategory'])->name('categories.store');
            Route::put('/categories/{category}', [DeliveryChecklistTemplateController::class, 'updateCategory'])->name('categories.update');
            Route::delete('/categories/{category}', [DeliveryChecklistTemplateController::class, 'deleteCategory'])->name('categories.destroy');
        });

        Route::prefix('servicetickets')->name('servicetickets.')->group(function () {
            Route::get('/location-tax-rate', [ServiceTicketController::class, 'getLocationTaxRate'])->name('location-tax-rate');
            Route::get('/service-items/lookup', [ServiceTicketController::class, 'lookupServiceItems'])->name('service-items.lookup');
            Route::post('/{id}/send-approval-request', [ServiceTicketController::class, 'sendApprovalRequest'])->name('send-approval-request');
            Route::get('/{id}/approval-url', [ServiceTicketController::class, 'getApprovalUrl'])->name('approval-url');
            Route::resource('/', ServiceTicketController::class)->parameters(['' => 'serviceticket']);
        });

        // Route::prefix('inventory')->group(function () {
        //     Route::get('/', [InventoryController::class, 'index'])->name('inventory.index');
        //     Route::get('/make', [InventoryController::class, 'make'])->name('inventory.make');
        //     Route::get('/type', [InventoryController::class, 'type'])->name('inventory.type');
        // });

        Route::prefix('inventoryitems')->name('inventoryitems.')->group(function () {
            Route::resource('/', InventoryItemController::class)->parameters(['' => 'inventoryitem']);
        });

        Route::prefix('assets')->name('assets.')->group(function () {
            Route::resource('/', AssetController::class)->parameters(['' => 'asset']);
        });

        Route::prefix('assetunits')->name('assetunits.')->group(function () {
            Route::resource('/', AssetUnitController::class)->parameters(['' => 'assetunit']);
        });

        Route::prefix('boatmakes')->name('boatmakes.')->group(function () {
            Route::resource('/', BoatMakeController::class)->parameters(['' => 'boatmake']);
        });

        Route::prefix('inventoryunits')->name('inventoryunits.')->group(function () {
            Route::resource('/', InventoryUnitController::class)->parameters(['' => 'inventoryunit']);
        });

        Route::prefix('inventoryimages')->name('inventoryimages.')->group(function () {
            Route::resource('/', InventoryImageController::class)->parameters(['' => 'inventoryimage']);
        });

        Route::prefix('tasks')->name('tasks.')->group(function () {
            Route::resource('/', TaskController::class)->parameters(['' => 'task']);
        });

        Route::prefix('documents')->name('documents.')->group(function () {
            Route::get('/search', [DocumentController::class, 'search'])->name('search');
            Route::post('/upload-attach', [DocumentController::class, 'uploadAttach'])->name('upload-attach');
            Route::get('/{id}/download', [DocumentController::class, 'download'])->name('download');
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

        Route::prefix('documentables')->name('documentables.')->group(function () {
            Route::post('/attach', [DocumentController::class, 'attach'])->name('attach');
            Route::delete('/detach', [DocumentController::class, 'detach'])->name('detach');
        });

        Route::prefix('account')->name('account.')->group(function () {
            Route::get('/', [AccountController::class, 'index'])->name('index');
            Route::post('/update', [AccountController::class, 'update'])->name('update');
        });

        Route::get('/records/lookup', [GeneralController::class, 'lookup'])->name('records.lookup');
        Route::get('/records/select-form', [GeneralController::class, 'selectForm'])->name('records.select-form');

        // Generic Many-to-Many relationship routes (attach/detach for any resource)
        // These need to be registered for each resource that supports many-to-many relationships
        Route::post('/locations/{location}/attach', [LocationController::class, 'attachRelationship'])->name('locations.attach');
        Route::post('/locations/{location}/detach', [LocationController::class, 'detachRelationship'])->name('locations.detach');
        
        Route::post('/subsidiaries/{subsidiary}/attach', [SubsidiaryController::class, 'attachRelationship'])->name('subsidiaries.attach');
        Route::post('/subsidiaries/{subsidiary}/detach', [SubsidiaryController::class, 'detachRelationship'])->name('subsidiaries.detach');

        Route::prefix('notifications')
        ->name('notifications.')
        ->group(function () {
    
            // List notifications (API or page)
            Route::get('/', [NotificationController::class, 'index'])
                ->name('index');
     
            // Redirect + mark as read
            Route::get('/{id}', [NotificationController::class, 'redirect'])
                ->name('redirect');

            // Mark single as read (AJAX)
            Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])
                ->name('read');

            // Mark all as read
            Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])
                ->name('markAllRead');

            // Optional: delete notification
            Route::delete('/{id}', [NotificationController::class, 'destroy'])
                ->name('destroy');
        });


        // Profile routes
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        // Logout route
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    });
});
