<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetOption\Models\AssetOption;
use App\Domain\AssetOption\Models\AssetOptionAssignment;
use App\Domain\AssetOption\Models\AssetOptionMakeAssignment;
use App\Domain\AssetVariant\Models\AssetVariant;
use Illuminate\Support\Collection;

class AssetOptionResolver
{
    /**
     * Catalog-assigned options for a tenant asset and optional variant (excludes global options).
     * Precedence: variant assignment > asset-level assignment > make-wide assignment.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function resolve(Asset $asset, ?AssetVariant $variant = null): Collection
    {
        $makeAssignments = collect();
        if ($asset->make_id) {
            $makeAssignments = AssetOptionMakeAssignment::query()
                ->where('make_id', $asset->make_id)
                ->where('active', true)
                ->get()
                ->keyBy('option_id');
        }

        $assetAssignments = AssetOptionAssignment::query()
            ->where('asset_id', $asset->id)
            ->where('active', true)
            ->get();

        $optionIds = $makeAssignments->keys()
            ->merge($assetAssignments->pluck('option_id'))
            ->unique()
            ->values();

        if ($optionIds->isEmpty()) {
            return collect();
        }

        $options = AssetOption::query()
            ->whereIn('id', $optionIds)
            ->where('active', true)
            ->where('is_global', false)
            ->with(['values'])
            ->get()
            ->keyBy('id');

        return $this->buildResolvedCollection($optionIds, $options, $variant, $assetAssignments, $makeAssignments);
    }

    /**
     * All active global options (tenant-wide, no catalog assignment required).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function resolveGlobal(): Collection
    {
        $options = AssetOption::query()
            ->where('active', true)
            ->where('is_global', true)
            ->with(['values'])
            ->orderBy('name')
            ->get();

        return $options->map(fn (AssetOption $option) => $this->mapOptionPayload($option, null, null))->values();
    }

    /**
     * Resolve specific option IDs (assigned + global) for validation and persistence.
     *
     * @param  list<int>  $optionIds
     * @return Collection<int, array<string, mixed>>
     */
    public function resolveByIds(Asset $asset, ?AssetVariant $variant, array $optionIds): Collection
    {
        $ids = collect($optionIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $makeAssignments = collect();
        if ($asset->make_id) {
            $makeAssignments = AssetOptionMakeAssignment::query()
                ->where('make_id', $asset->make_id)
                ->where('active', true)
                ->whereIn('option_id', $ids)
                ->get()
                ->keyBy('option_id');
        }

        $assetAssignments = AssetOptionAssignment::query()
            ->where('asset_id', $asset->id)
            ->where('active', true)
            ->whereIn('option_id', $ids)
            ->get();

        $options = AssetOption::query()
            ->whereIn('id', $ids)
            ->where('active', true)
            ->with(['values'])
            ->get()
            ->keyBy('id');

        $out = collect();

        foreach ($ids as $optionId) {
            $option = $options->get((int) $optionId);
            if ($option === null) {
                continue;
            }

            if ($option->is_global) {
                $out->push($this->mapOptionPayload($option, null, null));

                continue;
            }

            $assignment = $this->pickAssignment((int) $optionId, $variant, $assetAssignments, $makeAssignments);
            if ($assignment === null) {
                continue;
            }

            $out->push($this->mapOptionPayload(
                $option,
                $assignment['cost_override'] ?? null,
                $assignment['price_override'] ?? null,
            ));
        }

        return $out->values();
    }

    /**
     * @param  Collection<int|string, int>  $optionIds
     * @param  Collection<int, AssetOption>  $options
     * @param  Collection<int, AssetOptionAssignment>  $assetAssignments
     * @param  Collection<int, AssetOptionMakeAssignment>  $makeAssignments
     * @return Collection<int, array<string, mixed>>
     */
    private function buildResolvedCollection(
        Collection $optionIds,
        Collection $options,
        ?AssetVariant $variant,
        Collection $assetAssignments,
        Collection $makeAssignments,
    ): Collection {
        $out = collect();

        foreach ($optionIds as $optionId) {
            $option = $options->get((int) $optionId);
            if ($option === null) {
                continue;
            }

            $assignment = $this->pickAssignment((int) $optionId, $variant, $assetAssignments, $makeAssignments);
            if ($assignment === null) {
                continue;
            }

            $out->push($this->mapOptionPayload(
                $option,
                $assignment['cost_override'] ?? null,
                $assignment['price_override'] ?? null,
            ));
        }

        return $out->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapOptionPayload(AssetOption $option, ?string $costOverride, ?string $priceOverride): array
    {
        $values = $option->input_type === 'toggle'
            ? $this->mapToggleValues($option, $costOverride, $priceOverride)
            : $option->values->map(function ($value) use ($costOverride, $priceOverride) {
                return [
                    'id' => $value->id,
                    'label' => $value->label,
                    'value' => $value->value,
                    'color_hex' => $value->color_hex,
                    'cost' => $costOverride !== null ? $costOverride : $value->cost,
                    'price' => $priceOverride !== null ? $priceOverride : $value->price,
                ];
            })->values()->all();

        return [
            'option_id' => $option->id,
            'name' => $option->name,
            'input_type' => $option->input_type,
            'is_required' => $option->is_required,
            'is_global' => (bool) $option->is_global,
            'allow_multiple' => $option->allow_multiple,
            'min_select' => $option->min_select,
            'max_select' => $option->max_select,
            'values' => $values,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapToggleValues(AssetOption $option, ?string $costOverride, ?string $priceOverride): array
    {
        $onValue = $option->ensureToggleOnValue();

        return [[
            'id' => $onValue->id,
            'label' => $onValue->label,
            'value' => $onValue->value ?? 'on',
            'color_hex' => null,
            'cost' => $costOverride !== null ? $costOverride : $onValue->cost,
            'price' => $priceOverride !== null ? $priceOverride : $onValue->price,
        ]];
    }

    /**
     * @param  Collection<int, AssetOptionAssignment>  $assetAssignments
     * @param  Collection<int, AssetOptionMakeAssignment>  $makeAssignments  keyed by option_id
     * @return array{cost_override: ?string, price_override: ?string}|null
     */
    private function pickAssignment(
        int $optionId,
        ?AssetVariant $variant,
        Collection $assetAssignments,
        Collection $makeAssignments
    ): ?array {
        $forOption = $assetAssignments->where('option_id', $optionId);

        if ($variant !== null) {
            $variantRow = $forOption->firstWhere('variant_id', $variant->id);
            if ($variantRow !== null) {
                return [
                    'cost_override' => $variantRow->cost_override,
                    'price_override' => $variantRow->price_override,
                ];
            }
        }

        $assetRow = $forOption->firstWhere('variant_id', null);
        if ($assetRow !== null) {
            return [
                'cost_override' => $assetRow->cost_override,
                'price_override' => $assetRow->price_override,
            ];
        }

        $makeRow = $makeAssignments->get($optionId);
        if ($makeRow !== null) {
            return [
                'cost_override' => $makeRow->cost_override,
                'price_override' => $makeRow->price_override,
            ];
        }

        return null;
    }
}
