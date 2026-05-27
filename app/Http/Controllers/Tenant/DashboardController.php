<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\BoatMake\Models\BoatMake;
use App\Domain\Location\Models\Location;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Enums\Timezone;
use App\Http\Controllers\Controller;
use App\Models\AccountSettings;
use App\Services\Dashboard\TenantDashboardDataService;
use App\Support\ManufacturerCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private TenantDashboardDataService $tenantDashboardData
    ) {}

    public function index(Request $request): Response
    {
        // Get the account from the request (set by EnsureTenantAccess middleware)
        $account = $request->get('tenant_account');

        // If not set, fetch it manually
        // Account model uses 'pgsql' connection (central/public schema) by default
        if (! $account) {
            $tenant = tenant();
            $account = \App\Models\Account::where('tenant_id', $tenant->id)
                ->with(['owner', 'users'])
                ->first();
        }

        $dashboard = $this->tenantDashboardData->build($request);

        $settings = AccountSettings::getCurrent();
        $initialStep = $this->resolveOnboardingInitialStep($settings);
        if ($request->query('onboarding') === 'stripe-return' && ! $settings->onboarding_complete) {
            $initialStep = 4;
        }
        $onboarding = [
            'complete' => (bool) $settings->onboarding_complete,
            'initial_step' => $initialStep,
            'subsidiaries' => Subsidiary::query()
                ->orderBy('display_name')
                ->get(['id', 'display_name'])
                ->map(fn ($s) => [
                    'id' => (int) $s->id,
                    'label' => (string) $s->display_name,
                ])
                ->values()
                ->all(),
            'manufacturers' => ManufacturerCatalog::entries(),
            'existingBrandKeys' => BoatMake::query()
                ->whereNotNull('brand_key')
                ->pluck('brand_key')
                ->all(),
            'timezones' => Timezone::options(),
            'stripe_connect_from_onboarding_url' => route('stripe.connect', ['from' => 'onboarding']),
            'default_timezone' => $settings->timezone ?? 'America/Chicago',
            'default_brand_color' => $settings->brand_color ?? '#3B82F6',
            'stripe_just_returned' => $request->query('onboarding') === 'stripe-return',
        ];

        return Inertia::render('Tenant/Dashboard', [
            'account' => $account ? [
                'id' => $account->id,
                'name' => $account->name,
                'owner' => $account->owner ? [
                    'id' => $account->owner->id,
                    'name' => $account->owner->name,
                    'email' => $account->owner->email,
                ] : null,
                'users_count' => $account->users()->count(),
            ] : null,
            'dashboard' => $dashboard,
            'onboarding' => $onboarding,
        ]);
    }

    private function resolveOnboardingInitialStep(AccountSettings $settings): int
    {
        if ($settings->onboarding_complete) {
            return 1;
        }

        if (Subsidiary::query()->count() === 0) {
            return 1;
        }

        if (! Location::query()->whereHas('subsidiaries')->exists()) {
            return 2;
        }

        return 3;
    }
}
