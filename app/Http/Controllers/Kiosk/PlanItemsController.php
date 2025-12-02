<?php
namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\PlanItem;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanItemsController extends Controller
{
    public function index(): Response
    {
        $planitems = PlanItem::with('plan')->latest()->paginate(15);

        return Inertia::render('Kiosk/PlanItems/Index', [
            'planitems' => $planitems,
        ]);
    }

    public function create(): Response
    {
        $plans = Plan::all();

        return Inertia::render('Kiosk/PlanItems/Create', [
            'plans' => $plans,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'name' => 'required|string|max:255',
            'stripe_price_id' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0|max:999999.99',
        ]);

        PlanItem::create($validated);

        return redirect()->route('kiosk.planitems.index')
            ->with('success', 'Plan Item created successfully.');
    }

    public function show(PlanItem $planitem): Response
    {
        return Inertia::render('Kiosk/PlanItems/Show', [
            'planitem' => $planitem->load('plan'),
        ]);
    }

    public function edit(PlanItem $planitem): Response
    {
        $plans = Plan::all();

        return Inertia::render('Kiosk/PlanItems/Edit', [
            'planitem' => $planitem->load('plan'),
            'plans' => $plans,
        ]);
    }

    public function update(Request $request, PlanItem $planitem): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'name' => 'required|string|max:255',
            'stripe_price_id' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0|max:999999.99',
        ]);

        $planitem->update($validated);

        return redirect()->route('kiosk.planitems.index')
            ->with('success', 'Plan Item updated successfully.');
    }

    public function destroy(PlanItem $planitem): RedirectResponse
    {
        $planitem->delete();

        return redirect()->route('kiosk.planitems.index')
            ->with('success', 'Plan Item deleted successfully.');
    }
}
