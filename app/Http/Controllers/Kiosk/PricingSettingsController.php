<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\PricingSetting;
use App\Support\PlanFeatureList;
use App\Support\PublicPageCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PricingSettingsController extends Controller
{
    public function edit(): Response
    {
        $section = PricingSetting::allTiersSection();
        $record = PricingSetting::query()->first();

        return Inertia::render('Kiosk/PricingSettings/Edit', [
            'allTiers' => $section,
            'settingsId' => $record?->id,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'features' => 'nullable|array',
            'features.*.title' => 'required|string|max:255',
            'features.*.description' => 'nullable|string|max:5000',
        ]);

        $record = PricingSetting::query()->first() ?? new PricingSetting;
        $record->all_tiers_included = [
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'] ?? '',
            'features' => PlanFeatureList::validateAndNormalize($validated['features'] ?? []),
        ];
        $record->save();

        PublicPageCache::forgetPricingPlans();

        return redirect()->route('kiosk.pricing-settings.edit')
            ->with('success', 'All tiers features updated.');
    }
}
