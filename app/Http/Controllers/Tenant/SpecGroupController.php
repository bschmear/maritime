<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\AssetSpec\Models\AssetSpecDefinition;
use App\Domain\AssetSpec\Models\SpecGroup;
use App\Domain\AssetSpec\Support\AvailableAssetSpecsCache;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SpecGroupController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string|max:255|unique:spec_groups,key',
            'name' => 'required|string|max:255',
        ]);

        $validated['position'] = (int) (SpecGroup::max('position') ?? 0) + 1;
        $validated['is_active'] = true;

        SpecGroup::create($validated);
        AvailableAssetSpecsCache::forgetAll();

        return back()->with('success', 'Spec group created successfully.');
    }

    public function update(Request $request, SpecGroup $specGroup)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $specGroup->update($validated);
        AvailableAssetSpecsCache::forgetAll();

        return back()->with('success', 'Spec group updated successfully.');
    }

    /**
     * Deactivate a group and move its specs to “ungrouped” (group_id null).
     */
    public function destroy(SpecGroup $specGroup)
    {
        AssetSpecDefinition::query()->where('group_id', $specGroup->id)->update(['group_id' => null]);
        $specGroup->update(['is_active' => false]);
        AvailableAssetSpecsCache::forgetAll();

        return back()->with('success', 'Spec group removed. Its specs are now ungrouped.');
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'groups' => 'required|array',
            'groups.*.id' => 'required|exists:spec_groups,id',
            'groups.*.position' => 'required|integer',
        ]);

        foreach ($validated['groups'] as $row) {
            SpecGroup::where('id', $row['id'])->update(['position' => $row['position']]);
        }
        AvailableAssetSpecsCache::forgetAll();

        return back()->with('success', 'Spec groups reordered successfully.');
    }
}
