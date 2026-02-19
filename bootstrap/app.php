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
                require base_path('routes/tenant.php');
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
        ]);

        // When an unauthenticated user hits a tenant subdomain, send them to the
        // main domain instead of trying to generate route('login') which doesn't
        // exist in the tenant routing context.
        $middleware->redirectGuestsTo(function (Request $request) {
            $host = $request->getHost();
            $parts = explode('.', $host);

            if (count($parts) >= 2 && preg_match('/^\d{6}$/', $parts[0])) {
                return config('app.url') . '/login';
            }

            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
