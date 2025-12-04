<?php

use App\Http\Controllers\Kiosk\CategoryController;
use App\Http\Controllers\Kiosk\DashboardController as KioskDashboardController;
use App\Http\Controllers\Kiosk\FaqController;
use App\Http\Controllers\Kiosk\PostController;
use App\Http\Controllers\Kiosk\TagController;
use App\Http\Controllers\Kiosk\UserController;
use App\Http\Controllers\Kiosk\PlansController;
use App\Http\Controllers\Kiosk\PlanItemsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Middleware\EnsureKioskAdmin;
use App\Http\Controllers\BlogController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Kiosk Subdomain Routes (kiosk.example.com)
Route::domain('kiosk.' . config('app.domain'))->middleware(['auth'])->name('kiosk.')->group(function () {
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

Route::get('/', [WelcomeController::class, 'index'])->name('home');
Route::get('/blog', [BlogController::class, 'index'])->name('blog');
Route::get('/blog/category', [BlogController::class, 'category'])->name('blogCategory');
Route::get('/blog/tag', [BlogController::class, 'tag'])->name('blogTag');
Route::get('/blog/{slug}', [BlogController::class, 'post'])->name('blogPostShow');

// Checkout Routes
// Route::middleware('guest')->group(function () {
    Route::get('/pricing', [CheckoutController::class, 'plans'])->name('checkout.plans');
// });

Route::middleware('auth')->group(function () {
    Route::get('/checkout/cart', [CheckoutController::class, 'cart'])->name('checkout.cart');
    Route::get('/checkout', [CheckoutController::class, 'checkout'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'process'])->name('checkout.process');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');
Route::post('/dashboard/switch-tenant', [DashboardController::class, 'switchTenant'])->middleware(['auth', 'verified'])->name('dashboard.switch-tenant');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
