<?php

declare(strict_types=1);

namespace App\Domain\Opportunity\Validation;

final class OpportunityLineRequestRules
{
    /**
     * Extra validation rules when `assets` and/or `inventory_items` keys are present on the payload.
     *
     * @return array<string, mixed>
     */
    public static function nested(array $data): array
    {
        $nested = [];

        if (array_key_exists('assets', $data)) {
            $nested += [
                'assets' => ['nullable', 'array'],
                'assets.*.asset_id' => ['required', 'integer', 'exists:assets,id'],
                'assets.*.quantity' => ['nullable', 'numeric', 'min:0'],
                'assets.*.unit_price' => ['nullable', 'numeric'],
                'assets.*.estimated_cost' => ['nullable', 'numeric'],
                'assets.*.notes' => ['nullable', 'string'],
                'assets.*.asset_variant_id' => ['nullable', 'integer'],
                'assets.*.asset_unit_id' => ['nullable', 'integer'],
                'assets.*.asset_option_selections' => ['nullable', 'array'],
                'assets.*.asset_option_selections.*.option_id' => ['required', 'integer'],
                'assets.*.asset_option_selections.*.option_value_id' => ['required', 'integer'],
                'assets.*.addons' => ['nullable', 'array'],
                'assets.*.addons.*.addon_id' => ['nullable', 'integer', 'exists:addons,id'],
                'assets.*.addons.*.name' => ['nullable', 'string', 'max:255'],
                'assets.*.addons.*.price' => ['nullable', 'numeric'],
                'assets.*.addons.*.quantity' => ['nullable', 'integer', 'min:1'],
                'assets.*.addons.*.notes' => ['nullable', 'string'],
                'assets.*.addons.*.metadata' => ['nullable'],
            ];
        }

        if (array_key_exists('inventory_items', $data)) {
            $nested += [
                'inventory_items' => ['nullable', 'array'],
                'inventory_items.*.inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
                'inventory_items.*.quantity' => ['nullable', 'numeric', 'min:0'],
                'inventory_items.*.unit_price' => ['nullable', 'numeric'],
                'inventory_items.*.estimated_cost' => ['nullable', 'numeric'],
                'inventory_items.*.notes' => ['nullable', 'string'],
                'inventory_items.*.addons' => ['nullable', 'array'],
                'inventory_items.*.addons.*.addon_id' => ['nullable', 'integer', 'exists:addons,id'],
                'inventory_items.*.addons.*.name' => ['nullable', 'string', 'max:255'],
                'inventory_items.*.addons.*.price' => ['nullable', 'numeric'],
                'inventory_items.*.addons.*.quantity' => ['nullable', 'integer', 'min:1'],
                'inventory_items.*.addons.*.notes' => ['nullable', 'string'],
                'inventory_items.*.addons.*.metadata' => ['nullable'],
            ];
        }

        return $nested;
    }
}
