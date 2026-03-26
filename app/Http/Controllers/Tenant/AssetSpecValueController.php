<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Domain\Asset\Models\Asset;
use App\Domain\AssetSpec\Models\AssetSpecDefinition;
use App\Domain\AssetSpec\Models\AssetSpecValue;
use Illuminate\Http\Request;

class AssetSpecValueController extends Controller
{
    /**
     * Get specs for a specific asset
     */
    public function index(Asset $asset)
    {
        $specValues = $asset->specValues;

        // Get all available specs for this asset type
        $availableSpecs = AssetSpecDefinition::whereJsonContains('asset_types', $asset->type)
            ->orderBy('position')
            ->get();

        return response()->json([
            'spec_values' => $specValues,
            'available_specs' => $availableSpecs,
        ]);
    }

    /**
     * Store or update spec values for an asset
     */
    public function store(Request $request, Asset $asset)
    {
        $validated = $request->validate([
            'specs' => 'required|array',
            'specs.*.spec_id' => 'required|exists:asset_spec_definitions,id',
            'specs.*.value_number' => 'nullable|numeric',
            'specs.*.value_text' => 'nullable|string',
            'specs.*.value_boolean' => 'nullable|boolean',
            'specs.*.unit' => 'nullable|string',
        ]);

        foreach ($validated['specs'] as $specData) {
            AssetSpecValue::updateOrCreate(
                [
                    'asset_id' => $asset->id,
                    'asset_spec_definition_id' => $specData['spec_id'],
                ],
                [
                    'value_number' => $specData['value_number'] ?? null,
                    'value_text' => $specData['value_text'] ?? null,
                    'value_boolean' => $specData['value_boolean'] ?? null,
                    'unit' => $specData['unit'] ?? null,
                ]
            );
        }

        return response()->json(['message' => 'Specs saved successfully.']);
    }

    /**
     * Delete a spec value
     */
    public function destroy(Asset $asset, AssetSpecValue $specValue)
    {
        // Don't allow deleting required specs
        if ($specValue->definition->is_required) {
            return response()->json(['error' => 'Cannot delete a required spec.'], 422);
        }

        $specValue->delete();

        return response()->json(['message' => 'Spec deleted successfully.']);
    }
}