<?php

use App\Http\Controllers\Kiosk\AccountController as KioskAccountController;
use App\Http\Controllers\Kiosk\CategoryController;
use App\Http\Controllers\Kiosk\DashboardController as KioskDashboardController;
use App\Http\Controllers\Kiosk\FaqController;
use App\Http\Controllers\Kiosk\PlanItemsController;
use App\Http\Controllers\Kiosk\PlansController;
use App\Http\Controllers\Kiosk\PostController;
use App\Http\Controllers\Kiosk\PricingSettingsController;
use App\Http\Controllers\Kiosk\TagController;
use App\Http\Controllers\Kiosk\UserController;
use App\Http\Middleware\EnsureKioskAdmin;
use Illuminate\Support\Facades\Route;

// Kiosk Subdomain Routes (kiosk.example.com)

Route::domain(config('app.admin_url'))->middleware(['auth'])->name('kiosk.')->group(function () {

    Route::middleware([EnsureKioskAdmin::class])->group(function () {

        Route::get('/', [KioskDashboardController::class, 'index'])->name('dashboard');

        Route::post('posts/cover-image', [PostController::class, 'uploadCover'])->name('posts.upload-cover');
        Route::resource('posts', PostController::class);
        Route::resource('categories', CategoryController::class);
        Route::resource('tags', TagController::class);
        Route::resource('faqs', FaqController::class);
        Route::resource('plans', PlansController::class);
        Route::resource('plan-items', PlanItemsController::class);
        Route::get('pricing-settings', [PricingSettingsController::class, 'edit'])->name('pricing-settings.edit');
        Route::put('pricing-settings', [PricingSettingsController::class, 'update'])->name('pricing-settings.update');

        Route::get('accounts', [KioskAccountController::class, 'index'])->name('accounts.index');
        Route::get('accounts/{account}', [KioskAccountController::class, 'show'])->name('accounts.show');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/roles', [UserController::class, 'attachRole'])->name('users.roles.store');
        Route::delete('users/{user}/kiosk-access', [UserController::class, 'removeKioskAccess'])->name('users.kiosk-access.destroy');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // Route::prefix('support')->group(function () {
    //     Route::get('/', [App\Http\Controllers\Kiosk\SupportController::class, 'index'])->name('kioskSupport');
    //     Route::prefix('tickets')->group(function () {
    //         Route::get('/', [App\Http\Controllers\Kiosk\TicketsController::class, 'index'])->name('tickets.index');
    //         Route::post('/', [App\Http\Controllers\Kiosk\TicketsController::class, 'store'])->name('tickets.store');
    //         Route::get('/show', [App\Http\Controllers\Kiosk\TicketsController::class, 'show'])->name('tickets.show');
    //         Route::get('/create', [App\Http\Controllers\Kiosk\TicketsController::class, 'create'])->name('tickets.create');
    //         Route::get('/edit', [App\Http\Controllers\Kiosk\TicketsController::class, 'edit'])->name('tickets.edit');
    //         Route::put('/update', [App\Http\Controllers\Kiosk\TicketsController::class, 'update'])->name('tickets.update');
    //         Route::post('/ticket-responses', [App\Http\Controllers\Kiosk\TicketResponseController::class, 'store'])->name('ticket-responses.store');

    //     });
    // });

});

require __DIR__.'/auth.php';
