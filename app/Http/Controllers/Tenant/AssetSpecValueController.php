<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Support\SyncAssetSpecValues;
use App\Domain\AssetSpec\Models\AssetSpecDefinition;
use App\Domain\AssetSpec\Models\AssetSpecValue;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AssetSpecValueController extends Controller
{
    public function index(Asset $asset)
    {
        if ($asset->has_variants) {
            return response()->json([
                'spec_values' => [],
                'available_specs' => AssetSpecDefinition::query()
                    ->with('group')
                    ->whereJsonContains('asset_types', $asset->type)
                    ->orderBy('position')
                    ->get(),
            ]);
        }

        $specValues = $asset->specValues;

        $availableSpecs = AssetSpecDefinition::query()
            ->with('group')
            ->whereJsonContains('asset_types', $asset->type)
            ->orderBy('position')
            ->get();

        return response()->json([
            'spec_values' => $specValues,
            'available_specs' => $availableSpecs,
        ]);
    }

    public function store(Request $request, Asset $asset)
    {
        if ($asset->has_variants) {
            return response()->json([
                'message' => 'Specifications for this asset are stored on each variant, not on the asset.',
            ], 422);
        }

        $validated = $request->validate([
            'specs' => 'required|array',
            'specs.*.spec_id' => 'required|exists:asset_spec_definitions,id',
            'specs.*.value_number' => 'nullable|numeric',
            'specs.*.value_text' => 'nullable|string',
            'specs.*.value_boolean' => 'nullable|boolean',
            'specs.*.unit' => 'nullable|string',
        ]);

        SyncAssetSpecValues::forSpecable($asset, (int) $asset->type, $validated['specs']);

        return response()->json(['message' => 'Specs saved successfully.']);
    }

    public function destroy(Asset $asset, AssetSpecValue $specValue)
    {
        if ($specValue->specable_type !== $asset->getMorphClass()
            || (int) $specValue->specable_id !== (int) $asset->id) {
            abort(404);
        }

        if ($specValue->definition->is_required) {
            return response()->json(['error' => 'Cannot delete a required spec.'], 422);
        }

        $specValue->delete();

        return response()->json(['message' => 'Spec deleted successfully.']);
    }
}
