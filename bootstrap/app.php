<?php

use App\Http\Middleware\ClearPwaModeCookie;
use App\Http\Middleware\EnsureActiveWorkspaceSubscription;
use App\Http\Middleware\EnsureCentralEmailVerified;
use App\Http\Middleware\EnsureKioskAdmin;
use App\Http\Middleware\EnsureKioskDomain;
use App\Http\Middleware\EnsureTenantAccess;
use App\Http\Middleware\EnsureTicketSupportAccess;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectUnauthenticatedFromTenant;
use App\Http\Middleware\ValidatePortalToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        using: function () {
            $host = request()->getHost();
            $parts = explode('.', $host);

            // Kiosk subdomain
            // if (count($parts) >= 2 && $parts[0] === 'kiosk') {
            //     require base_path('routes/web.php');
            //     return;
            // }

            $helpPortalHost = config('app.help_portal_host');

            // Documentation portal (HELP_PORTAL host)
            if ($helpPortalHost && $host === $helpPortalHost) {
                Route::middleware('web')
                    ->name('docs.')
                    ->group(base_path('routes/documentation.php'));

                return;
            }

            // Tenant subdomain (6-digit): CRM + portal routes register together so named routes
            // like portal.* resolve when rendering emails/links from the tenant app.
            if (count($parts) >= 2 && preg_match('/^\d{6}$/', $parts[0])) {
                require base_path('routes/portal.php');
                require base_path('routes/tenant.php');

                Route::middleware(['web', 'auth', 'tenant.access'])
                    ->prefix('api')
                    ->group(base_path('routes/api.php'));

                return;
            }

            // Default web routes
            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        },
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            ClearPwaModeCookie::class,
            HandleInertiaRequests::class,
            EnsureCentralEmailVerified::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
            'stripe/connect-webhook',
        ]);
        $middleware->alias([
            'kiosk.domain' => EnsureKioskDomain::class,
            'kiosk.admin' => EnsureKioskAdmin::class,
            'tenant.access' => EnsureTenantAccess::class,
            'workspace.subscription' => EnsureActiveWorkspaceSubscription::class,
            'redirect.unauthenticated' => RedirectUnauthenticatedFromTenant::class,
            'portal.token' => ValidatePortalToken::class,
            'ticket.support' => EnsureTicketSupportAccess::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            $host = $request->getHost();
            $parts = explode('.', $host);
            $isTenant = count($parts) >= 2 && preg_match('/^\d{6}$/', $parts[0]);

            if ($isTenant && str_starts_with($request->path(), 'vendor/portal')) {
                return '/vendor/portal/login';
            }

            if ($isTenant && str_starts_with($request->path(), 'portal')) {
                return '/portal/login';
            }

            if ($isTenant) {
                return config('app.url').'/login';
            }

            return route('login');
        });

        $middleware->redirectUsersTo(function (Request $request) {
            // Customer/vendor guards use tenant `contacts`; session keys can persist on the central
            // host after visiting a workspace portal — never resolve those users here or we hit pgsql.
            $host = $request->getHost();
            $parts = explode('.', $host);
            $isTenantSubdomain = count($parts) >= 2 && preg_match('/^\d{6}$/', $parts[0]);

            if ($isTenantSubdomain) {
                if ($request->user('vendor')) {
                    return '/vendor/portal';
                }

                if ($request->user('customer')) {
                    return '/portal';
                }
            }

            $user = $request->user('web');
            if ($user && $user->email_verified_at === null) {
                return route('verification.notice', absolute: false);
            }

            if ($request->isPwa() && $user) {
                return '/dashboard?pwa=1';
            }

            return '/';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
