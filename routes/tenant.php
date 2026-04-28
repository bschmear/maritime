<?php

declare(strict_types=1);
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\FaviconController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Tenant\AccountController;
use App\Http\Controllers\Tenant\AddOnController;
use App\Http\Controllers\Tenant\AssetController;
use App\Http\Controllers\Tenant\AssetSpecController;
use App\Http\Controllers\Tenant\AssetSpecValueController;
use App\Http\Controllers\Tenant\AssetUnitController;
use App\Http\Controllers\Tenant\BoatMakeController;
use App\Http\Controllers\Tenant\BoatShowEmailTemplateController;
use App\Http\Controllers\Tenant\BoatShowEventAssetController;
use App\Http\Controllers\Tenant\CommunicationController;
use App\Http\Controllers\Tenant\ContactAddressController;
use App\Http\Controllers\Tenant\ContactController;
use App\Http\Controllers\Tenant\ContractController;
use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\DeliveryChecklistController;
use App\Http\Controllers\Tenant\DeliveryChecklistTemplateController;
use App\Http\Controllers\Tenant\DeliveryController;
use App\Http\Controllers\Tenant\DeliveryLocationController;
use App\Http\Controllers\Tenant\DocumentController;
use App\Http\Controllers\Tenant\EstimateController;
use App\Http\Controllers\Tenant\EventChecklistController;
use App\Http\Controllers\Tenant\FleetController;
use App\Http\Controllers\Tenant\FleetMaintenanceController;
use App\Http\Controllers\Tenant\GeneralController;
use App\Http\Controllers\Tenant\IntegrationController;
use App\Http\Controllers\Tenant\Integrations\MailchimpController;
use App\Http\Controllers\Tenant\Integrations\QuickbooksController;
use App\Http\Controllers\Tenant\InventoryImageController;
use App\Http\Controllers\Tenant\InventoryItemController;
use App\Http\Controllers\Tenant\InventoryUnitController;
use App\Http\Controllers\Tenant\InvoiceController;
use App\Http\Controllers\Tenant\LeadController;
use App\Http\Controllers\Tenant\LocationController;
use App\Http\Controllers\Tenant\MaintenanceTypeController;
use App\Http\Controllers\Tenant\NotificationController;
use App\Http\Controllers\Tenant\OperationsController;
use App\Http\Controllers\Tenant\OpportunityController;
use App\Http\Controllers\Tenant\PaymentConfigurationController;
use App\Http\Controllers\Tenant\PaymentController;
use App\Http\Controllers\Tenant\PortalAccessController;
use App\Http\Controllers\Tenant\PublicBoatShowEventController;
use App\Http\Controllers\Tenant\PublicController;
use App\Http\Controllers\Tenant\QualificationController;
use App\Http\Controllers\Tenant\ReportsController;
use App\Http\Controllers\Tenant\RoleController;
use App\Http\Controllers\Tenant\SchedulingController;
use App\Http\Controllers\Tenant\ScoreController;
use App\Http\Controllers\Tenant\ServiceItemController;
use App\Http\Controllers\Tenant\ServiceTicketController;
use App\Http\Controllers\Tenant\SpecGroupController;
use App\Http\Controllers\Tenant\StripeController;
use App\Http\Controllers\Tenant\SubsidiaryController;
use App\Http\Controllers\Tenant\Surveys\PublicSurveyController;
use App\Http\Controllers\Tenant\Surveys\SurveyController;
use App\Http\Controllers\Tenant\TaskController;
use App\Http\Controllers\Tenant\TransactionController;
use App\Http\Controllers\Tenant\UserController;
use App\Http\Controllers\Tenant\VendorController;
use App\Http\Controllers\Tenant\WarrantyClaimController;
use App\Http\Controllers\Tenant\WorkOrderController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

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

    Route::get('favicon.ico', FaviconController::class);

    // ── Admin Portal Access Management ───────────────────────────────────

    Route::prefix('portal-accesses')->name('portal-accesses.')->middleware(['auth', 'tenant.access'])->group(function () {
        Route::get('/', [PortalAccessController::class, 'index'])->name('index');
        Route::post('/', [PortalAccessController::class, 'store'])->name('store');
        Route::get('/{portalAccess}', [PortalAccessController::class, 'show'])->name('show');
        Route::put('/{portalAccess}', [PortalAccessController::class, 'update'])->name('update');
        Route::delete('/{portalAccess}', [PortalAccessController::class, 'destroy'])->name('destroy');
    });

    Route::post('/stripe/webhook', [StripeController::class, 'webhook']);

    // Public routes — UUID-secured, no auth required
    Route::get('/service-tickets/{uuid}/review', [PublicController::class, 'review'])->name('service-tickets.review');
    Route::post('/service-tickets/{uuid}/approve', [PublicController::class, 'approve'])->name('service-tickets.approve');
    Route::post('/service-tickets/{uuid}/decline', [PublicController::class, 'decline'])->name('service-tickets.decline');

    Route::get('/estimates/{uuid}/review', [PublicController::class, 'reviewEstimate'])->name('estimates.review');
    Route::post('/estimates/{uuid}/approve', [PublicController::class, 'approveEstimate'])->name('estimates.approve');
    Route::post('/estimates/{uuid}/decline', [PublicController::class, 'declineEstimate'])->name('estimates.decline');

    Route::get('/deliveries/{uuid}/review', [PublicController::class, 'reviewDelivery'])->name('deliveries.review');
    Route::post('/deliveries/{uuid}/sign', [PublicController::class, 'signDelivery'])->name('deliveries.sign');

    Route::get('/contracts/{uuid}/review', [PublicController::class, 'reviewContract'])->name('contracts.review');
    Route::post('/contracts/{uuid}/sign', [PublicController::class, 'signContract'])->name('contracts.sign');

    Route::get('/invoices/{uuid}/view', [PublicController::class, 'viewInvoice'])->name('invoices.view');
    Route::post('/invoices/{uuid}/pay', [PublicController::class, 'startInvoicePayment'])
        ->middleware('throttle:20,1')
        ->name('invoices.pay');

    Route::get('/boat-show-events/{uuid}/public', [PublicBoatShowEventController::class, 'showcase'])
        ->name('boat-show-events.public.showcase');
    Route::get('/boat-show-events/{uuid}/print', [PublicBoatShowEventController::class, 'printFlyer'])
        ->name('boat-show-events.public.print');
    Route::get('/boat-show-events/{uuid}/assets/{asset}', [PublicBoatShowEventController::class, 'assetShow'])
        ->whereNumber('asset')
        ->name('boat-show-events.public.asset');
    Route::get('/boat-show-events/{uuid}/lead', [PublicBoatShowEventController::class, 'leadForm'])
        ->name('boat-show-events.public.lead');
    Route::post('/boat-show-events/{uuid}/lead', [PublicBoatShowEventController::class, 'leadStore'])
        ->middleware('throttle:20,1')
        ->name('boat-show-events.public.lead.store');

    Route::get('/surveys', [PublicSurveyController::class, 'index'])->name('surveysPublic');
    Route::prefix('survey')->group(function () {
        Route::get('/view', [PublicSurveyController::class, 'show'])->name('surveysPublicShow');
        Route::get('/embed', [PublicSurveyController::class, 'embed'])->name('surveysPublicEmbed');
        Route::put('/edit', [PublicSurveyController::class, 'edit'])->name('surveysPublicEdit');
        Route::post('/submit', [PublicSurveyController::class, 'submit'])
            ->middleware('throttle:20,1')
            ->name('surveysPublicSubmit');
    });

    Route::middleware(['auth', 'tenant.access'])->group(function () {
        // Tenant dashboard
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/payments/success', function () {
            return redirect()->route('account.payments')->with('success', 'Payment completed.');
        })->name('payments.success');
        Route::get('/payments/cancel', function () {
            return redirect()->route('account.payments')->with('error', 'Payment cancelled.');
        })->name('payments.cancel');

        Route::get('/stripe/connect', [StripeController::class, 'connect'])->name('stripe.connect');
        Route::post('/stripe/disconnect', [StripeController::class, 'disconnect'])->name('stripe.disconnect');
        Route::get('/stripe/return', [StripeController::class, 'return'])->name('stripe.return');
        Route::get('/stripe/refresh', [StripeController::class, 'refresh'])->name('stripe.refresh');

        Route::resource('subsidiaries', SubsidiaryController::class);

        Route::prefix('customers')->name('customers.')->group(function () {
            Route::resource('/', CustomerController::class)->parameters(['' => 'customer']);
        });

        Route::prefix('contacts')->name('contacts.')->group(function () {
            Route::post('bulk-destroy', [ContactController::class, 'bulkDestroy'])->name('bulk-destroy');
            Route::get('{contact}/addresses', [ContactController::class, 'indexAddresses'])->name('addresses.index');
            Route::post('{contact}/addresses', [ContactController::class, 'storeAddress'])->name('addresses.store');
            Route::post('{contact}/send-portal-link', [ContactController::class, 'sendPortalLink'])
                ->middleware('throttle:15,1')
                ->name('send-portal-link');
            Route::resource('/', ContactController::class)->parameters(['' => 'contact']);
        });

        Route::prefix('contactaddresses')->name('contactaddresses.')->group(function () {
            Route::resource('/', ContactAddressController::class)->parameters(['' => 'contactaddress']);
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

        Route::prefix('communications')->name('communications.')->group(function () {
            Route::get('/recorditems', [CommunicationController::class, 'recorditems'])->name('recorditems');
            Route::post('/store', [CommunicationController::class, 'store'])->name('store');
            Route::put('/update', [CommunicationController::class, 'update'])->name('update');
            Route::delete('/destroy', [CommunicationController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('qualifications')->name('qualifications.')->group(function () {
            Route::resource('/', QualificationController::class)->parameters(['' => 'qualification']);
        });

        Route::prefix('opportunities')->name('opportunities.')->group(function () {
            Route::resource('/', OpportunityController::class)->parameters(['' => 'opportunity']);
        });

        Route::prefix('estimates')->name('estimates.')->group(function () {
            // Static routes must come before the resource wildcard {estimate}
            Route::get('/address-tax-rate', [GeneralController::class, 'getTaxRate'])->name('address-tax-rate');
            Route::post('/{estimate}/create-deal', [EstimateController::class, 'createDeal'])->name('create-deal');
            Route::resource('/', EstimateController::class)->parameters(['' => 'estimate']);
            Route::post('/{estimate}/send-approval', [EstimateController::class, 'sendApprovalRequest'])->name('send-approval');
            Route::post('/{estimate}/revision', [EstimateController::class, 'createRevision'])->name('revision');
        });

        Route::prefix('contracts')->name('contracts.')->group(function () {
            Route::resource('/', ContractController::class)->parameters(['' => 'contract']);
            Route::post('/{contract}/send-to-customer', [ContractController::class, 'sendToCustomer'])->name('send-to-customer');
        });

        Route::prefix('addons')->name('addons.')->group(function () {
            Route::resource('/', AddOnController::class)->parameters(['' => 'addon']);
        });

        Route::prefix('vendors')->name('vendors.')->group(function () {
            Route::patch('{vendor}/primary-contact', [VendorController::class, 'setPrimaryContact'])->name('primary-contact');
            Route::post('{vendor}/attach', [VendorController::class, 'attachRelationship'])->name('attach');
            Route::post('{vendor}/detach', [VendorController::class, 'detachRelationship'])->name('detach');
            Route::resource('/', VendorController::class)->parameters(['' => 'vendor']);
        });

        Route::prefix('operations')->name('operations.')->group(function () {
            Route::get('/', [OperationsController::class, 'index'])->name('index');
        });

        Route::prefix('transactions')->name('transactions.')->group(function () {
            Route::resource('/', TransactionController::class)->parameters(['' => 'transaction']);
        });

        Route::prefix('invoices')->name('invoices.')->group(function () {
            // Static routes must come before the resource wildcard {invoice}
            Route::get('/address-tax-rate', [GeneralController::class, 'getTaxRate'])->name('address-tax-rate');
            Route::get('/prefill-from-transaction/{transaction}', [InvoiceController::class, 'prefillFromTransaction'])->name('prefill-from-transaction');
            Route::post('/{invoice}/send-to-customer', [InvoiceController::class, 'sendToCustomer'])->name('send-to-customer');
            Route::post('/{invoice}/apply-manual-payment', [InvoiceController::class, 'applyManualPayment'])->name('apply-manual-payment');
            Route::resource('/', InvoiceController::class)->parameters(['' => 'invoice']);
        });

        Route::prefix('warrantyclaims')->name('warrantyclaims.')->group(function () {
            Route::resource('/', WarrantyClaimController::class)->parameters(['' => 'warrantyclaim']);
        });

        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('/', [PaymentController::class, 'index'])->name('index');
            Route::get('/create/eligible-invoices', [PaymentController::class, 'eligibleInvoicesForCreate'])->name('create.eligible-invoices');
            Route::get('/create', [PaymentController::class, 'create'])->name('create');
            Route::post('/', [PaymentController::class, 'store'])->name('store');
            Route::get('/{payment}/edit', [PaymentController::class, 'edit'])->name('edit');
            Route::put('/{payment}', [PaymentController::class, 'update'])->name('update');
            Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
        });

        Route::prefix('workorders')->name('workorders.')->group(function () {
            Route::get('/location-tax-rate', [GeneralController::class, 'getTaxRate'])->name('location-tax-rate');
            Route::get('/service-items/lookup', [WorkOrderController::class, 'lookupServiceItems'])->name('service-items.lookup');
            Route::get('/{id}/preview', [WorkOrderController::class, 'preview'])->name('preview.view');
            Route::resource('/', WorkOrderController::class)->parameters(['' => 'workorder']);
        });
        Route::prefix('serviceitems')->name('serviceitems.')->group(function () {
            Route::resource('/', ServiceItemController::class)->parameters(['' => 'serviceitem']);
        });
        Route::prefix('scheduling')->name('scheduling.')->group(function () {
            Route::post('update-item', [SchedulingController::class, 'updateItem'])->name('update-item');
            Route::post('defaults', [SchedulingController::class, 'updateDefaults'])->name('update-defaults');
            Route::resource('/', SchedulingController::class)->parameters(['' => 'scheduling']);
        });

        // ── Common Delivery Locations ─────────────────────────────────
        Route::prefix('delivery-locations')->name('delivery-locations.')->group(function () {
            Route::get('/options', [DeliveryLocationController::class, 'options'])->name('options');
            Route::get('/', [DeliveryLocationController::class, 'index'])->name('index');
            Route::get('/create', [DeliveryLocationController::class, 'create'])->name('create');
            Route::post('/', [DeliveryLocationController::class, 'store'])->name('store');
            Route::get('/{deliveryLocation}', [DeliveryLocationController::class, 'show'])->name('show');
            Route::get('/{deliveryLocation}/edit', [DeliveryLocationController::class, 'edit'])->name('edit');
            Route::match(['put', 'patch'], '/{deliveryLocation}', [DeliveryLocationController::class, 'update'])->name('update');
            Route::delete('/{deliveryLocation}', [DeliveryLocationController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('fleet')->name('fleet.')->group(function () {
            Route::get('/maintenance', [FleetMaintenanceController::class, 'index'])->name('maintenance.index');
            Route::get('/maintenance/{maintenanceLog}', [FleetMaintenanceController::class, 'show'])->name('maintenance.show');

            Route::get('/', [FleetController::class, 'index'])->name('index');
            Route::get('/trucks/create', [FleetController::class, 'createTruck'])->name('trucks.create');
            Route::get('/trailers/create', [FleetController::class, 'createTrailer'])->name('trailers.create');
            Route::post('/', [FleetController::class, 'store'])->name('store');
            Route::post('/{fleet}/maintenance', [FleetMaintenanceController::class, 'store'])->name('maintenance.store');
            Route::match(['put', 'patch'], '/{fleet}/maintenance/{maintenanceLog}', [FleetMaintenanceController::class, 'update'])->name('maintenance.update');
            Route::delete('/{fleet}/maintenance/{maintenanceLog}', [FleetMaintenanceController::class, 'destroy'])->name('maintenance.destroy');
            Route::get('/{fleet}', [FleetController::class, 'show'])->name('show');
            Route::get('/{fleet}/edit', [FleetController::class, 'edit'])->name('edit');
            Route::match(['put', 'patch'], '/{fleet}', [FleetController::class, 'update'])->name('update');
            Route::delete('/{fleet}', [FleetController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('deliveries')->name('deliveries.')->group(function () {
            Route::get('/work-order-details/{workorder}', [DeliveryController::class, 'workOrderDetails'])->name('work-order-details');
            Route::get('/customer-details/{customer}', [DeliveryController::class, 'customerDetails'])->name('customer-details');
            Route::get('/source-items', [DeliveryController::class, 'sourceItems'])->name('source-items');
            Route::get('/schedule', [DeliveryController::class, 'schedule'])->name('delivery-schedule');
            Route::get('/schedule-board', [DeliveryController::class, 'scheduleBoard'])->name('schedule-board');
            Route::post('/check-fleet-schedule', [DeliveryController::class, 'checkFleetSchedule'])->name('check-fleet-schedule');
            Route::post('/travel-estimate', [DeliveryController::class, 'travelEstimate'])->name('travel-estimate');
            Route::get('/{delivery}/print', [DeliveryController::class, 'print'])->name('print');
            Route::post('/{delivery}/send-signature-request', [DeliveryController::class, 'sendSignatureRequest'])->name('send-signature-request');
            Route::post('/{delivery}/mark-delivered', [DeliveryController::class, 'markAsDelivered'])->name('mark-delivered');
            Route::post('/{delivery}/en-route', [DeliveryController::class, 'markEnRoute'])->name('en-route');
            Route::post('/{delivery}/swap-fleet', [DeliveryController::class, 'swapFleet'])->name('swap-fleet');
            Route::post('/{delivery}/items/{item}/delivered', [DeliveryController::class, 'markItemDelivered'])->name('items.mark-delivered');
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

            // Categories (register before /{template} so "categories" is not captured as a template id)
            Route::get('/categories', [DeliveryChecklistTemplateController::class, 'getCategories'])->name('categories.index');
            Route::post('/categories', [DeliveryChecklistTemplateController::class, 'createCategory'])->name('categories.store');
            Route::put('/categories/{categoryId}', [DeliveryChecklistTemplateController::class, 'updateCategory'])->name('categories.update');
            Route::delete('/categories/{categoryId}', [DeliveryChecklistTemplateController::class, 'deleteCategory'])->name('categories.destroy');

            Route::get('/{template}', [DeliveryChecklistTemplateController::class, 'show'])->name('show');
            Route::put('/{template}', [DeliveryChecklistTemplateController::class, 'update'])->name('update');
            Route::delete('/{template}', [DeliveryChecklistTemplateController::class, 'destroy'])->name('destroy');

            // Template Items
            Route::post('/{template}/items', [DeliveryChecklistTemplateController::class, 'addItem'])->name('add-item');
            Route::put('/items/{item}', [DeliveryChecklistTemplateController::class, 'updateItem'])->name('update-item');
            Route::delete('/items/{item}', [DeliveryChecklistTemplateController::class, 'deleteItem'])->name('delete-item');
        });

        Route::prefix('servicetickets')->name('servicetickets.')->group(function () {
            Route::get('/location-tax-rate', [GeneralController::class, 'getTaxRate'])->name('location-tax-rate');
            Route::get('/service-items/lookup', [ServiceTicketController::class, 'lookupServiceItems'])->name('service-items.lookup');
            Route::post('/{id}/send-approval-request', [ServiceTicketController::class, 'sendApprovalRequest'])->name('send-approval-request');
            Route::get('/{id}/approval-url', [ServiceTicketController::class, 'getApprovalUrl'])->name('approval-url');
            Route::resource('/', ServiceTicketController::class)->parameters(['' => 'serviceticket']);
        });

        // ── Boat Shows ────────────────────────────────────────────────
        Route::prefix('boat-show-email-templates')->name('boat-show-email-templates.')->group(function () {
            Route::get('/', [BoatShowEmailTemplateController::class, 'index'])->name('index');
            Route::put('{email_template}', [BoatShowEmailTemplateController::class, 'update'])->name('update');
            Route::post('send-test', [BoatShowEmailTemplateController::class, 'sendTest'])->name('send-test');
        });

        Route::prefix('boat-show-events')->name('boat-show-events.')->group(function () {
            Route::put('{event}/checklist', [EventChecklistController::class, 'updateBoatShowEvent'])->name('checklist.update');
            Route::post('{event}/assets', [BoatShowEventAssetController::class, 'store'])->name('assets.store');
            Route::get('{event}/assets/units', [BoatShowEventAssetController::class, 'units'])->name('assets.units');
            Route::delete('{event}/assets/{eventAsset}', [BoatShowEventAssetController::class, 'destroy'])->name('assets.destroy');
            Route::put('{event}/layout', [BoatShowEventAssetController::class, 'syncLayout'])->name('layout.sync');
            Route::resource('/', \App\Http\Controllers\Tenant\BoatShowEventController::class)
                ->parameters(['' => 'event']);
        });

        Route::prefix('boat-show-layouts')->name('boat-show-layouts.')->group(function () {
            Route::post('/{layout}/sync', [\App\Http\Controllers\Tenant\BoatShowLayoutController::class, 'sync'])->name('sync');
            Route::resource('/', \App\Http\Controllers\Tenant\BoatShowLayoutController::class)
                ->parameters(['' => 'layout']);
        });

        Route::prefix('boat-shows')->name('boat-shows.')->group(function () {
            Route::resource('/', \App\Http\Controllers\Tenant\BoatShowController::class)
                ->parameters(['' => 'boatShow']);

            // Boat Show Events (scoped under a show)
            Route::prefix('{boatShow}/events')->name('events.')->group(function () {
                Route::put('{event}/checklist', [EventChecklistController::class, 'updateBoatShowEvent'])->name('checklist.update');
                Route::post('{event}/assets', [BoatShowEventAssetController::class, 'store'])->name('assets.store');
                Route::get('{event}/assets/units', [BoatShowEventAssetController::class, 'units'])->name('assets.units');
                Route::delete('{event}/assets/{eventAsset}', [BoatShowEventAssetController::class, 'destroy'])->name('assets.destroy');
                Route::put('{event}/layout', [BoatShowEventAssetController::class, 'syncLayout'])->name('layout.sync');
                Route::resource('/', \App\Http\Controllers\Tenant\BoatShowEventController::class)
                    ->parameters(['' => 'event']);
            });

            // Boat Show Layouts (scoped under a show)
            Route::prefix('{boatShow}/layouts')->name('layouts.')->group(function () {
                Route::post('/{layout}/sync', [\App\Http\Controllers\Tenant\BoatShowLayoutController::class, 'sync'])->name('sync');
                Route::resource('/', \App\Http\Controllers\Tenant\BoatShowLayoutController::class)
                    ->parameters(['' => 'layout']);
            });
        });

        Route::post('checklist-templates', [EventChecklistController::class, 'storeTemplate'])->name('checklist-templates.store');

        // Route::prefix('inventory')->group(function () {
        //     Route::get('/', [InventoryController::class, 'index'])->name('inventory.index');
        //     Route::get('/make', [InventoryController::class, 'make'])->name('inventory.make');
        //     Route::get('/type', [InventoryController::class, 'type'])->name('inventory.type');
        // });

        Route::prefix('inventoryitems')->name('inventoryitems.')->group(function () {
            Route::resource('/', InventoryItemController::class)->parameters(['' => 'inventoryitem']);
        });

        Route::prefix('assets')->name('assets.')->group(function () {
            Route::get('units', [AssetUnitController::class, 'index'])->name('units.global-index');
            Route::get('/{asset}/units', [AssetController::class, 'unitsIndex'])->name('units.index');
            Route::get('/{asset}/variants/select-form', [AssetController::class, 'variantsSelectForm'])->name('variants.select-form');
            Route::get('/{asset}/variants', [AssetController::class, 'variantsIndex'])->name('variants.index');
            Route::post('/{asset}/variants', [AssetController::class, 'variantsStore'])->name('variants.store');
            Route::get('/{asset}/variants/{variant}', [AssetController::class, 'variantsShow'])->name('variants.show')->scopeBindings();
            Route::put('/{asset}/variants/{variant}', [AssetController::class, 'variantsUpdate'])->name('variants.update')->scopeBindings();
            Route::delete('/{asset}/variants/{variant}', [AssetController::class, 'variantsDestroy'])->name('variants.destroy')->scopeBindings();
            Route::resource('/', AssetController::class)->parameters(['' => 'asset']);
        });

        Route::prefix('spec-groups')->name('spec-groups.')->group(function () {
            Route::post('/', [SpecGroupController::class, 'store'])->name('store');
            Route::put('/{specGroup}', [SpecGroupController::class, 'update'])->name('update');
            Route::delete('/{specGroup}', [SpecGroupController::class, 'destroy'])->name('destroy');
            Route::post('/reorder', [SpecGroupController::class, 'reorder'])->name('reorder');
        });

        Route::prefix('asset-specs')->name('asset-specs.')->group(function () {
            Route::get('/', [AssetSpecController::class, 'index'])->name('index');
            Route::post('/', [AssetSpecController::class, 'store'])->name('store');
            Route::put('/{assetSpec}', [AssetSpecController::class, 'update'])->name('update');
            Route::delete('/{assetSpec}', [AssetSpecController::class, 'destroy'])->name('destroy');
            Route::post('/reorder', [AssetSpecController::class, 'reorder'])->name('reorder');
        });

        // Asset Spec Values (per asset)
        Route::prefix('assets/{asset}/specs')->name('assets.specs.')->group(function () {
            Route::get('/', [AssetSpecValueController::class, 'index'])->name('index');
            Route::post('/', [AssetSpecValueController::class, 'store'])->name('store');
            Route::delete('/{specValue}', [AssetSpecValueController::class, 'destroy'])->name('destroy');
        });

        Route::get('asset/units', [AssetUnitController::class, 'index'])->name('asset.units.index');

        Route::prefix('assetunits')->name('assetunits.')->group(function () {
            Route::resource('/', AssetUnitController::class)->parameters(['' => 'assetunit']);
        });

        Route::prefix('boatmakes')->name('boatmakes.')->group(function () {
            Route::post('/manual', [BoatMakeController::class, 'storeManual'])->name('manual');
            Route::post('/bulk-from-catalog', [BoatMakeController::class, 'bulkFromCatalog'])->name('bulk-from-catalog');
            Route::post('/{boatmake}/catalog-import', [BoatMakeController::class, 'catalogImport'])->name('catalog-import');
            Route::post('/{boatmake}/catalog-generate-model', [BoatMakeController::class, 'catalogGenerateModel'])->name('catalog-generate-model');
            Route::post('/{boatmake}/import-discovered-models', [BoatMakeController::class, 'queueImportDiscoveredModels'])->name('import-discovered-models');
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

        Route::prefix('maintenance-types')->name('maintenance-types.')->group(function () {
            Route::resource('/', MaintenanceTypeController::class)->parameters(['' => 'maintenanceType']);
        });

        Route::prefix('documentables')->name('documentables.')->group(function () {
            Route::post('/attach', [DocumentController::class, 'attach'])->name('attach');
            Route::delete('/detach', [DocumentController::class, 'detach'])->name('detach');
        });

        Route::prefix('account')->name('account.')->group(function () {
            Route::get('/', [AccountController::class, 'index'])->name('index');
            Route::post('/update', [AccountController::class, 'update'])->name('update');
            Route::get('/payments/stripe-info', [PaymentConfigurationController::class, 'stripeInformation'])->name('payments.stripe-info');
            Route::get('/payments/stripe', [PaymentConfigurationController::class, 'stripePage'])->name('payments.stripe');
            Route::get('/payments', [PaymentConfigurationController::class, 'index'])->name('payments');
            Route::post('/payments/sync-stripe', [PaymentConfigurationController::class, 'syncFromStripe'])->name('payments.sync-stripe');
            Route::patch('/payments/methods', [PaymentConfigurationController::class, 'updateMethod'])->name('payments.methods');
        });

        Route::get('/records/lookup', [GeneralController::class, 'lookup'])->name('records.lookup');
        Route::get('/records/select-form', [GeneralController::class, 'selectForm'])->name('records.select-form');

        // Generic Many-to-Many relationship routes (attach/detach for any resource)
        // These need to be registered for each resource that supports many-to-many relationships
        Route::post('/locations/{location}/attach', [LocationController::class, 'attachRelationship'])->name('locations.attach');
        Route::post('/locations/{location}/detach', [LocationController::class, 'detachRelationship'])->name('locations.detach');

        Route::post('/users/{user}/attach', [UserController::class, 'attachRelationship'])->name('users.attach');
        Route::post('/users/{user}/detach', [UserController::class, 'detachRelationship'])->name('users.detach');

        Route::post('/subsidiaries/{subsidiary}/attach', [SubsidiaryController::class, 'attachRelationship'])->name('subsidiaries.attach');
        Route::post('/subsidiaries/{subsidiary}/detach', [SubsidiaryController::class, 'detachRelationship'])->name('subsidiaries.detach');

        Route::prefix('surveys')->group(function () {
            Route::get('/', [SurveyController::class, 'index'])->name('surveysIndex');
            Route::get('/create', [SurveyController::class, 'create'])->name('surveysCreate');
            Route::get('/templates', [SurveyController::class, 'getTemplates'])->name('surveyTemplates');
            Route::post('/store', [SurveyController::class, 'store'])->name('surveysStore');
            Route::get('/survey', [SurveyController::class, 'show'])->name('surveysShow');
            Route::get('/edit', [SurveyController::class, 'edit'])->name('surveysEdit');
            Route::match(['put', 'patch'], '/update', [SurveyController::class, 'update'])->name('surveysUpdate');
            Route::delete('/delete', [SurveyController::class, 'destroy'])->name('surveysDestroy');
            Route::post('/delete-selected', [SurveyController::class, 'deleteSelected'])->name('surveysDeleteSelected');
            Route::post('/clone', [SurveyController::class, 'clone'])->name('surveysClone');
            Route::get('/get-active-surveys', [SurveyController::class, 'getActiveSurveys'])->name('surveysGetActive');

            // Stubs — pending implementation
            Route::post('/send-to-deal', [SurveyController::class, 'sendToDeal'])->name('surveysSendToDeal');
            Route::post('/send-to-contact', [SurveyController::class, 'sendToContact'])->name('surveysSendToContact');
            Route::post('/send-to-record', [SurveyController::class, 'sendToRecord'])->name('surveysSendToRecord');

            // Response routes
            Route::patch('/response/reassign', [SurveyController::class, 'reassignResponse'])->name('surveyResponseReassign');
            Route::get('/responses', [SurveyController::class, 'responses'])->name('surveyResponses');
            Route::get('/survey/responses', [SurveyController::class, 'responses'])->name('surveyResponsesByUuid');
            Route::get('/survey/response', [SurveyController::class, 'showResponse'])->name('surveyResponseShow');
            Route::post('/survey/response/convert', [SurveyController::class, 'convertSurveyResponse'])->name('surveyResponseConvert');
            Route::post('/survey/response/convert-to-lead', [SurveyController::class, 'convertResponseToLead'])->name('surveyResponseConvertToLead');
        });

        Route::prefix('reports')->name('reports.')->group(function () {

            // Financial
            Route::get('/pnl', [ReportsController::class, 'pnl'])->name('pnl');
            Route::get('/balance-sheet', [ReportsController::class, 'balanceSheet'])->name('balance-sheet');
            Route::get('/cash-flow', [ReportsController::class, 'cashFlow'])->name('cash-flow');
            Route::get('/sales-tax-liability', [ReportsController::class, 'salesTaxLiability'])->name('sales-tax-liability');
            Route::get('/sales-tax-payable', [ReportsController::class, 'salesTaxPayable'])->name('sales-tax-payable');

            // Sales
            Route::get('/sales-by-customer', [ReportsController::class, 'salesByCustomer'])->name('sales-by-customer');
            Route::get('/sales-by-customer/{contact}/invoices', [ReportsController::class, 'salesByCustomerInvoices'])
                ->name('sales-by-customer.invoices');
            Route::get('/sales-by-item-summary', [ReportsController::class, 'salesByItemSummary'])->name('sales-by-item-summary');
            Route::get('/sales-by-item-detail', [ReportsController::class, 'salesByItemDetail'])->name('sales-by-item-detail');

        });

        Route::prefix('integrations')->group(function () {
            Route::get('/', [IntegrationController::class, 'index'])->name('integrations');

            Route::prefix('mailchimp')->group(function () {
                Route::get('/', [MailchimpController::class, 'show'])->name('mailchimp');
                Route::delete('/', [MailchimpController::class, 'destroy'])->name('mailchimp.destroy');
                Route::get('/connect', [MailchimpController::class, 'connect'])->name('mailchimp.connect');

                // === LIST MANAGEMENT ===
                Route::get('/lists', [MailchimpController::class, 'lists'])->name('mailchimp.lists');
                Route::post('/lists', [MailchimpController::class, 'createList'])->name('mailchimp.createList');

                // === SEGMENT MANAGEMENT (within a list) ===
                Route::get('/lists/{listId}/segments', [MailchimpController::class, 'segments'])->name('mailchimp.segments');
                Route::post('/lists/{listId}/segments', [MailchimpController::class, 'createSegment'])->name('mailchimp.createSegment');

                // === PUSH/PULL CONTACTS ===
                Route::post('/lists/{listId}/push', [MailchimpController::class, 'pushContacts'])->name('mailchimp.pushContact');
                Route::post('/lists/{listId}/segments/{segmentId}/push', [MailchimpController::class, 'pushToSegment'])->name('mailchimp.pushToSegment');

                Route::get('/lists/pull', [MailchimpController::class, 'pullContacts'])->name('mailchimp.pullContacts');
            });

            Route::prefix('quickbooks')->group(function () {
                Route::get('/', [QuickbooksController::class, 'show'])->name('quickbooks');
                Route::delete('/', [QuickbooksController::class, 'destroy'])->name('quickbooks.destroy');
                Route::get('/connect', [QuickbooksController::class, 'connect'])->name('quickbooks.connect');
                Route::post('/import-customers', [QuickbooksController::class, 'importCustomers'])->name('quickbooks.import-customers');
            });
        });

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
