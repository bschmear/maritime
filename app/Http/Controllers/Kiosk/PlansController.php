<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Support\PlanFeatureList;
use App\Support\PublicPageCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlansController extends Controller
{
    public function index(): Response
    {
        $plans = Plan::withCount('items')->latest()->paginate(15);

        return Inertia::render('Kiosk/Plans/Index', [
            'plans' => $plans,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Kiosk/Plans/Create', $this->planFormProps());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'monthly_price' => 'nullable|numeric|min:0|max:999999.99',
            'yearly_price' => 'nullable|numeric|min:0|max:999999.99',
            'stripe_monthly_id' => 'nullable|string|max:255',
            'stripe_yearly_id' => 'nullable|string|max:255',
            'seat_limit' => 'required|integer|min:1',
            'seat_extra' => 'nullable|numeric|min:0|max:999999.99',
            'description' => 'nullable|string',
            'included' => 'nullable|array',
            'included.*.title' => 'required|string|max:255',
            'included.*.description' => 'nullable|string|max:5000',
            'popular' => 'boolean',
            'active' => 'boolean',
            'ticket_support_access' => 'boolean',
            'coming_soon' => 'boolean',
        ]);

        $validated['included'] = PlanFeatureList::validateAndNormalize($validated['included'] ?? []);
        $validated['coming_soon'] = $request->boolean('coming_soon');

        Plan::create($validated);

        PublicPageCache::forgetPricingPlans();

        return redirect()->route('kiosk.plans.index')
            ->with('success', 'Plan created successfully.');
    }

    public function show(Plan $plan): Response
    {
        return Inertia::render('Kiosk/Plans/Show', [
            'plan' => $plan->load('items'),
        ]);
    }

    public function edit(Plan $plan): Response
    {
        $plan->load('items');
        $plan->setAttribute('included', PlanFeatureList::normalize($plan->included));

        return Inertia::render('Kiosk/Plans/Edit', array_merge(
            $this->planFormProps($plan),
            ['plan' => $plan],
        ));
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'monthly_price' => 'nullable|numeric|min:0|max:999999.99',
            'yearly_price' => 'nullable|numeric|min:0|max:999999.99',
            'stripe_monthly_id' => 'nullable|string|max:255',
            'stripe_yearly_id' => 'nullable|string|max:255',
            'seat_limit' => 'required|integer|min:1',
            'seat_extra' => 'nullable|numeric|min:0|max:999999.99',
            'description' => 'nullable|string',
            'included' => 'nullable|array',
            'included.*.title' => 'required|string|max:255',
            'included.*.description' => 'nullable|string|max:5000',
            'popular' => 'boolean',
            'active' => 'boolean',
            'ticket_support_access' => 'boolean',
            'coming_soon' => 'boolean',
        ]);

        $validated['included'] = PlanFeatureList::validateAndNormalize($validated['included'] ?? []);
        $validated['coming_soon'] = $request->boolean('coming_soon');

        $plan->update($validated);

        PublicPageCache::forgetPricingPlans();

        return redirect()->route('kiosk.plans.index')
            ->with('success', 'Plan updated successfully.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $plan->delete();

        PublicPageCache::forgetPricingPlans();

        return redirect()->route('kiosk.plans.index')
            ->with('success', 'Plan deleted successfully.');
    }

    /**
     * @return array{otherPlans: array<int, array{id: int, name: string, included: array<int, array{title: string, description: string}>}>}
     */
    private function planFormProps(?Plan $exclude = null): array
    {
        $otherPlans = Plan::query()
            ->when($exclude, fn ($query) => $query->where('id', '!=', $exclude->id))
            ->orderBy('name')
            ->get(['id', 'name', 'included'])
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'included' => PlanFeatureList::normalize($plan->included),
            ])
            ->values()
            ->all();

        return ['otherPlans' => $otherPlans];
    }
}
