<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\AssetSpec\Models\AssetSpecDefinition;
use App\Domain\AssetSpec\Models\SpecGroup;
use App\Domain\AssetSpec\Support\AvailableAssetSpecsCache;
use App\Http\Controllers\Controller;
use Database\Seeders\AssetSpecDefinitionSeeder;
use Illuminate\Http\Request;

class AssetSpecController extends Controller
{
    /**
     * Display the spec builder interface.
     * Seeds default specs the very first time if none exist.
     */
    public function index(Request $request)
    {
        if (! AssetSpecDefinition::exists()) {
            app(AssetSpecDefinitionSeeder::class)->run();
        }

        // JSON fetch for asset forms (asset type only) — same payload as RecordController cache
        if ($request->ajax() && ! $request->header('X-Inertia')
            && $request->filled('asset_type')
            && ! $request->has('search')
            && ! $request->filled('group_id')
            && ! $request->has('type')) {
            return response()->json([
                'specs' => AvailableAssetSpecsCache::get((int) $request->asset_type),
            ]);
        }

        $query = AssetSpecDefinition::query()->with('group');

        if ($request->has('asset_type')) {
            $assetType = (int) $request->asset_type;
            $query->whereJsonContains('asset_types', $assetType);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('label', 'like', "%{$search}%")
                    ->orWhere('key', 'like', "%{$search}%")
                    ->orWhereHas('group', function ($gq) use ($search) {
                        $gq->where('name', 'like', "%{$search}%")
                            ->orWhere('key', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('group_id')) {
            $query->where('group_id', (int) $request->group_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $specs = $query->orderBy('position')->get();

        if ($request->ajax() && ! $request->header('X-Inertia')) {
            return response()->json(['specs' => $specs]);
        }

        $specGroups = SpecGroup::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->get();

        $types = AssetSpecDefinition::query()->distinct()->orderBy('type')->pluck('type')->filter()->values();

        return inertia('Tenant/AssetSpec/Index', [
            'specs' => $specs,
            'specGroups' => $specGroups,
            'types' => $types,
            'filters' => $request->only(['asset_type', 'search', 'group_id', 'type']),
        ]);
    }

    /**
     * Store a new spec definition
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string|unique:asset_spec_definitions,key',
            'label' => 'required|string|max:255',
            'group_id' => 'nullable|exists:spec_groups,id',
            'type' => 'required|in:number,text,select,boolean',
            'unit' => 'nullable|string|max:50',
            'unit_imperial' => 'nullable|string|max:50',
            'unit_metric' => 'nullable|string|max:50',
            'use_metric' => 'boolean',
            'options' => 'nullable|array',
            'options.*.value' => 'required_with:options|string',
            'options.*.label' => 'required_with:options|string',
            'is_filterable' => 'boolean',
            'is_visible' => 'boolean',
            'is_required' => 'boolean',
            'show_on_table' => 'boolean',
            'position' => 'integer',
            'asset_types' => 'nullable|array',
            'asset_types.*' => 'integer',
        ]);

        if (! isset($validated['position'])) {
            $validated['position'] = (int) (AssetSpecDefinition::max('position') ?? 0) + 1;
        }

        AssetSpecDefinition::create($validated);
        AvailableAssetSpecsCache::forgetAll();

        return back()->with('success', 'Spec definition created successfully.');
    }

    /**
     * Update an existing spec definition
     */
    public function update(Request $request, AssetSpecDefinition $assetSpec)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'group_id' => 'nullable|exists:spec_groups,id',
            'type' => 'required|in:number,text,select,boolean',
            'unit' => 'nullable|string|max:50',
            'unit_imperial' => 'nullable|string|max:50',
            'unit_metric' => 'nullable|string|max:50',
            'use_metric' => 'boolean',
            'options' => 'nullable|array',
            'is_filterable' => 'boolean',
            'is_visible' => 'boolean',
            'is_required' => 'boolean',
            'show_on_table' => 'boolean',
            'position' => 'integer',
            'asset_types' => 'nullable|array',
            'asset_types.*' => 'integer',
        ]);

        $assetSpec->update($validated);
        AvailableAssetSpecsCache::forgetAll();

        return back()->with('success', 'Spec definition updated successfully.');
    }

    /**
     * Delete a spec definition
     */
    public function destroy(AssetSpecDefinition $assetSpec)
    {
        if ($assetSpec->is_required) {
            return back()->withErrors(['error' => 'Required spec definitions cannot be deleted.']);
        }

        $assetSpec->delete();
        AvailableAssetSpecsCache::forgetAll();

        return back()->with('success', 'Spec definition deleted successfully.');
    }

    /**
     * Reorder specs
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'specs' => 'required|array',
            'specs.*.id' => 'required|exists:asset_spec_definitions,id',
            'specs.*.position' => 'required|integer',
        ]);

        foreach ($validated['specs'] as $spec) {
            AssetSpecDefinition::where('id', $spec['id'])
                ->update(['position' => $spec['position']]);
        }
        AvailableAssetSpecsCache::forgetAll();

        return back()->with('success', 'Specs reordered successfully.');
    }
}
