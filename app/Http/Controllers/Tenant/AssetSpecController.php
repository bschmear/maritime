<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Domain\AssetSpec\Models\AssetSpecDefinition;
use App\Domain\AssetSpec\Models\AssetSpecValue;
// use App\Domain\Asset\Models\Asset;
use Database\Seeders\AssetSpecDefinitionSeeder;
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Artisan;
// use Illuminate\Support\Facades\Cache;

class AssetSpecController extends Controller
{
    /**
     * Display the spec builder interface.
     * Seeds default specs the very first time if none exist.
     */
    public function index(Request $request)
    {
        // Run the seeder once — flag is stored permanently so it never runs again.
        if (!AssetSpecDefinition::exists()) {
            app(AssetSpecDefinitionSeeder::class)->run();
        }

        $query = AssetSpecDefinition::query();

        // Filter by asset type if provided
        if ($request->has('asset_type')) {
            $assetType = (int) $request->asset_type;
            $query->whereJsonContains('asset_types', $assetType);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('label', 'like', "%{$search}%")
                  ->orWhere('key', 'like', "%{$search}%")
                  ->orWhere('group', 'like', "%{$search}%");
            });
        }

        // Filter by group
        if ($request->has('group')) {
            $query->where('group', $request->group);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $specs = $query->orderBy('position')->get();

        // Return JSON for AJAX requests (e.g. dynamic asset-type switching in forms)
        if ($request->ajax() && !$request->header('X-Inertia')) {
            return response()->json(['specs' => $specs]);
        }

        // Get unique groups and types for filters
        $groups = AssetSpecDefinition::distinct()->pluck('group')->filter()->values();
        $types  = AssetSpecDefinition::distinct()->pluck('type')->filter()->values();

        return inertia('Tenant/AssetSpec/Index', [
            'specs'   => $specs,
            'groups'  => $groups,
            'types'   => $types,
            'filters' => $request->only(['asset_type', 'search', 'group', 'type']),
        ]);
    }

    /**
     * Store a new spec definition
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'key'           => 'required|string|unique:asset_spec_definitions,key',
            'label'         => 'required|string|max:255',
            'group'         => 'nullable|string|max:255',
            'type'          => 'required|in:number,text,select,boolean',
            'unit'          => 'nullable|string|max:50',
            'unit_imperial' => 'nullable|string|max:50',
            'unit_metric'   => 'nullable|string|max:50',
            'use_metric'    => 'boolean',
            'options'           => 'nullable|array',
            'options.*.value'   => 'required_with:options|string',
            'options.*.label'   => 'required_with:options|string',
            'is_filterable' => 'boolean',
            'is_visible'    => 'boolean',
            'is_required'   => 'boolean',
            'position'      => 'integer',
            'asset_types'   => 'nullable|array',
            'asset_types.*' => 'integer',
        ]);

        if (!isset($validated['position'])) {
            $validated['position'] = AssetSpecDefinition::max('position') + 1;
        }

        AssetSpecDefinition::create($validated);

        return back()->with('success', 'Spec definition created successfully.');
    }

    /**
     * Update an existing spec definition
     */
    public function update(Request $request, AssetSpecDefinition $assetSpec)
    {
        $validated = $request->validate([
            'label'         => 'required|string|max:255',
            'group'         => 'nullable|string|max:255',
            'type'          => 'required|in:number,text,select,boolean',
            'unit'          => 'nullable|string|max:50',
            'unit_imperial' => 'nullable|string|max:50',
            'unit_metric'   => 'nullable|string|max:50',
            'use_metric'    => 'boolean',
            'options'       => 'nullable|array',
            'is_filterable' => 'boolean',
            'is_visible'    => 'boolean',
            'is_required'   => 'boolean',
            'position'      => 'integer',
            'asset_types'   => 'nullable|array',
            'asset_types.*' => 'integer',
        ]);

        $assetSpec->update($validated);

        return back()->with('success', 'Spec definition updated successfully.');
    }

    /**
     * Delete a spec definition
     */
    public function destroy(AssetSpecDefinition $assetSpec)
    {
        if ($assetSpec->is_required && AssetSpecValue::where('asset_spec_definition_id', $assetSpec->id)->exists()) {
            return back()->withErrors(['error' => 'Cannot delete a required spec that is in use.']);
        }

        $assetSpec->delete();

        return back()->with('success', 'Spec definition deleted successfully.');
    }

    /**
     * Reorder specs
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'specs'            => 'required|array',
            'specs.*.id'       => 'required|exists:asset_spec_definitions,id',
            'specs.*.position' => 'required|integer',
        ]);

        foreach ($validated['specs'] as $spec) {
            AssetSpecDefinition::where('id', $spec['id'])
                ->update(['position' => $spec['position']]);
        }

        return back()->with('success', 'Specs reordered successfully.');
    }
}