<?php

namespace App\Http\Middleware;

use App\Domain\Delivery\Models\Delivery;
use App\Models\AccountSettings;
use App\Services\TenantStaffResolver;
use App\Services\WorkspaceNavCache;
use App\Services\WorkspacePlanCache;
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
        if ($this->isHelpPortalHost($request)) {
            return 'documentation';
        }

        if (tenant() || $this->isTenantSubdomain($request)) {
            if (str_starts_with($request->path(), 'portal') || str_starts_with($request->path(), 'vendor/portal')) {
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

    protected function isHelpPortalHost(Request $request): bool
    {
        $host = config('app.help_portal_host');

        return $host && $request->getHost() === $host;
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
        if ($this->isHelpPortalHost($request)) {
            return [
                ...parent::share($request),
                'app' => [
                    'name' => config('app.name'),
                ],
                'helpNav' => fn () => \App\Services\Help\HelpCategoryTree::toNavArray(
                    \App\Services\Help\HelpCategoryTree::forPortal()
                ),
                'docSearchIndex' => fn () => \App\Services\Help\HelpArticleSearch::index(),
            ];
        }

        // Explicitly use the web guard so auth:customer never bleeds over.
        // onTrial/trialEndsAt are tenant account (Cashier) concerns, not customer portal concerns.
        $user = $request->user('web');

        $trialEndsAt = null;
        if ($user) {
            if ($user->onGenericTrial()) {
                $trialEndsAt = $user->trial_ends_at?->format('M j, Y');
            }
            if ($trialEndsAt === null) {
                $trialSubscription = $user->subscriptions->first(function ($sub) {
                    return $sub->valid() && $sub->onTrial();
                });
                $trialEndsAt = $trialSubscription?->trial_ends_at?->format('M j, Y');
            }
        }

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
                'vendor' => fn () => $this->resolveVendor($request),
                'onTrial' => $trialEndsAt !== null,
                'trialEndsAt' => $trialEndsAt,
            ],
            'radar' => [
                'publishable' => config('services.radar.publishable'),
            ],
            'pwa' => fn () => $this->rootView($request) === 'app' && $request->isPwa(),
            'workspace_nav' => fn () => $this->workspaceNavAccounts($request),
            'workspace_plan' => fn () => tenant() ? WorkspacePlanCache::get() : null,
            'tenant_sandbox_mode' => fn () => tenant() ? (bool) AccountSettings::getCurrent()->sandbox_mode : false,
            'delivery_en_route_banner' => fn () => $this->deliveryEnRouteBanner($request),
        ];
    }

    /**
     * When the logged-in central user matches a tenant staff row marked in-progress,
     * surface the active en-route delivery for a global "View delivery" strip.
     *
     * @return array{delivery_id: int, title: string, url: string}|null
     */
    protected function deliveryEnRouteBanner(Request $request): ?array
    {
        if (! tenant() || $this->rootView($request) !== 'tenant') {
            return null;
        }

        $central = $request->user('web');
        if (! $central) {
            return null;
        }

        $tenantStaff = TenantStaffResolver::tenantStaffForWebUser($central);
        if (! $tenantStaff || ! $tenantStaff->delivery_in_progress) {
            return null;
        }

        $delivery = Delivery::query()
            ->where('technician_id', $tenantStaff->id)
            ->where('status', 'en_route')
            ->orderByDesc('en_route_at')
            ->first();

        if (! $delivery) {
            $tenantStaff->forceFill(['delivery_in_progress' => false])->saveQuietly();

            return null;
        }

        return [
            'delivery_id' => $delivery->id,
            'title' => $delivery->display_name,
            'url' => route('deliveries.show', $delivery->id),
        ];
    }

    /**
     * Accounts the central user can open in the tenant app (member + provisioned tenant + active subscription).
     * Host URLs are built on the client with {@see \App\Services\WorkspaceNavCache} (cached; same rules as the CRM entry list).
     *
     * @return array<int, array{id: int, name: string, domain: string}>
     */
    protected function workspaceNavAccounts(Request $request): array
    {
        if (tenant() || $this->isTenantSubdomain($request)) {
            return [];
        }

        $user = $request->user('web');
        if (! $user) {
            return [];
        }

        return WorkspaceNavCache::get($user);
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

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveVendor(Request $request): ?array
    {
        if (! tenant()) {
            return null;
        }

        $contact = $request->user('vendor');

        if (! $contact) {
            return null;
        }

        $account = AccountSettings::getCurrent();

        return array_merge(
            $contact->only('id', 'display_name', 'first_name', 'last_name', 'email'),
            [
                'portal_brand' => [
                    'account_logo_url' => $account->logo_url,
                    'subsidiary_display_name' => null,
                    'subsidiary_logo_url' => null,
                ],
            ],
        );
    }
}
