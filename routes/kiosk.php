<?php

use App\Http\Controllers\Kiosk\CategoryController;
use App\Http\Controllers\Kiosk\DashboardController as KioskDashboardController;
use App\Http\Controllers\Kiosk\FaqController;
use App\Http\Controllers\Kiosk\PostController;
use App\Http\Controllers\Kiosk\TagController;
use App\Http\Controllers\Kiosk\UserController;
use App\Http\Controllers\Kiosk\PlansController;
use App\Http\Controllers\Kiosk\PlanItemsController;
use App\Http\Middleware\EnsureKioskAdmin;
use Illuminate\Support\Facades\Route;
// Kiosk Subdomain Routes (kiosk.example.com)

Route::domain(config('app.admin_url'))->middleware(['auth'])->name('kiosk.')->group(function () {
    
    Route::middleware([EnsureKioskAdmin::class])->group(function () {

        Route::get('/', [KioskDashboardController::class, 'index'])->name('dashboard');

        Route::resource('posts', PostController::class);
        Route::resource('categories', CategoryController::class);
        Route::resource('tags', TagController::class);
        Route::resource('faqs', FaqController::class);
        Route::resource('plans', PlansController::class);
        Route::resource('plan-items', PlanItemsController::class);


        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});


require __DIR__.'/auth.php';
