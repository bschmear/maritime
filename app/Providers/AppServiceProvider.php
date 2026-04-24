<?php

namespace App\Providers;

use App\Domain\EmailTemplate\Models\EmailTemplate as TenantEmailTemplate;
use App\Tenancy\CurrentTenantProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentTenantProfile::class, function () {
            return new CurrentTenantProfile;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Request::macro('isPwa', function () {
            /** @var \Illuminate\Http\Request $this */
            if ($this->boolean('pwa')) {
                return true;
            }

            return $this->cookie('pwa_mode') === '1';
        });

        Route::bind('email_template', function (string $value) {
            return TenantEmailTemplate::query()->findOrFail($value);
        });
    }
}
