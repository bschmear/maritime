<?php

namespace App\Http\Middleware;

use App\Models\AccountSettings;
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
        if (tenant() || $this->isTenantSubdomain($request)) {
            if (str_starts_with($request->path(), 'portal')) {
                return 'portal';
            }

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
        // Explicitly use the web guard so auth:customer never bleeds over.
        // onTrial/trialEndsAt are tenant account (Cashier) concerns, not customer portal concerns.
        $user = $request->user('web');

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
                'checkout_refresh' => fn () => $request->session()->get('checkout_refresh'),
                'delivery_fleet_conflicts' => fn () => $request->session()->get('delivery_fleet_conflicts'),
            ],
            'auth' => [
                'user' => $user,
                'customer' => fn () => $this->resolveCustomer($request),
                'onTrial' => $user ? $user->onTrial() : false,
                'trialEndsAt' => $user && $user->onTrial()
                    ? $user->subscription('default')?->trial_ends_at?->format('M j, Y')
                    : null,
            ],
            'radar' => [
                'publishable' => config('services.radar.publishable'),
            ],
            'pwa' => fn () => $this->rootView($request) === 'app' && $request->isPwa(),
        ];
    }

    protected function resolveCustomer(Request $request): ?array
    {
        if (! tenant()) {
            return null;
        }

        $contact = $request->user('customer');

        if (! $contact) {
            return null;
        }

        $contact->loadMissing(['customer.subsidiary']);

        $account = AccountSettings::getCurrent();
        $subsidiary = $contact->customer?->subsidiary;

        return array_merge(
            $contact->only('id', 'display_name', 'first_name', 'last_name', 'email'),
            [
                'portal_brand' => [
                    'account_logo_url' => $account->logo_url,
                    'subsidiary_display_name' => $subsidiary?->display_name,
                    'subsidiary_logo_url' => $subsidiary?->logo_url,
                ],
            ],
        );
    }
}
