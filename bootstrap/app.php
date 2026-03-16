<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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

            // Tenant subdomain (6-digit)
            if (count($parts) >= 2 && preg_match('/^\d{6}$/', $parts[0])) {
                $path = request()->path();

                // Portal routes (customer-facing) get their own isolated route file
                if ($path === 'portal' || str_starts_with($path, 'portal/')) {
                    require base_path('routes/portal.php');
                } else {
                    require base_path('routes/tenant.php');
                }

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
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
        $middleware->alias([
            'kiosk.domain' => \App\Http\Middleware\EnsureKioskDomain::class,
            'kiosk.admin' => \App\Http\Middleware\EnsureKioskAdmin::class,
            'tenant.access' => \App\Http\Middleware\EnsureTenantAccess::class,
            'redirect.unauthenticated' => \App\Http\Middleware\RedirectUnauthenticatedFromTenant::class,
            'portal.token' => \App\Http\Middleware\ValidatePortalToken::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            $host = $request->getHost();
            $parts = explode('.', $host);
            $isTenant = count($parts) >= 2 && preg_match('/^\d{6}$/', $parts[0]);

            if ($isTenant && str_starts_with($request->path(), 'portal')) {
                return '/portal/login';
            }

            if ($isTenant) {
                return config('app.url') . '/login';
            }

            return route('login');
        });

        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->user('customer')) {
                return '/portal';
            }
            return '/';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
