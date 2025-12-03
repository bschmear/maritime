<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        using: function () {
            $host = request()->getHost();
            $parts = explode('.', $host);

            // Kiosk subdomain
            if (count($parts) >= 2 && $parts[0] === 'kiosk') {
                require base_path('routes/kiosk.php');
                return;
            }

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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
