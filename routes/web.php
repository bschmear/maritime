<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FaviconController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\Kiosk\CategoryController;
use App\Http\Controllers\Kiosk\DashboardController as KioskDashboardController;
use App\Http\Controllers\Kiosk\FaqController;
use App\Http\Controllers\Kiosk\HelpArticleController;
use App\Http\Controllers\Kiosk\HelpCategoryController;
use App\Http\Controllers\Kiosk\PlanItemsController;
use App\Http\Controllers\Kiosk\PlansController;
use App\Http\Controllers\Kiosk\PricingSettingsController;
use App\Http\Controllers\Kiosk\PostController;
use App\Http\Controllers\Kiosk\SupportTicketsController;
use App\Http\Controllers\Kiosk\TagController;
use App\Http\Controllers\Kiosk\UserController;
use App\Http\Controllers\MailchimpOAuthController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuickBooksOAuthController;
use App\Http\Controllers\StripeConnectWebhookController;
use App\Http\Controllers\WelcomeController;
use App\Http\Middleware\EnsureKioskAdmin;
use Illuminate\Support\Facades\Route;

Route::get('favicon.ico', FaviconController::class);

// Kiosk Subdomain Routes (kiosk.example.com)
Route::domain('kiosk.'.config('app.domain'))->middleware(['auth'])->name('kiosk.')->group(function () {
    Route::middleware([EnsureKioskAdmin::class])->group(function () {
        Route::get('/', [KioskDashboardController::class, 'index'])->name('dashboard');

        Route::post('posts/cover-image', [PostController::class, 'uploadCover'])->name('posts.upload-cover');
        Route::resource('posts', PostController::class);
        Route::resource('categories', CategoryController::class);
        Route::resource('tags', TagController::class);
        Route::resource('faqs', FaqController::class);
        Route::resource('help-categories', HelpCategoryController::class);
        Route::get('help-articles/siblings', [HelpArticleController::class, 'siblings'])->name('help-articles.siblings');
        Route::post('help-articles/reorder', [HelpArticleController::class, 'reorder'])->name('help-articles.reorder');
        Route::resource('help-articles', HelpArticleController::class);
        Route::resource('support-tickets', SupportTicketsController::class)->only(['index', 'show', 'update']);
        Route::post('support-tickets/{support_ticket}/responses', [SupportTicketsController::class, 'storeResponse'])
            ->name('support-tickets.responses.store');
        Route::resource('plans', PlansController::class);
        Route::resource('plan-items', PlanItemsController::class);
        Route::get('pricing-settings', [PricingSettingsController::class, 'edit'])->name('pricing-settings.edit');
        Route::put('pricing-settings', [PricingSettingsController::class, 'update'])->name('pricing-settings.update');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});

Route::post('/stripe/connect-webhook', StripeConnectWebhookController::class)
    ->name('stripe.connect.webhook');

// Single Mailchimp OAuth redirect for all tenants (MAILCHIMP_REDIRECT_URI must point here).
Route::get('/integrations/mailchimp/oauth/callback', [MailchimpOAuthController::class, 'callback'])
    ->name('mailchimp.oauth.callback');

// Single QuickBooks Online OAuth redirect for all tenants (QUICKBOOKS_REDIRECT_URI must point here).
Route::get('/quickbooks/callback', [QuickBooksOAuthController::class, 'callback'])
    ->name('quickbooks.oauth.callback');

Route::get('/', [WelcomeController::class, 'index'])->name('home');
Route::get('/blog', [BlogController::class, 'index'])->name('blog');
Route::get('/blog/category', [BlogController::class, 'category'])->name('blogCategory');
Route::get('/blog/tag', [BlogController::class, 'tag'])->name('blogTag');
Route::get('/blog/{slug}', [BlogController::class, 'post'])->name('blogPostShow');

Route::get('/faq', [PageController::class, 'faq'])->name('faq');
// Route::get('/help-center', [PageController::class, 'help-center'])->name('help-center');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'contactStore'])
    ->middleware('throttle:10,1')
    ->name('contact.store');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/features', [PageController::class, 'features'])->name('features');
Route::get('/features/boat-shows', [PageController::class, 'featuresBoatShows'])->name('features.boat-shows');
Route::get('/features/service-department', [PageController::class, 'featuresServiceDepartment'])->name('features.service-department');
Route::get('/features/performance-tracking', [PageController::class, 'featuresPerformanceTracking'])->name('features.performance-tracking');
Route::get('/features/delivery-system', [PageController::class, 'featuresDeliverySystem'])->name('features.delivery-system');
Route::get('/features/smart-surveys', [PageController::class, 'featuresSmartSurveys'])->name('features.smart-surveys');
Route::get('/features/stripe-payments', [PageController::class, 'featuresStripePayments'])->name('features.stripe-payments');
Route::get('/features/mailchimp', [PageController::class, 'featuresMailchimp'])->name('features.mailchimp');
Route::get('/features/quickbooks', [PageController::class, 'featuresQuickbooks'])->name('features.quickbooks');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy-policy');

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

// Account Management Routes
Route::middleware(['auth', 'verified'])
    ->prefix('accounts')
    ->name('accounts.')
    ->group(function () {

        // User routes FIRST
        Route::post('{account}/users', [AccountController::class, 'inviteUser'])->name('users.invite');
        Route::delete('{account}/users/{user}', [AccountController::class, 'removeUser'])->name('users.destroy');
        Route::patch('{account}/users/{user}/role', [AccountController::class, 'updateUserRole'])->name('users.update-role');

        Route::post('{account}/switch-plan', [AccountController::class, 'switchPlan'])->name('switch-plan');
        Route::post('{account}/cancel', [AccountController::class, 'cancelSubscription'])->name('cancel');

        Route::get('{account}', [AccountController::class, 'show'])->name('show');
    });
// Invitation Routes
Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');
    Route::post('invitations/{token}/decline', [InvitationController::class, 'decline'])->name('invitations.decline');

    // Account owner invitation management
    Route::post('invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
    Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
