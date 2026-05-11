<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetUnit\Actions\CreateAssetUnit as CreateAction;
use App\Domain\AssetUnit\Actions\DeleteAssetUnit as DeleteAction;
use App\Domain\AssetUnit\Actions\UpdateAssetUnit as UpdateAction;
use App\Domain\AssetUnit\Models\AssetUnit as RecordModel;
use App\Enums\RecordType;
use App\Services\AssetOptionResolver;
use Illuminate\Http\Request;

class AssetUnitController extends RecordController
{
    public function __construct(Request $request)
    {
        $recordType = RecordType::AssetUnit;
        parent::__construct(
            $request,
            $recordType->plural(),
            $recordType->title(),
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            $recordType->domainName()
        );
    }

    /**
     * Include catalog flags on the parent asset so the UI can hide options when variants exist.
     */
    protected function appendShowRelationships(array &$relationships): void
    {
        $relationships['asset'] = function ($query) {
            $query->select(['id', 'display_name', 'has_variants', 'make_id']);
        };
    }

    /**
     * @param  \App\Domain\AssetUnit\Models\AssetUnit  $record
     */
    protected function showPageExtraProps($record): array
    {
        $asset = Asset::query()
            ->select(['id', 'display_name', 'has_variants', 'make_id'])
            ->find($record->asset_id);

        if ($asset === null) {
            return [
                'catalogResolvedOptions' => null,
                'catalogContext' => null,
            ];
        }

        // Options belong on variant show when this catalog asset uses variants.
        if (filter_var($asset->getAttribute('has_variants'), FILTER_VALIDATE_BOOLEAN)) {
            return [
                'catalogResolvedOptions' => null,
                'catalogContext' => null,
            ];
        }

        $resolved = app(AssetOptionResolver::class)->resolve($asset, null);

        return [
            'catalogResolvedOptions' => $resolved->values()->all(),
            'catalogContext' => [
                'asset_id' => $asset->id,
                'variant_id' => null,
                'make_id' => $asset->make_id,
                'has_variants' => false,
                'show_variant_scope' => false,
            ],
        ];
    }
}
