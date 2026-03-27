<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Asset\Actions\CreateAsset as CreateAction;
use App\Domain\Asset\Actions\DeleteAsset as DeleteAction;
use App\Domain\Asset\Actions\UpdateAsset as UpdateAction;
use App\Domain\Asset\Models\Asset as RecordModel;
use App\Domain\AssetSpec\Support\AvailableAssetSpecsCache;
use App\Enums\RecordType;
use Illuminate\Http\Request;

class AssetController extends RecordController
{
    public function __construct(Request $request)
    {
        $recordType = RecordType::Asset;
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
     * Spec definitions for the default asset type on the index “create” modal (matches fields.type default).
     */
    protected function indexInertiaProps(Request $request, $records, $schema, array $fieldsSchema, $formSchema, array $enumOptions): array
    {
        $props = parent::indexInertiaProps($request, $records, $schema, $fieldsSchema, $formSchema, $enumOptions);

        $formGroups = $formSchema['form'] ?? $formSchema;
        $hasSpecsGroup = is_array($formGroups) && collect($formGroups)
            ->contains(fn ($g) => is_array($g) && ($g['type'] ?? null) === 'specs');

        $defaultType = isset($fieldsSchema['type']['default'])
            ? (int) $fieldsSchema['type']['default']
            : 1;

        $props['createAvailableSpecs'] = $hasSpecsGroup
            ? AvailableAssetSpecsCache::get($defaultType)->values()->all()
            : [];

        return $props;
    }
}
