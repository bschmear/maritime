<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{

    protected $namespace = 'App\Http\Controllers';
    public const HOME = '/settings';

    public function boot()
    {
        parent::boot();

        $this->routes(function (Request $request) {
            $domainType = $this->getSubDomainType($request);

            if($domainType == 'kiosk') {
                $this->mapAdminRoutes();
            } else if($domainType == 'tenant') {
                $this->mapTenantRoutes();
            } else {
                $this->mapWebRoutes();
            }
            
        });
    }


    protected function mapWebRoutes()
    {
        Route::middleware(['web'])
             ->group(base_path('routes/web.php'));
    }

    protected function mapTenantRoutes()
    {
        // Register kiosk routes with domain constraint
        Route::domain('{tenantid}.{domain}')
             ->middleware(['web', 'auth'])
             ->group(base_path('routes/tenant.php'));
    }

    protected function mapAdminRoutes()
    {
        // Register kiosk routes with domain constraint
        Route::domain('kiosk.{domain}')
             ->middleware(['web', 'auth'])
             ->group(base_path('routes/kiosk.php'));
    }

    protected function mapApiRoutes()
    {
        Route::prefix('api')
             ->middleware('api')
             ->group(base_path('routes/api.php'));
    }



    protected function getSubDomainType($request)
    {
        $host = $request->getHost();
        $parts = explode('.', $host);

        // Check if the subdomain is 'admin'
        if (count($parts) >= 2 && $parts[0] === 'kiosk') {
            return 'kiosk';
        } elseif (count($parts) >= 2 && preg_match('/^\d{6}$/', $parts[0])) {

            return 'tenant';
        }  elseif (count($parts) === 2 || (count($parts) === 3 && $parts[0] === 'www')) {

            return 'web';
        } else {
            return 'web';
        }
    }

}
