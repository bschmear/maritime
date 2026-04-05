<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Asset\Actions\CreateAsset as CreateAction;
use App\Domain\Asset\Actions\DeleteAsset as DeleteAction;
use App\Domain\Asset\Actions\UpdateAsset as UpdateAction;
use App\Domain\Asset\Models\Asset as RecordModel;
use App\Domain\AssetSpec\Models\AssetSpecDefinition;
use App\Domain\AssetSpec\Models\AssetSpecValue;
use App\Domain\AssetSpec\Support\AvailableAssetSpecsCache;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Enums\RecordType;
use App\Enums\Timezone;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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

        $baseSchema = is_array($payload['schema']) ? $payload['schema'] : [];
        $tableSchema = $baseSchema;
        $fieldsSchema = is_array($payload['fieldsSchema']) ? $payload['fieldsSchema'] : [];
        $appendSpecs = (bool) ($tableSchema['appendAssetSpecTableColumns'] ?? false);
        unset($tableSchema['appendAssetSpecTableColumns']);

        $tableSpecDefs = new EloquentCollection;
        if ($appendSpecs && $asset->has_variants) {
            $tableSpecDefs = AssetSpecDefinition::query()
                ->where('show_on_table', true)
                ->whereJsonContains('asset_types', (int) $asset->type)
                ->orderBy('position')
                ->get();

            $columns = array_values($tableSchema['columns'] ?? []);
            foreach ($tableSpecDefs as $def) {
                $key = 'spec_value_'.$def->id;
                $columns[] = ['key' => $key, 'label' => $def->label, 'sortable' => false];
                $fieldsSchema[$key] = [
                    'label' => $def->label,
                    'type' => 'text',
                ];
            }
            $tableSchema['columns'] = $columns;
        }

        if (! $asset->has_variants) {
            return response()->json([
                'records' => [],
                'schema' => $tableSchema,
                'fieldsSchema' => $fieldsSchema,
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
            ->with([
                'specValues.definition',
                'asset' => fn ($q) => $q->select(['id', 'description']),
            ])
            ->orderByRaw('LOWER(COALESCE(display_name, \'\')) ASC')
            ->paginate($perPage);

        $records->getCollection()->transform(function (AssetVariant $variant) use ($tableSpecDefs) {
            $row = $variant->toArray();
            $row['resolved_description'] = $variant->resolvedDescription();
            foreach ($tableSpecDefs as $def) {
                if (! $def instanceof AssetSpecDefinition) {
                    continue;
                }
                $key = 'spec_value_'.$def->id;
                $sv = $variant->specValues->firstWhere('asset_spec_definition_id', $def->id);
                $row[$key] = $this->formatVariantSpecTableCell(
                    $sv instanceof AssetSpecValue ? $sv : null,
                    $def
                );
            }

            return $row;
        });

        return response()->json([
            'records' => $records->items(),
            'schema' => $tableSchema,
            'fieldsSchema' => $fieldsSchema,
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
                'asset' => fn ($q) => $q->select(['id', 'display_name', 'type', 'has_variants', 'description']),
                'specValues.definition',
            ]);

            return response()->json([
                'record' => array_merge($variant->toArray(), [
                    'resolved_description' => $variant->resolvedDescription(),
                ]),
            ]);
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
            'description' => ['nullable', 'string'],
            'default_cost' => ['nullable', 'numeric'],
            'default_price' => ['nullable', 'numeric'],
            'inactive' => ['sometimes', 'boolean'],
        ], $this->variantSpecsValidationRules()));

        $variant = AssetVariant::query()->create([
            'asset_id' => $asset->id,
            'name' => $validated['name'] ?? null,
            'description' => $validated['description'] ?? null,
            'default_cost' => $validated['default_cost'] ?? null,
            'default_price' => $validated['default_price'] ?? null,
            'inactive' => (bool) ($validated['inactive'] ?? false),
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
            'description' => ['nullable', 'string'],
            'default_cost' => ['nullable', 'numeric'],
            'default_price' => ['nullable', 'numeric'],
            'inactive' => ['sometimes', 'boolean'],
        ], $this->variantSpecsValidationRules()));

        $variant->fill([
            'name' => $validated['name'] ?? null,
            'description' => $validated['description'] ?? null,
            'default_cost' => $validated['default_cost'] ?? null,
            'default_price' => $validated['default_price'] ?? null,
            'inactive' => array_key_exists('inactive', $validated) ? (bool) $validated['inactive'] : $variant->inactive,
        ]);
        $variant->save();

        if (array_key_exists('specs', $validated) && is_array($validated['specs'])) {
            $this->syncVariantSpecs($variant, $validated['specs']);
        }

        $variant->load([
            'asset' => fn ($q) => $q->select(['id', 'display_name', 'type', 'has_variants', 'description']),
            'specValues.definition',
        ]);

        return response()->json([
            'record' => array_merge($variant->toArray(), [
                'resolved_description' => $variant->resolvedDescription(),
            ]),
        ]);
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

    private function formatVariantSpecTableCell(?AssetSpecValue $sv, AssetSpecDefinition $def): ?string
    {
        if ($sv === null) {
            return null;
        }

        return match ($def->type) {
            'boolean' => $sv->value_boolean === null
                ? null
                : (((bool) $sv->value_boolean) ? 'Yes' : 'No'),
            'number' => $this->formatVariantSpecNumberDisplay($sv, $def),
            'select' => $this->formatVariantSpecSelectDisplay($sv, $def),
            'text' => ($sv->value_text !== null && $sv->value_text !== '') ? $sv->value_text : null,
            default => $sv->value_text ?? ($sv->value_number !== null ? (string) $sv->value_number : null),
        };
    }

    private function formatVariantSpecNumberDisplay(AssetSpecValue $sv, AssetSpecDefinition $def): ?string
    {
        if ($sv->value_number === null) {
            return null;
        }

        $n = (float) $sv->value_number;
        $str = fmod($n, 1.0) === 0.0 ? (string) (int) $n : rtrim(rtrim(sprintf('%.6F', $n), '0'), '.');
        $unit = $sv->unit ?? $def->unit;

        return $unit !== null && $unit !== '' ? $str.' '.$unit : $str;
    }

    private function formatVariantSpecSelectDisplay(AssetSpecValue $sv, AssetSpecDefinition $def): ?string
    {
        $raw = $sv->value_text;
        if ($raw === null || $raw === '') {
            return null;
        }

        $options = $def->options ?? [];
        foreach ($options as $opt) {
            if (! is_array($opt)) {
                continue;
            }
            if (array_key_exists('value', $opt) && (string) $opt['value'] === (string) $raw) {
                return isset($opt['label']) ? (string) $opt['label'] : (string) $raw;
            }
        }

        return (string) $raw;
    }
}
