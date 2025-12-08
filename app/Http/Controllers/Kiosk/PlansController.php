<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\Plan;
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
        return Inertia::render('Kiosk/Plans/Create');
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
            'seat_extra' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'included' => 'nullable|array',
            'popular' => 'boolean',
            'active' => 'boolean',
        ]);

        // Ensure included is stored as JSON array
        if (!isset($validated['included'])) {
            $validated['included'] = [];
        }

        Plan::create($validated);

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
        return Inertia::render('Kiosk/Plans/Edit', [
            'plan' => $plan->load('items'),
        ]);
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
            'popular' => 'boolean',
            'active' => 'boolean',
        ]);

        // Ensure included is stored as JSON array
        if (!isset($validated['included'])) {
            $validated['included'] = [];
        }

        $plan->update($validated);

        return redirect()->route('kiosk.plans.index')
            ->with('success', 'Plan updated successfully.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $plan->delete();

        return redirect()->route('kiosk.plans.index')
            ->with('success', 'Plan deleted successfully.');
    }
}
