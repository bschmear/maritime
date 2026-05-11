<?php

declare(strict_types=1);

namespace App\Domain\Opportunity\Services;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Models\OpportunityAssetSelectedOption;
use App\Services\AssetOptionResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OpportunitySelectedOptionSync
{
    public function __construct(private AssetOptionResolver $resolver) {}

    /**
     * @param  array<int, array<string, mixed>>  $assetsPayload  Opportunity form asset rows (asset_id, asset_variant_id, asset_option_selections).
     */
    public function sync(Opportunity $opportunity, array $assetsPayload): void
    {
        $validator = Validator::make(
            ['assets' => $assetsPayload],
            [
                'assets' => ['present', 'array'],
                'assets.*.asset_option_selections' => ['nullable', 'array'],
                'assets.*.asset_option_selections.*.option_id' => ['required', 'integer'],
                'assets.*.asset_option_selections.*.option_value_id' => ['required', 'integer'],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        foreach ($assetsPayload as $item) {
            $assetId = (int) ($item['asset_id'] ?? 0);
            if ($assetId === 0) {
                continue;
            }

            $pivotId = $this->assetPivotId($opportunity->id, $assetId);
            if ($pivotId === null) {
                continue;
            }

            OpportunityAssetSelectedOption::query()->where('asset_opportunity_id', $pivotId)->delete();

            $customerFillsOptions = false;

            $asset = Asset::query()->find($assetId);
            if ($asset === null) {
                continue;
            }

            $variantId = ! empty($item['asset_variant_id']) ? (int) $item['asset_variant_id'] : null;
            $variant = $variantId
                ? AssetVariant::query()->whereKey($variantId)->where('asset_id', $asset->id)->first()
                : null;

            $resolved = $this->resolver->resolve($asset, $variant)->keyBy('option_id');

            $selections = $item['asset_option_selections'] ?? [];
            $selectedByOption = [];
            foreach ($selections as $sel) {
                $oid = (int) $sel['option_id'];
                $vid = (int) $sel['option_value_id'];
                if (! isset($selectedByOption[$oid])) {
                    $selectedByOption[$oid] = [];
                }
                $selectedByOption[$oid][$vid] = $vid;
            }

            foreach ($selectedByOption as $oid => $vids) {
                $selectedByOption[$oid] = array_values($vids);
            }

            foreach ($resolved as $optionPayload) {
                $optionId = (int) $optionPayload['option_id'];
                $valueIds = $selectedByOption[$optionId] ?? [];

                if (! $customerFillsOptions && ($optionPayload['is_required'] ?? false) && $valueIds === []) {
                    throw ValidationException::withMessages([
                        'assets' => 'Option "'.$optionPayload['name'].'" is required for boat "'.$asset->display_name.'".',
                    ]);
                }

                $allowMultiple = (bool) ($optionPayload['allow_multiple'] ?? false);
                if (! $allowMultiple && count($valueIds) > 1) {
                    throw ValidationException::withMessages([
                        'assets' => 'Option "'.$optionPayload['name'].'" allows only one selection.',
                    ]);
                }

                $min = $optionPayload['min_select'] ?? null;
                $max = $optionPayload['max_select'] ?? null;
                $count = count($valueIds);
                if (! $customerFillsOptions && $min !== null && $count < $min) {
                    throw ValidationException::withMessages([
                        'assets' => 'Option "'.$optionPayload['name'].'" requires at least '.$min.' selection(s).',
                    ]);
                }
                if ($max !== null && $count > $max) {
                    throw ValidationException::withMessages([
                        'assets' => 'Option "'.$optionPayload['name'].'" allows at most '.$max.' selection(s).',
                    ]);
                }
            }

            foreach ($selections as $sel) {
                $oid = (int) $sel['option_id'];
                $vid = (int) $sel['option_value_id'];

                $optionPayload = $resolved->get($oid);
                if ($optionPayload === null) {
                    throw ValidationException::withMessages([
                        'assets' => 'Invalid option selection for this boat configuration.',
                    ]);
                }

                $valueMeta = collect($optionPayload['values'] ?? [])->firstWhere('id', $vid);
                if ($valueMeta === null) {
                    throw ValidationException::withMessages([
                        'assets' => 'Invalid option value for "'.$optionPayload['name'].'".',
                    ]);
                }

                OpportunityAssetSelectedOption::query()->create([
                    'asset_opportunity_id' => $pivotId,
                    'option_id' => $oid,
                    'option_value_id' => $vid,
                    'option_name' => $optionPayload['name'],
                    'value_label' => $valueMeta['label'],
                    'cost' => $valueMeta['cost'],
                    'price' => $valueMeta['price'],
                ]);
            }
        }
    }

    private function assetPivotId(int $opportunityId, int $assetId): ?int
    {
        $id = DB::table('asset_opportunity')
            ->where('opportunity_id', $opportunityId)
            ->where('asset_id', $assetId)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}
