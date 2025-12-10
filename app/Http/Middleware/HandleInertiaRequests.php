<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Set the root template for the given request.
     */
    public function rootView(Request $request): string
    {
        // Use tenant.blade.php for tenant subdomains
        // Check if tenant() is available (after tenancy initialization)
        // OR check the host directly (before tenancy initialization)
        if (tenant() || $this->isTenantSubdomain($request)) {
            return 'tenant';
        }

        return parent::rootView($request);
    }

    /**
     * Check if the request is for a tenant subdomain (6-digit number).
     */
    protected function isTenantSubdomain(Request $request): bool
    {
        $host = $request->getHost();
        $parts = explode('.', $host);

        // Check if first part is a 6-digit number (tenant subdomain pattern)
        return count($parts) >= 2 && preg_match('/^\d{6}$/', $parts[0]);
    }

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'onTrial' => $user ? $user->onTrial() : false,
                'trialEndsAt' => $user && $user->onTrial()
                    ? $user->subscription('default')?->trial_ends_at?->format('M j, Y')
                    : null,
            ],
            'radar' => [
                'publishable' => config('services.radar.publishable'),
            ],
        ];
    }
}
