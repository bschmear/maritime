<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Asset\Actions\CreateAsset as CreateAction;
use App\Domain\Asset\Actions\DeleteAsset as DeleteAction;
use App\Domain\Asset\Actions\UpdateAsset as UpdateAction;
use App\Domain\Asset\Models\Asset as RecordModel;
use App\Domain\AssetSpec\Models\AssetSpecValue;
use App\Domain\AssetSpec\Support\AvailableAssetSpecsCache;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Enums\RecordType;
use App\Enums\Timezone;
use Illuminate\Http\JsonResponse;
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

    /**
     * Full-page create: include default-type specs so the generic form matches the index modal behaviour.
     */
    public function create()
    {
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();

        $account = \App\Models\AccountSettings::getCurrent();

        $formGroups = $formSchema['form'] ?? $formSchema;
        $hasSpecsGroup = is_array($formGroups) && collect($formGroups)
            ->contains(fn ($g) => is_array($g) && ($g['type'] ?? null) === 'specs');

        $defaultType = isset($fieldsSchema['type']['default'])
            ? (int) $fieldsSchema['type']['default']
            : 1;

        $createAvailableSpecs = $hasSpecsGroup
            ? AvailableAssetSpecsCache::get($defaultType)->values()->all()
            : [];

        return inertia('Tenant/'.$this->domainName.'/Create', [
            'recordType' => $this->recordType,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
            'createAvailableSpecs' => $createAvailableSpecs,
        ]);
    }

    /**
     * Run a callback with schema resolution pointing at the AssetVariant domain (no RecordController changes).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function withAssetVariantDomain(callable $callback): mixed
    {
        $previous = $this->domainName;
        $this->domainName = 'AssetVariant';
        try {
            return $callback();
        } finally {
            $this->domainName = $previous;
        }
    }

    public function variantsSelectForm(Request $request, RecordModel $asset): JsonResponse
    {
        return $this->withAssetVariantDomain(function () use ($asset, $request): JsonResponse {
            $formSchema = $this->getFormSchema();
            $fieldsSchemaRaw = $this->getFieldsSchema();
            $fieldsSchema = isset($fieldsSchemaRaw['fields']) ? $fieldsSchemaRaw['fields'] : $fieldsSchemaRaw;
            $enumOptions = $this->getEnumOptions();

            if ($request->wantsJson() || $request->ajax()) {
                $availableSpecs = $asset->has_variants
                    ? AvailableAssetSpecsCache::get((int) $asset->type)->values()->all()
                    : [];

                return response()->json([
                    'formSchema' => $formSchema,
                    'fieldsSchema' => $fieldsSchema,
                    'enumOptions' => $enumOptions,
                    'recordType' => 'assets.variants',
                    'recordTitle' => 'Variant',
                    'extraRouteParams' => ['asset' => $asset->id],
                    'availableSpecs' => $availableSpecs,
                    'specsContextAssetType' => (int) $asset->type,
                ]);
            }

            return response()->json(['error' => 'Invalid request'], 400);
        });
    }

    public function variantsIndex(Request $request, RecordModel $asset): JsonResponse
    {
        $payload = $this->withAssetVariantDomain(function () {
            return [
                'schema' => $this->getTableSchema(),
                'fieldsSchema' => $this->getUnwrappedFieldsSchema(),
            ];
        });

        if (! $asset->has_variants) {
            return response()->json([
                'records' => [],
                'schema' => $payload['schema'],
                'fieldsSchema' => $payload['fieldsSchema'],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => (int) $request->get('per_page', 10),
                    'total' => 0,
                ],
            ]);
        }

        $perPage = (int) $request->get('per_page', 10);

        $records = AssetVariant::query()
            ->where('asset_id', $asset->id)
            ->orderByRaw('LOWER(COALESCE(display_name, \'\')) ASC')
            ->paginate($perPage);

        return response()->json([
            'records' => $records->items(),
            'schema' => $payload['schema'],
            'fieldsSchema' => $payload['fieldsSchema'],
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    public function variantsShow(Request $request, RecordModel $asset, AssetVariant $variant): JsonResponse
    {
        $this->ensureVariantBelongsToAsset($asset, $variant);

        if ($request->ajax() || $request->wantsJson()) {
            $variant->load([
                'asset' => fn ($q) => $q->select(['id', 'display_name', 'type', 'has_variants']),
                'specValues.definition',
            ]);

            return response()->json(['record' => $variant]);
        }

        abort(404);
    }

    public function variantsStore(Request $request, RecordModel $asset): JsonResponse
    {
        if (! $asset->has_variants) {
            return response()->json([
                'message' => 'This asset is not configured to use variants. Enable “This asset has variants” on the asset first.',
            ], 422);
        }

        $validated = $request->validate(array_merge([
            'name' => ['nullable', 'string', 'max:255'],
        ], $this->variantSpecsValidationRules()));

        $variant = AssetVariant::query()->create([
            'asset_id' => $asset->id,
            'name' => $validated['name'] ?? null,
        ]);

        if (! empty($validated['specs']) && is_array($validated['specs'])) {
            $this->syncVariantSpecs($variant, $validated['specs']);
        }

        return response()->json(['recordId' => $variant->id]);
    }

    public function variantsUpdate(Request $request, RecordModel $asset, AssetVariant $variant): JsonResponse
    {
        $this->ensureVariantBelongsToAsset($asset, $variant);

        if (! $asset->has_variants) {
            return response()->json([
                'message' => 'This asset is not configured to use variants.',
            ], 422);
        }

        $validated = $request->validate(array_merge([
            'name' => ['nullable', 'string', 'max:255'],
        ], $this->variantSpecsValidationRules()));

        $variant->fill([
            'name' => $validated['name'] ?? null,
        ]);
        $variant->save();

        if (array_key_exists('specs', $validated) && is_array($validated['specs'])) {
            $this->syncVariantSpecs($variant, $validated['specs']);
        }

        $variant->load([
            'asset' => fn ($q) => $q->select(['id', 'display_name', 'type', 'has_variants']),
            'specValues.definition',
        ]);

        return response()->json(['record' => $variant]);
    }

    public function variantsDestroy(Request $request, RecordModel $asset, AssetVariant $variant): JsonResponse
    {
        $this->ensureVariantBelongsToAsset($asset, $variant);

        if ($variant->units()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a variant that still has units. Reassign or remove those units first.',
            ], 422);
        }

        $variant->delete();

        return response()->json(['success' => true]);
    }

    private function ensureVariantBelongsToAsset(RecordModel $asset, AssetVariant $variant): void
    {
        if ((int) $variant->asset_id !== (int) $asset->id) {
            abort(404);
        }
    }

    /**
     * @param  array<int, array{spec_id: int, value_number?: mixed, value_text?: ?string, value_boolean?: mixed, unit?: ?string}>  $specs
     */
    private function syncVariantSpecs(AssetVariant $variant, array $specs): void
    {
        $type = $variant->getMorphClass();
        $id = $variant->getKey();

        foreach ($specs as $spec) {
            if (empty($spec['spec_id'])) {
                continue;
            }

            AssetSpecValue::updateOrCreate(
                [
                    'specable_type' => $type,
                    'specable_id' => $id,
                    'asset_spec_definition_id' => $spec['spec_id'],
                ],
                [
                    'value_number' => $spec['value_number'] ?? null,
                    'value_text' => $spec['value_text'] ?? null,
                    'value_boolean' => array_key_exists('value_boolean', $spec) ? $spec['value_boolean'] : null,
                    'unit' => $spec['unit'] ?? null,
                ]
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function variantSpecsValidationRules(): array
    {
        return [
            'specs' => ['nullable', 'array'],
            'specs.*.spec_id' => ['required', 'integer', 'exists:asset_spec_definitions,id'],
            'specs.*.value_number' => ['nullable', 'numeric'],
            'specs.*.value_text' => ['nullable', 'string'],
            'specs.*.value_boolean' => ['nullable', 'boolean'],
            'specs.*.unit' => ['nullable', 'string'],
        ];
    }
}
