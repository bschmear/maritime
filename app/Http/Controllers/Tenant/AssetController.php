<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Asset\Actions\CreateAsset as CreateAction;
use App\Domain\Asset\Actions\DeleteAsset as DeleteAction;
use App\Domain\Asset\Actions\UpdateAsset as UpdateAction;
use App\Domain\Asset\Models\Asset as RecordModel;
use App\Domain\Asset\Support\SyncAssetSpecValues;
use App\Domain\AssetSpec\Models\AssetSpecDefinition;
use App\Domain\AssetSpec\Models\AssetSpecValue;
use App\Domain\AssetSpec\Support\AvailableAssetSpecsCache;
use App\Domain\AssetUnit\Models\AssetUnit;
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
                $intentVariants = $request->boolean('enable_has_variants');
                $availableSpecs = ($asset->has_variants || $intentVariants)
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
        $intentVariants = $request->boolean('enable_has_variants');
        if ($appendSpecs && ($asset->has_variants || $intentVariants)) {
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
        $validated = $request->validate(array_merge([
            'enable_has_variants' => ['sometimes', 'boolean'],
            'name' => ['nullable', 'string', 'max:255'],
            'length' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'width' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'description' => ['nullable', 'string'],
            'default_cost' => ['nullable', 'numeric'],
            'default_price' => ['nullable', 'numeric'],
            'inactive' => ['sometimes', 'boolean'],
        ], $this->variantSpecsValidationRules()));

        if (! $asset->has_variants) {
            if (! ($validated['enable_has_variants'] ?? false)) {
                return response()->json([
                    'message' => 'This asset is not configured to use variants. Enable “This asset has variants” on the asset first.',
                ], 422);
            }
            $asset->has_variants = true;
            $asset->save();
        }

        $variant = AssetVariant::query()->create([
            'asset_id' => $asset->id,
            'name' => $validated['name'] ?? null,
            'length' => $validated['length'] ?? null,
            'width' => $validated['width'] ?? null,
            'description' => $validated['description'] ?? null,
            'default_cost' => $validated['default_cost'] ?? null,
            'default_price' => $validated['default_price'] ?? null,
            'inactive' => (bool) ($validated['inactive'] ?? false),
        ]);

        if (! empty($validated['specs']) && is_array($validated['specs'])) {
            $this->syncVariantSpecs($asset, $variant, $validated['specs']);
        }

        return response()->json(['recordId' => $variant->id]);
    }

    public function variantsUpdate(Request $request, RecordModel $asset, AssetVariant $variant): JsonResponse
    {
        $this->ensureVariantBelongsToAsset($asset, $variant);

        $validated = $request->validate(array_merge([
            'enable_has_variants' => ['sometimes', 'boolean'],
            'name' => ['nullable', 'string', 'max:255'],
            'length' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'width' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'description' => ['nullable', 'string'],
            'default_cost' => ['nullable', 'numeric'],
            'default_price' => ['nullable', 'numeric'],
            'inactive' => ['sometimes', 'boolean'],
        ], $this->variantSpecsValidationRules()));

        if (! $asset->has_variants) {
            if (! ($validated['enable_has_variants'] ?? false)) {
                return response()->json([
                    'message' => 'This asset is not configured to use variants.',
                ], 422);
            }
            $asset->has_variants = true;
            $asset->save();
        }

        $variant->fill([
            'name' => $validated['name'] ?? null,
            'length' => $validated['length'] ?? null,
            'width' => $validated['width'] ?? null,
            'description' => $validated['description'] ?? null,
            'default_cost' => $validated['default_cost'] ?? null,
            'default_price' => $validated['default_price'] ?? null,
            'inactive' => array_key_exists('inactive', $validated) ? (bool) $validated['inactive'] : $variant->inactive,
        ]);
        $variant->save();

        if (array_key_exists('specs', $validated) && is_array($validated['specs'])) {
            $this->syncVariantSpecs($asset, $variant, $validated['specs']);
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

    /**
     * Suggest description, dynamic spec values, and static spec fields from current form context (OpenAI).
     */
    public function aiSuggestDetails(Request $request, RecordModel $asset): JsonResponse
    {
        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'max:255'],
            'asset_make' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'has_variants' => ['required', 'boolean'],
            'variants' => ['nullable', 'array', 'max:100'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.display_name' => ['nullable', 'string', 'max:255'],
            'variants.*.name' => ['nullable', 'string', 'max:255'],
            'specs' => ['nullable', 'array', 'max:200'],
            'specs.*.id' => ['required', 'integer'],
            'specs.*.label' => ['required', 'string', 'max:255'],
            'specs.*.type' => ['nullable', 'string', 'max:32'],
            'specs.*.unit' => ['nullable', 'string', 'max:64'],
            'specs.*.options' => ['nullable', 'array'],
            'specs.*.current' => ['nullable'],
            'static_fields' => ['nullable', 'array', 'max:50'],
            'static_fields.*.key' => ['required', 'string', 'max:64'],
            'static_fields.*.label' => ['nullable', 'string', 'max:255'],
            'static_fields.*.type' => ['nullable', 'string', 'max:32'],
            'static_fields.*.value' => ['nullable'],
            'static_fields.*.options' => ['nullable', 'array'],
            'static_fields.*.value_mm' => ['nullable', 'numeric'],
            'static_fields.*.value_display_imperial' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $result = app(AssetDetailsAiService::class)->suggest($asset, $validated);

            return response()->json($result);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * AI suggestions for the variant modal (name, description, dimensions, pricing, dynamic specs).
     */
    public function aiSuggestVariantDetails(Request $request, RecordModel $asset): JsonResponse
    {
        if (! $asset->has_variants && ! $request->boolean('enable_has_variants')) {
            return response()->json([
                'message' => 'This asset is not configured to use variants.',
            ], 422);
        }

        $validated = $request->validate([
            'enable_has_variants' => ['sometimes', 'boolean'],
            'asset_display_name' => ['nullable', 'string', 'max:255'],
            'asset_make' => ['nullable', 'string', 'max:255'],
            'sibling_variant_names' => ['nullable', 'array', 'max:100'],
            'sibling_variant_names.*' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'length' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'width' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'default_cost' => ['nullable', 'numeric'],
            'default_price' => ['nullable', 'numeric'],
            'specs' => ['nullable', 'array', 'max:200'],
            'specs.*.id' => ['required', 'integer'],
            'specs.*.label' => ['required', 'string', 'max:255'],
            'specs.*.type' => ['nullable', 'string', 'max:32'],
            'specs.*.unit' => ['nullable', 'string', 'max:64'],
            'specs.*.options' => ['nullable', 'array'],
            'specs.*.current' => ['nullable'],
        ]);

        try {
            $result = app(AssetDetailsAiService::class)->suggestVariant($asset, $validated);

            return response()->json($result);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
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

    /**
     * JSON list of this asset's units, optionally narrowed by variant.
     *
     * Mirrors `variantsIndex` but is flat: used by AssetLineUnitSelect in line-item modals
     * so a user can choose a specific serialized unit and let reports read its cost through
     * the `assetUnit` relation.
     */
    public function unitsIndex(Request $request, RecordModel $asset): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 100);

        $query = AssetUnit::query()
            ->where('asset_id', $asset->id)
            ->where(function ($q) {
                $q->whereNull('inactive')->orWhere('inactive', false);
            });

        if ($request->filled('variant')) {
            $query->where('asset_variant_id', (int) $request->get('variant'));
        }

        if ($request->filled('customer_id')) {
            $cid = (int) $request->get('customer_id');
            $query->where(function ($q) use ($cid) {
                $q->where('customer_id', $cid)
                    ->orWhereNull('customer_id');
            });
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->get('search'));
            if ($term !== '') {
                $like = '%'.$term.'%';
                $query->where(function ($q) use ($like) {
                    $q->where('serial_number', 'like', $like)
                        ->orWhere('hin', 'like', $like)
                        ->orWhere('sku', 'like', $like);
                });
            }
        }

        $records = $query->with([
            'asset:id,display_name',
            'assetVariant:id,display_name,name',
        ])
            ->orderByRaw('COALESCE(serial_number, hin, sku, CAST(id AS CHAR)) ASC')
            ->paginate($perPage);

        $records->getCollection()->transform(function (AssetUnit $unit) {
            return [
                'id' => $unit->id,
                'display_name' => $unit->display_name,
                'asset_id' => $unit->asset_id,
                'asset_variant_id' => $unit->asset_variant_id,
                'serial_number' => $unit->serial_number,
                'hin' => $unit->hin,
                'sku' => $unit->sku,
                'status' => $unit->status,
                'condition' => $unit->condition,
                'cost' => $unit->cost,
                'asking_price' => $unit->asking_price,
                'variant' => $unit->assetVariant
                    ? [
                        'id' => $unit->assetVariant->id,
                        'display_name' => $unit->assetVariant->display_name,
                        'name' => $unit->assetVariant->name,
                    ]
                    : null,
            ];
        });

        return response()->json([
            'records' => $records->items(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ]);
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
    private function syncVariantSpecs(RecordModel $asset, AssetVariant $variant, array $specs): void
    {
        SyncAssetSpecValues::forSpecable($variant, (int) $asset->type, $specs);
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
