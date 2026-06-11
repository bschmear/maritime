<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\BoatMake\Models\BoatMake;
use App\Domain\Location\Models\Location;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Enums\Locations\LocationType;
use App\Enums\Timezone;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountSettings;
use App\Services\Dashboard\TenantDashboardDataService;
use App\Support\Dashboard\DashboardFilterOptions;
use App\Support\Dashboard\DashboardFilters;
use App\Support\ManufacturerCatalog;
use App\Tenancy\CurrentTenantProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private TenantDashboardDataService $tenantDashboardData,
        private CurrentTenantProfile $tenantProfile,
    ) {}

    public function index(Request $request): Response
    {
        // Get the account from the request (set by EnsureTenantAccess middleware)
        $account = $request->get('tenant_account');

        // If not set, fetch it manually
        // Account model uses 'pgsql' connection (central/public schema) by default
        if (! $account) {
            $tenant = tenant();
            $account = Account::where('tenant_id', $tenant->id)
                ->with(['owner', 'users'])
                ->first();
        }

        $tenantUser = $this->tenantProfile->profile();
        $dashboardFilters = DashboardFilters::fromRequest($request, $tenantUser);
        $dashboard = $this->tenantDashboardData->build($request, $dashboardFilters);

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
            'location_types' => LocationType::options(),
            'locations' => Location::query()
                ->whereHas('subsidiaries')
                ->with(['subsidiaries:id,display_name'])
                ->orderBy('display_name')
                ->get(['id', 'display_name', 'location_type', 'address_line_1', 'city', 'state', 'postal_code'])
                ->map(function (Location $location) {
                    $typeLabel = collect(LocationType::options())
                        ->firstWhere('id', (int) $location->location_type)['name'] ?? null;

                    $addressParts = array_filter([
                        $location->address_line_1,
                        collect([$location->city, $location->state, $location->postal_code])
                            ->filter()
                            ->join(', '),
                    ]);

                    return [
                        'id' => (int) $location->id,
                        'display_name' => (string) $location->display_name,
                        'location_type_label' => $typeLabel,
                        'subsidiary_labels' => $location->subsidiaries
                            ->pluck('display_name')
                            ->join(', '),
                        'address_summary' => $addressParts !== [] ? implode(', ', $addressParts) : null,
                    ];
                })
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
            'dashboardFilters' => $dashboardFilters->toArray(),
            'dashboardFilterOptions' => DashboardFilterOptions::build(),
            'onboarding' => $onboarding,
        ]);
    }

    public function storeFilters(Request $request): RedirectResponse
    {
        $tenantUser = $this->tenantProfile->profile();
        if ($tenantUser === null) {
            abort(403);
        }

        $validated = $request->validate([
            'subsidiary_id' => ['nullable', 'integer', 'exists:subsidiaries,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ]);

        $filters = DashboardFilters::validated(
            isset($validated['subsidiary_id']) ? (int) $validated['subsidiary_id'] : null,
            isset($validated['location_id']) ? (int) $validated['location_id'] : null,
        );

        $tenantUser->preferred_subsidiary_id = $filters->subsidiaryId;
        $tenantUser->preferred_location_id = $filters->locationId;
        $tenantUser->save();

        return redirect()->route('dashboard', array_filter([
            'subsidiary_id' => $filters->subsidiaryId,
            'location_id' => $filters->locationId,
            'onboarding' => $request->query('onboarding'),
        ], fn ($value) => $value !== null && $value !== ''));
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
