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
            } else {
                $this->mapWebRoutes();
            }
            
            // API routes if needed
            // $this->mapApiRoutes();
        });
    }


    protected function mapWebRoutes()
    {
        Route::middleware(['web'])
             ->group(base_path('routes/web.php'));
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
        }  elseif (count($parts) === 2 || (count($parts) === 3 && $parts[0] === 'www')) {
            return 'web';
        } else {
            return 'web';
        }
    }

}
