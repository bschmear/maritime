<?php

namespace App\Providers;

use App\Domain\EmailTemplate\Models\EmailTemplate as TenantEmailTemplate;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Models\Post;
use App\Models\User;
use App\Observers\PostObserver;
use App\Policies\WarrantyClaimPolicy;
use App\Services\WorkspaceNavCache;
use App\Services\WorkspacePlanCache;
use App\Tenancy\CurrentTenantProfile;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('inbound-email', function () {
            $config = config('inbound_email.rate_limit', []);

            return Limit::perMinute((int) ($config['max_attempts'] ?? 120));
        });

        Post::observe(PostObserver::class);

        Gate::policy(WarrantyClaim::class, WarrantyClaimPolicy::class);

        RedirectIfAuthenticated::redirectUsing(function (Request $request): string {
            $host = $request->getHost();
            $parts = explode('.', $host);
            $isTenantSubdomain = count($parts) >= 2 && preg_match('/^\d{6}$/', $parts[0]);

            if ($isTenantSubdomain) {
                if ($request->user('vendor')) {
                    return route('vendor.portal.index');
                }

                if ($request->user('customer')) {
                    return route('portal.index');
                }
            }

            foreach (['dashboard', 'home'] as $name) {
                if (Route::has($name)) {
                    return route($name);
                }
            }

            return '/';
        });

        Vite::prefetch(concurrency: 3);

        Request::macro('isPwa', function () {
            /** @var Request $this */
            if ($this->query('pwa') === '0') {
                return false;
            }

            if ($this->boolean('pwa')) {
                return true;
            }

            return $this->cookie('pwa_mode') === '1';
        });

        Route::bind('email_template', function (string $value) {
            return TenantEmailTemplate::query()->findOrFail($value);
        });

        Event::listen(Login::class, function (Login $event): void {
            if ($event->guard !== 'web' || ! $event->user instanceof User) {
                return;
            }

            WorkspaceNavCache::forgetUser($event->user);
            WorkspaceNavCache::put($event->user);
            WorkspacePlanCache::forget();
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->guard !== 'web') {
                return;
            }

            if ($event->user instanceof User) {
                WorkspaceNavCache::forgetUser($event->user);
            }

            WorkspacePlanCache::forget();
        });
    }
}
