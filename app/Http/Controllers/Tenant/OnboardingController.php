<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\PublicStorage;
use App\Domain\BoatMake\Actions\CreateBoatMake as CreateBoatMakeAction;
use App\Domain\BoatMake\Models\BoatMake as BoatMakeRecord;
use App\Domain\Location\Actions\CreateLocation as CreateLocationAction;
use App\Domain\Location\Models\Location;
use App\Domain\Subsidiary\Actions\CreateSubsidiary as CreateSubsidiaryAction;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Enums\Locations\LocationType;
use App\Enums\Timezone;
use App\Models\AccountSettings;
use App\Support\ManufacturerCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;

class OnboardingController extends BaseController
{
    public function storeSubsidiary(Request $request, CreateSubsidiaryAction $createSubsidiary): RedirectResponse
    {
        $this->ensureOnboardingIncomplete();

        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $result = $createSubsidiary(array_merge($data, [
            'inactive' => false,
        ]));

        if (! ($result['success'] ?? false) || empty($result['record'])) {
            return back()->withErrors(['display_name' => $result['message'] ?? 'Could not create subsidiary.']);
        }

        return back()->with('onboarding_subsidiary_id', $result['record']->id);
    }

    public function storeLocation(Request $request, CreateLocationAction $createLocation): RedirectResponse
    {
        $this->ensureOnboardingIncomplete();

        $locationTypeIds = array_column(LocationType::options(), 'id');

        $validated = $request->validate([
            'subsidiary_id' => ['required', 'integer', 'exists:subsidiaries,id'],
            'display_name' => ['required', 'string', 'max:255'],
            'location_type' => ['required', 'integer', Rule::in($locationTypeIds)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $payload = [
            'display_name' => $validated['display_name'],
            'location_type' => (int) $validated['location_type'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'address_line_1' => $validated['address_line_1'] ?? null,
            'address_line_2' => $validated['address_line_2'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'country' => $validated['country'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
        ];

        $result = $createLocation($payload);

        if (! ($result['success'] ?? false) || empty($result['record'])) {
            return back()->withErrors(['display_name' => $result['message'] ?? 'Could not create location.']);
        }

        /** @var Location $location */
        $location = $result['record'];
        $subsidiary = Subsidiary::query()->findOrFail((int) $validated['subsidiary_id']);

        if (! $location->subsidiaries()->where('subsidiaries.id', $subsidiary->id)->exists()) {
            $location->subsidiaries()->attach($subsidiary->id);
        }

        return back();
    }

    public function storeBrands(Request $request, CreateBoatMakeAction $createBoatMake): RedirectResponse
    {
        $this->ensureOnboardingIncomplete();

        $validated = $request->validate([
            'brand_keys' => ['nullable', 'array'],
            'brand_keys.*' => ['required', 'string', 'max:255'],
        ]);

        $slugs = array_values(array_unique($validated['brand_keys'] ?? []));
        $allowed = collect(ManufacturerCatalog::entries())->keyBy('slug');
        $assetTypes = [1];

        foreach ($slugs as $slug) {
            if (! isset($allowed[$slug])) {
                continue;
            }
            if (BoatMakeRecord::query()->where('brand_key', $slug)->exists()) {
                continue;
            }
            $label = $allowed[$slug]['display_name'];
            $createBoatMake([
                'display_name' => $label,
                'asset_types' => $assetTypes,
                'is_custom' => false,
                'active' => true,
                'brand_key' => $slug,
            ]);
        }

        return back();
    }

    public function finalize(Request $request, PublicStorage $publicStorage): RedirectResponse
    {
        $this->ensureOnboardingIncomplete();

        $validated = $request->validate([
            'logo' => ['nullable', 'image', 'max:2048'],
            'default_timezone' => ['required', 'string', Rule::in(array_column(Timezone::options(), 'id'))],
            'brand_color' => ['nullable', 'string', 'max:7'],
        ]);

        $account = AccountSettings::getCurrent();

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $result = $publicStorage->store($file, 'logos', null, $account->logo_file);
            $account->logo_file = $result['key'];
            $account->logo_file_extension = $result['file_extension'];
            $account->logo_file_size = $result['file_size'];
        }

        $account->timezone = $validated['default_timezone'];
        $bc = trim((string) ($validated['brand_color'] ?? ''));
        if ($bc !== '') {
            $account->brand_color = $bc;
        }

        $account->onboarding_complete = true;
        $account->save();

        return back();
    }

    private function ensureOnboardingIncomplete(): void
    {
        if (AccountSettings::getCurrent()->onboarding_complete) {
            abort(403, 'Onboarding is already complete.');
        }
    }
}
