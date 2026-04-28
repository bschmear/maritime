<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\PublicStorage;
use App\Domain\AssetSpec\Support\AvailableAssetSpecsCache;
use App\Domain\Document\Models\Document;
use App\Enums\Timezone;
use App\Http\Controllers\Concerns\HasImageSupport;
use App\Http\Controllers\Concerns\HasSchemaSupport;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;

class RecordController extends BaseController
{
    use AuthorizesRequests, HasImageSupport, HasSchemaSupport, ValidatesRequests;

    protected $recordType;

    protected $recordTitle;

    protected $recordModel;

    protected $createAction;

    protected $updateAction;

    protected $deleteAction;

    protected $domainName;

    public function __construct(
        Request $request,
        $recordType,
        $recordTitle,
        $recordModel,
        $createAction,
        $updateAction,
        $deleteAction,
        $domainName = null
    ) {
        $this->middleware('auth');
        $this->recordType = $recordType;
        $this->recordTitle = $recordTitle;
        $this->recordModel = $recordModel;
        $this->createAction = $createAction;
        $this->updateAction = $updateAction;
        $this->deleteAction = $deleteAction;
        $this->domainName = $domainName ?? $recordTitle;
    }

    /**
     * Override in subclasses to apply custom search logic.
     * Return true if the query was modified, false to use the default search behaviour.
     */
    protected function applyCustomSearch($query, string $rawSearch): bool
    {
        return false;
    }

    /**
     * Map a table column key from table.json to a real database column on the primary table.
     */
    protected function resolveRecordIndexSortColumn(string $requestKey, array $dbColumns): ?string
    {
        if (in_array($requestKey, $dbColumns, true)) {
            return $requestKey;
        }

        // Virtual appended display_name (e.g. Estimate EST-{sequence})
        if ($requestKey === 'display_name' && in_array('sequence', $dbColumns, true)) {
            return 'sequence';
        }

        return null;
    }

    /**
     * When a column is backed by a PHP enum with options(), sort by option label (alphabetically)
     * instead of the raw stored id/value.
     */
    protected function applyEnumLabelSortIfApplicable(
        $query,
        string $qualifiedColumn,
        array $fieldsSchema,
        string $sortRequestKey,
        string $resolvedColumn,
        string $dir
    ): bool {
        if (str_contains($resolvedColumn, '.')) {
            return false;
        }

        if ($sortRequestKey !== $resolvedColumn) {
            return false;
        }

        $fieldDef = $fieldsSchema[$sortRequestKey] ?? null;
        if (! is_array($fieldDef)) {
            return false;
        }

        $enumClass = $fieldDef['enum'] ?? null;
        if (! is_string($enumClass) || $enumClass === '' || ! class_exists($enumClass)) {
            return false;
        }

        if (! method_exists($enumClass, 'options')) {
            return false;
        }

        $options = $enumClass::options();
        if (! is_array($options) || $options === []) {
            return false;
        }

        $caseParts = [];
        $bindings = [];
        foreach ($options as $opt) {
            if (! is_array($opt)) {
                continue;
            }
            $id = $opt['id'] ?? null;
            if ($id === null) {
                $id = $opt['value'] ?? null;
            }
            $name = $opt['name'] ?? $opt['label'] ?? null;
            if ($name === null || (! is_string($name) && ! is_numeric($name))) {
                continue;
            }
            if ($id === null) {
                continue;
            }
            $name = (string) $name;
            $caseParts[] = 'when '.$qualifiedColumn.' = ? then lower(?)';
            $bindings[] = $id;
            $bindings[] = $name;
        }

        if ($caseParts === []) {
            return false;
        }

        $expr = 'case '.implode(' ', $caseParts).' else \'\' end';
        $query->orderByRaw($expr.' '.$dir, $bindings);

        return true;
    }

    /**
     * When table.json sets sortColumn to "related_table.column" for a record FK field,
     * join the related table and sort by that column (alphabetically for text columns).
     */
    protected function applyRelatedTableSortFromRecordField(
        $query,
        string $resolved,
        string $sortRequestKey,
        array $fieldsSchema,
        string $tableName,
        array $dbColumns,
        array $actualColumns,
        string $dir
    ): bool {
        if (! preg_match('/^([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)$/', $resolved, $m)) {
            return false;
        }

        $joinTable = $m[1];
        $joinColumn = $m[2];

        if ($joinTable === $tableName) {
            return false;
        }

        $fieldDef = $fieldsSchema[$sortRequestKey] ?? null;
        if (! is_array($fieldDef) || ($fieldDef['type'] ?? null) !== 'record' || empty($fieldDef['typeDomain'])) {
            return false;
        }

        $domain = $fieldDef['typeDomain'];
        if (! is_string($domain) || ! preg_match('/^[A-Za-z0-9]+$/', $domain)) {
            return false;
        }

        $modelClass = "App\\Domain\\{$domain}\\Models\\{$domain}";
        if (! class_exists($modelClass)) {
            return false;
        }

        /** @var \Illuminate\Database\Eloquent\Model $relatedModel */
        $relatedModel = new $modelClass;
        if ($relatedModel->getTable() !== $joinTable) {
            return false;
        }

        if (! in_array($sortRequestKey, $dbColumns, true)) {
            return false;
        }

        $connection = $this->recordModel->getConnectionName();
        if (! \Schema::connection($connection)->hasColumn($joinTable, $joinColumn)) {
            return false;
        }

        $prefixedColumns = array_map(function ($col) use ($dbColumns, $tableName) {
            return in_array($col, $dbColumns, true) ? $tableName.'.'.$col : $col;
        }, $actualColumns);

        $query->select($prefixedColumns)
            ->leftJoin($joinTable, $tableName.'.'.$sortRequestKey, '=', $joinTable.'.id')
            ->orderByRaw('LOWER('.$joinTable.'.'.$joinColumn.') '.$dir);

        return true;
    }

    /**
     * Apply ?sort=&direction= when allowed by table.json (sortable defaults true).
     */
    protected function applyRecordIndexSort($query, Request $request, ?array $schema, array $dbColumns, string $tableName, array $actualColumns, array $fieldsSchema): bool
    {
        $allowed = $this->sortableColumnsFromTableSchema($schema);
        $sp = $this->sortParamsFromRequest($request);
        if ($sp['key'] === null || ! isset($allowed[$sp['key']])) {
            return false;
        }

        $def = $allowed[$sp['key']];
        $override = $def['sortColumn'] ?? null;
        if (is_string($override) && $override !== '' && preg_match('/^[a-zA-Z0-9_.]+$/', $override)) {
            $resolved = $override;
        } else {
            $resolved = $this->resolveRecordIndexSortColumn($sp['key'], $dbColumns);
        }

        if ($resolved === null) {
            return false;
        }

        $dir = $sp['dir'];

        if ($this->domainName === 'AssetUnit') {
            $prefixedColumns = array_map(function ($col) use ($dbColumns, $tableName) {
                return in_array($col, $dbColumns, true) ? $tableName.'.'.$col : $col;
            }, $actualColumns);
            $query->select($prefixedColumns)
                ->join('assets', 'asset_units.asset_id', '=', 'assets.id');

            if (str_contains($resolved, '.')) {
                if (preg_match('/^assets\.[a-zA-Z0-9_]+$/', $resolved)) {
                    $query->orderBy($resolved, $dir);

                    return true;
                }

                return false;
            }

            if (! in_array($resolved, $dbColumns, true)) {
                return false;
            }

            if ($this->applyEnumLabelSortIfApplicable(
                $query,
                $tableName.'.'.$resolved,
                $fieldsSchema,
                $sp['key'],
                $resolved,
                $dir
            )) {
                return true;
            }

            $query->orderBy($tableName.'.'.$resolved, $dir);

            return true;
        }

        if ($this->domainName === 'InventoryUnit') {
            if (str_contains($resolved, '.')) {
                if (preg_match('/^inventory_items\.[a-zA-Z0-9_]+$/', $resolved)) {
                    $prefixedColumns = array_map(function ($col) use ($dbColumns, $tableName) {
                        return in_array($col, $dbColumns, true) ? $tableName.'.'.$col : $col;
                    }, $actualColumns);
                    $query->select($prefixedColumns)
                        ->join('inventory_items', 'inventory_units.inventory_item_id', '=', 'inventory_items.id')
                        ->orderBy($resolved, $dir);

                    return true;
                }

                return false;
            }

            if (! in_array($resolved, $dbColumns, true)) {
                return false;
            }

            $prefixedColumns = array_map(function ($col) use ($dbColumns, $tableName) {
                return in_array($col, $dbColumns, true) ? $tableName.'.'.$col : $col;
            }, $actualColumns);
            $query->select($prefixedColumns)
                ->join('inventory_items', 'inventory_units.inventory_item_id', '=', 'inventory_items.id');

            if ($this->applyEnumLabelSortIfApplicable(
                $query,
                $tableName.'.'.$resolved,
                $fieldsSchema,
                $sp['key'],
                $resolved,
                $dir
            )) {
                return true;
            }

            $query->orderBy($tableName.'.'.$resolved, $dir);

            return true;
        }

        if ($this->applyRelatedTableSortFromRecordField(
            $query,
            $resolved,
            $sp['key'],
            $fieldsSchema,
            $tableName,
            $dbColumns,
            $actualColumns,
            $dir
        )) {
            return true;
        }

        if (str_contains($resolved, '.')) {
            $query->orderBy($resolved, $dir);

            return true;
        }

        if (! in_array($resolved, $dbColumns, true)) {
            return false;
        }

        if ($this->applyEnumLabelSortIfApplicable(
            $query,
            $tableName.'.'.$resolved,
            $fieldsSchema,
            $sp['key'],
            $resolved,
            $dir
        )) {
            return true;
        }

        $query->orderBy($tableName.'.'.$resolved, $dir);

        return true;
    }

    /**
     * Get unwrapped fields schema (handles both wrapped and unwrapped structures)
     */
    protected function getUnwrappedFieldsSchema(): array
    {
        $fieldsSchemaRaw = $this->getFieldsSchema();
        if (! is_array($fieldsSchemaRaw)) {
            return [];
        }

        $unwrapped = isset($fieldsSchemaRaw['fields']) ? $fieldsSchemaRaw['fields'] : $fieldsSchemaRaw;

        return is_array($unwrapped) ? $unwrapped : [];
    }

    public function index(Request $request)
    {
        $columns = $this->getSchemaColumns();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $schema = $this->getTableSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();

        // Separate actual database columns from relationship columns,
        // filtering out virtual $appends attributes that don't exist in the DB.
        $actualColumns = [];
        $relationshipColumns = [];

        $tableName = $this->recordModel->getTable();
        $dbColumns = \Schema::connection($this->recordModel->getConnectionName())
            ->getColumnListing($tableName);

        foreach ($columns as $column) {
            if (strpos($column, '.') !== false) {
                // Relationship column like "asset.display_name"
                $relationshipColumns[] = $column;
            } elseif (in_array($column, $dbColumns)) {
                // Only SELECT columns that actually exist in the database.
                // Virtual $appends attributes are automatically included by Eloquent.
                $actualColumns[] = $column;
            }
        }

        // If the model uses $appends (virtual accessors), include all real DB columns
        // so that accessor dependencies (e.g. `sequence` for display_name) are always loaded.
        if (! empty($this->recordModel->getAppends())) {
            $actualColumns = array_values(array_unique(array_merge($actualColumns, $dbColumns)));
        }

        if (! in_array('id', $actualColumns)) {
            $actualColumns[] = 'id';
        }

        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        // Load relationships needed for display names
        if ($this->domainName === 'AssetUnit') {
            $relationships['asset'] = function ($query) {
                $query->select(['id', 'display_name']);
            };
        } elseif ($this->domainName === 'InventoryUnit') {
            $relationships['inventoryItem'] = function ($query) {
                $query->select(['id', 'display_name']);
            };
        }

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);

                // Determine which fields to select for this relationship
                $selectFields = ['id'];

                // Handle special cases for models that don't have display_name column
                if ($fieldDef['typeDomain'] === 'AssetUnit') {
                    // AssetUnit uses accessor for display_name, so select the underlying columns and load asset relationship
                    $selectFields = ['id', 'serial_number', 'hin', 'sku', 'asset_id'];
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'serial_number', 'hin', 'sku', 'asset_id'])
                            ->with(['asset' => function ($q) {
                                $q->select(['id', 'display_name']);
                            }]);
                    };
                } elseif (in_array($fieldDef['typeDomain'], ['Qualification', 'Contract', 'Delivery'], true)) {
                    // Accessor display_name (e.g. CTR- / DLV- prefix from sequence), not a DB column
                    $selectFields = ['id', 'sequence'];
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'sequence']);
                    };
                } elseif ($fieldDef['typeDomain'] === 'Transaction' || $fieldDef['typeDomain'] === 'Estimate') {
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'sequence']);
                    };
                } elseif ($fieldDef['typeDomain'] === 'Customer') {
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'contact_id'])
                            ->with(['contact' => function ($q) {
                                $q->select(['id', 'display_name', 'first_name', 'last_name']);
                            }]);
                    };
                } else {
                    $selectFields[] = 'display_name';
                }

                // If a custom displayField is specified, add it to the select
                if (isset($fieldDef['displayField']) && $fieldDef['displayField'] !== 'display_name') {
                    $selectFields[] = $fieldDef['displayField'];
                }

                // Make sure we have unique fields
                $selectFields = array_unique($selectFields);

                // Only set the relationship if it wasn't already set for AssetUnit
                if (! isset($relationships[$relationshipName])) {
                    $relationships[$relationshipName] = function ($query) use ($selectFields) {
                        $query->select($selectFields);
                    };
                }
            }
        }

        $query = $this->recordModel->select($actualColumns)->with($relationships);

        $searchQuery = $request->get('search');
        if ($searchQuery && ! empty(trim($searchQuery))) {
            // Allow subclasses to define their own search logic
            $customHandled = $this->applyCustomSearch($query, trim($searchQuery));

            if (! $customHandled) {
                // Check if display_name column exists, otherwise search in typical display name fields
                $tableName = $this->recordModel->getTable();
                $hasDisplayName = \Schema::connection($this->recordModel->getConnectionName())
                    ->hasColumn($tableName, 'display_name');

                if ($hasDisplayName) {
                    $query->whereRaw('LOWER(display_name) LIKE ?', ['%'.strtolower(trim($searchQuery)).'%']);
                } else {
                    // Search in fields that typically make up display names
                    $searchTerm = '%'.strtolower(trim($searchQuery)).'%';
                    if ($this->domainName === 'AssetUnit') {
                        $query->where(function ($q) use ($searchTerm) {
                            $q->whereRaw('LOWER(asset_units.serial_number) LIKE ?', [$searchTerm])
                                ->orWhereRaw('LOWER(asset_units.hin) LIKE ?', [$searchTerm])
                                ->orWhereRaw('LOWER(asset_units.sku) LIKE ?', [$searchTerm])
                                ->orWhereRaw('CAST(asset_units.id AS TEXT) LIKE ?', [$searchTerm])
                                ->orWhereHas('asset', function ($aq) use ($searchTerm) {
                                    $aq->whereRaw('LOWER(display_name) LIKE ?', [$searchTerm]);
                                });
                        });
                    } elseif ($this->domainName === 'InventoryUnit') {
                        $query->where(function ($q) use ($searchTerm) {
                            $q->whereRaw('LOWER(inventory_units.serial_number) LIKE ?', [$searchTerm])
                                ->orWhereRaw('LOWER(inventory_units.hin) LIKE ?', [$searchTerm])
                                ->orWhereRaw('LOWER(inventory_units.sku) LIKE ?', [$searchTerm])
                                ->orWhereRaw('CAST(inventory_units.id AS TEXT) LIKE ?', [$searchTerm])
                                ->orWhereHas('inventoryItem', function ($iq) use ($searchTerm) {
                                    $iq->whereRaw('LOWER(display_name) LIKE ?', [$searchTerm]);
                                });
                        });
                    } else {
                        $query->where(function ($q) use ($searchTerm) {
                            $q->whereRaw('LOWER(serial_number) LIKE ?', [$searchTerm])
                                ->orWhereRaw('LOWER(hin) LIKE ?', [$searchTerm])
                                ->orWhereRaw('LOWER(sku) LIKE ?', [$searchTerm])
                                ->orWhereRaw('CAST(id AS TEXT) LIKE ?', [$searchTerm]);
                        });
                    }
                }
            } // end !customHandled
        }

        $filtersParam = $request->get('filters');
        if ($filtersParam) {
            try {
                $filters = json_decode(urldecode($filtersParam), true);
                if (is_array($filters)) {
                    $query = $this->applyFilters($query, $filters, $fieldsSchema);
                }
            } catch (\Exception $e) {
                // ignore invalid filters
            }
        }

        $statsBaseQuery = clone $query;

        // Order: ?sort=&direction= from table schema (sortable defaults true), else defaults below
        if (! $this->applyRecordIndexSort($query, $request, $schema, $dbColumns, $tableName, $actualColumns, $fieldsSchema)) {
            $hasDisplayName = \Schema::connection($this->recordModel->getConnectionName())
                ->hasColumn($tableName, 'display_name');

            if ($hasDisplayName) {
                $query->orderByRaw('LOWER('.$tableName.'.display_name) ASC');
            } else {
                // For models with virtual display names (like AssetUnit, InventoryUnit), order by parent item then unit identifier
                if ($this->domainName === 'AssetUnit') {
                    $prefixedColumns = array_map(function ($col) use ($dbColumns, $tableName) {
                        return in_array($col, $dbColumns, true) ? $tableName.'.'.$col : $col;
                    }, $actualColumns);
                    $query->select($prefixedColumns)
                        ->join('assets', 'asset_units.asset_id', '=', 'assets.id')
                        ->orderBy('assets.display_name')
                        ->orderByRaw("COALESCE(NULLIF(asset_units.serial_number, ''), NULLIF(asset_units.hin, ''), NULLIF(asset_units.sku, ''), CAST(asset_units.id AS TEXT))");
                } elseif ($this->domainName === 'InventoryUnit') {
                    $prefixedColumns = array_map(function ($col) use ($dbColumns, $tableName) {
                        return in_array($col, $dbColumns, true) ? $tableName.'.'.$col : $col;
                    }, $actualColumns);
                    $query->select($prefixedColumns)
                        ->join('inventory_items', 'inventory_units.inventory_item_id', '=', 'inventory_items.id')
                        ->orderBy('inventory_items.display_name')
                        ->orderByRaw("COALESCE(NULLIF(inventory_units.serial_number, ''), NULLIF(inventory_units.hin, ''), NULLIF(inventory_units.sku, ''), CAST(inventory_units.id AS TEXT))");
                } else {
                    $query->orderBy($tableName.'.created_at', 'desc');
                }
            }
        }

        $perPage = $request->get('per_page', 15);
        $records = $query->paginate($perPage);

        $tableStats = $this->indexTableStats($request, $statsBaseQuery, $schema);

        // Return JSON for Inertia / AJAX / JSON requests only
        if ($request->ajax() && ! $request->header('X-Inertia')) {
            return response()->json([
                'records' => $records->items(),
                'schema' => $schema,
                'fieldsSchema' => $fieldsSchema, // Already unwrapped above
                'stats' => $tableStats,
                'meta' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ],
            ]);
        }

        // Normal initial page load - return Inertia page
        $indexProps = $this->indexInertiaProps($request, $records, $schema, $fieldsSchema, $formSchema, $enumOptions);
        $indexProps['stats'] = $tableStats;
        $indexProps = array_merge($indexProps, $this->indexSupplementInertiaProps($request));

        return inertia(
            'Tenant/'.$this->domainName.'/Index',
            $indexProps
        );
    }

    /**
     * Extra keys merged into the domain Index Inertia payload (e.g. Assets page bundles a units table).
     */
    protected function indexSupplementInertiaProps(Request $request): array
    {
        return [];
    }

    /**
     * Optional aggregate counts for table stat cards (see table.json "stats"). Override per domain.
     * Receives the same filtered query as the index list, before sort/pagination.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    protected function indexTableStats(Request $request, $query, ?array $schema): array
    {
        return [];
    }

    /**
     * Props for the domain Index Inertia page. Override in child controllers (e.g. Asset) to add keys.
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator  $records
     */
    protected function indexInertiaProps(Request $request, $records, $schema, array $fieldsSchema, $formSchema, array $enumOptions): array
    {
        return [
            'records' => $records,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'pluralTitle' => Str::plural($this->recordTitle),
            'schema' => $schema,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
        ];
    }

    public function create()
    {
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();

        // Get account settings for timezone display (cached)
        $account = \App\Models\AccountSettings::getCurrent();

        return inertia('Tenant/'.$this->domainName.'/Create', [
            'recordType' => $this->recordType,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
        ]);
    }

    public function store(Request $request, PublicStorage $publicStorage)
    {
        try {
            $data = $request->all();
            $fieldsSchema = $this->getUnwrappedFieldsSchema();

            // Handle image uploads (creates Document records)
            foreach ($fieldsSchema as $fieldKey => $fieldDef) {
                if (isset($fieldDef['type']) && $fieldDef['type'] === 'image') {
                    if ($request->hasFile($fieldKey)) {
                        $file = $request->file($fieldKey);

                        // Get meta options if available
                        $meta = $fieldDef['meta'] ?? [];

                        // Default options
                        $directory = $meta['directory'] ?? ($this->domainName.'/'.$fieldKey); // e.g., "User/avatar"
                        $isPrivate = $meta['private'] ?? false;
                        $resizeWidth = $meta['max_width'] ?? null;
                        $crop = $meta['crop'] ?? false;

                        // Store the image
                        $result = $publicStorage->store(
                            file: $file,
                            directory: $directory,
                            resizeWidth: $resizeWidth,
                            existingFile: null,
                            crop: $crop,
                            deleteOld: false,
                            isPrivate: $isPrivate
                        );

                        // Create Document record
                        $document = Document::create([
                            'display_name' => $result['display_name'],
                            'file' => $result['key'],
                            'file_extension' => $result['file_extension'],
                            'file_size' => $result['file_size'],
                            'created_by_id' => auth()->id(),
                            'updated_by_id' => auth()->id(),
                        ]);

                        // Update data to save the document ID instead of the file object/key
                        $data[$fieldKey] = $document->id;
                    }
                }
            }

            $result = ($this->createAction)($data);

            // Handle case where action returns a model directly (for backward compatibility)
            if (! is_array($result)) {
                $result = [
                    'success' => true,
                    'record' => $result,
                ];
            }

            if ($result['success']) {
                // If it's an AJAX request that wants JSON, return JSON instead of redirecting
                if ($request->ajax() && ! $request->header('X-Inertia')) {
                    // Reload the record with relationships to ensure display_name is available
                    $fieldsSchema = $this->getUnwrappedFieldsSchema();
                    $relationships = $this->getRelationshipsToLoad($fieldsSchema);

                    // Add record type relationships with id, display_name, and custom displayField
                    foreach ($fieldsSchema as $fieldKey => $fieldDef) {
                        if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                            $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);

                            // Determine which fields to select for this relationship
                            $selectFields = ['id'];

                            // Handle special cases for models that don't have display_name column
                            if ($fieldDef['typeDomain'] === 'AssetUnit') {
                                // AssetUnit uses accessor for display_name, so select the underlying columns and load asset relationship
                                $selectFields = ['id', 'serial_number', 'hin', 'sku', 'asset_id'];
                                $relationships[$relationshipName] = function ($query) {
                                    $query->select(['id', 'serial_number', 'hin', 'sku', 'asset_id'])
                                        ->with(['asset' => function ($q) {
                                            $q->select(['id', 'display_name']);
                                        }]);
                                };
                            } elseif (in_array($fieldDef['typeDomain'], ['Qualification', 'Contract', 'Delivery'], true)) {
                                // Accessor display_name from sequence, not a DB column
                                $selectFields = ['id', 'sequence'];
                                $relationships[$relationshipName] = function ($query) {
                                    $query->select(['id', 'sequence']);
                                };
                            } elseif ($fieldDef['typeDomain'] === 'Transaction' || $fieldDef['typeDomain'] === 'Estimate') {
                                $relationships[$relationshipName] = function ($query) {
                                    $query->select(['id', 'sequence']);
                                };
                            } elseif ($fieldDef['typeDomain'] === 'Customer') {
                                $relationships[$relationshipName] = function ($query) {
                                    $query->select(['id', 'contact_id'])
                                        ->with(['contact' => function ($q) {
                                            $q->select(['id', 'display_name', 'first_name', 'last_name']);
                                        }]);
                                };
                            } else {
                                $selectFields[] = 'display_name';
                            }

                            // If a custom displayField is specified, add it to the select
                            if (isset($fieldDef['displayField']) && $fieldDef['displayField'] !== 'display_name') {
                                $selectFields[] = $fieldDef['displayField'];
                            }

                            // Make sure we have unique fields
                            $selectFields = array_unique($selectFields);

                            // Only set the relationship if it wasn't already set for AssetUnit
                            if (! isset($relationships[$relationshipName])) {
                                $relationships[$relationshipName] = function ($query) use ($selectFields) {
                                    $query->select($selectFields);
                                };
                            }
                        }
                    }

                    // Reload the record with relationships
                    $record = $this->recordModel->with($relationships)->find($result['record']->id);

                    return response()->json([
                        'success' => true,
                        'recordId' => $result['record']->id,
                        'record' => $record,
                        'message' => $this->domainName.' created successfully',
                    ]);
                }

                return redirect()
                    ->route($this->recordType.'.show', $result['record']->id)
                    ->with('success', $this->domainName.' created successfully')
                    ->with('recordId', $result['record']->id);
            }

            // Handle errors for AJAX requests
            if ($request->ajax() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'errors' => $result['errors'] ?? [],
                    'message' => $result['message'] ?? 'Failed to create '.$this->recordTitle,
                ], 422);
            }

            return back()
                ->withInput()
                ->with('error', $result['message'] ?? 'Failed to create '.$this->recordTitle);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors
            if ($request->ajax() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                    'message' => 'Validation failed',
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors($e->errors());
        }
    }

    /**
     * Merge extra eager loads before {@see show()} and {@see edit()} load the record. Override in domain controllers (e.g. invoices).
     */
    protected function appendShowRelationships(array &$relationships): void {}

    public function show(Request $request, $id)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();

        // Build relationships array including both morph and record types
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        // Add record type relationships with id, display_name, and custom displayField
        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                // Convert field key like 'assigned_id' to relationship name like 'assigned'
                $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);

                // Determine which fields to select for this relationship
                $selectFields = ['id'];

                // Handle special cases for models that don't have display_name column
                if ($fieldDef['typeDomain'] === 'AssetUnit') {
                    // AssetUnit uses accessor for display_name, so select the underlying columns and load asset relationship
                    $selectFields = ['id', 'serial_number', 'hin', 'sku', 'asset_id'];
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'serial_number', 'hin', 'sku', 'asset_id'])
                            ->with(['asset' => function ($q) {
                                $q->select(['id', 'display_name']);
                            }]);
                    };
                } elseif (in_array($fieldDef['typeDomain'], ['Qualification', 'Contract', 'Delivery'], true)) {
                    // Accessor display_name from sequence, not a DB column
                    $selectFields = ['id', 'sequence'];
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'sequence']);
                    };
                } elseif ($fieldDef['typeDomain'] === 'Transaction' || $fieldDef['typeDomain'] === 'Estimate') {
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'sequence']);
                    };
                } elseif ($fieldDef['typeDomain'] === 'Customer') {
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'contact_id'])
                            ->with(['contact' => function ($q) {
                                $q->select(['id', 'display_name', 'first_name', 'last_name']);
                            }]);
                    };
                } else {
                    $selectFields[] = 'display_name';
                }

                // If a custom displayField is specified, add it to the select
                if (isset($fieldDef['displayField']) && $fieldDef['displayField'] !== 'display_name') {
                    $selectFields[] = $fieldDef['displayField'];
                }

                // Make sure we have unique fields
                $selectFields = array_unique($selectFields);

                // Only set the relationship if it wasn't already set for AssetUnit
                if (! isset($relationships[$relationshipName])) {
                    $relationships[$relationshipName] = function ($query) use ($selectFields) {
                        $query->select($selectFields);
                    };
                }
            }
        }

        // Load form schema to check for sublists and spec groups
        $formSchema = $this->getFormSchema();

        // Add sublist relationships (for model relationships like hasMany, belongsToMany)
        if (isset($formSchema['sublists']) && is_array($formSchema['sublists'])) {
            foreach ($formSchema['sublists'] as $sublist) {
                if (isset($sublist['modelRelationship'])) {
                    $relationships[$sublist['modelRelationship']] = function ($query) {
                        // Load basic fields for the related records
                        $query->select('*');
                    };
                }
            }
        }

        // Detect if the form has a specs section and eager-load spec values
        $formGroups = $formSchema['form'] ?? $formSchema;
        $hasSpecsGroup = is_array($formGroups) && collect($formGroups)
            ->contains(fn ($g) => is_array($g) && ($g['type'] ?? null) === 'specs');

        if ($hasSpecsGroup) {
            $relationships['specValues'] = fn ($q) => $q->with('definition');
        }

        $this->appendShowRelationships($relationships);

        // Load the record with relationships
        $record = $this->recordModel
            ->with($relationships)
            ->findOrFail($id);

        $availableSpecs = $hasSpecsGroup && isset($record->type)
            ? AvailableAssetSpecsCache::get((int) $record->type)
            : [];

        $enumOptions = $this->getEnumOptions();

        // Get account settings for timezone display (cached)
        $account = \App\Models\AccountSettings::getCurrent();

        // If it's a non-Inertia AJAX request, return JSON with full record data
        if ($request->ajax() && ! $request->header('X-Inertia')) {
            return response()->json(array_merge([
                'record' => $record,
                'recordType' => $this->recordType,
                'recordTitle' => $this->recordTitle,
                'domainName' => $this->domainName,
                'formSchema' => $formSchema,
                'fieldsSchema' => $fieldsSchema,
                'enumOptions' => $enumOptions,
                'imageUrls' => $this->getImageUrls($record, $fieldsSchema),
                'account' => $account,
                'timezones' => Timezone::options(),
                'availableSpecs' => $availableSpecs,
            ], $this->showPageExtraProps($record)));
        }

        // Return Inertia response (for navigation and partial reloads)
        return inertia('Tenant/'.$this->domainName.'/Show', array_merge([
            'record' => $record,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'domainName' => $this->domainName,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'imageUrls' => $this->getImageUrls($record, $fieldsSchema),
            'account' => $account,
            'timezones' => Timezone::options(),
            'availableSpecs' => $availableSpecs,
        ], $this->showPageExtraProps($record)));
    }

    /**
     * Extra Inertia/JSON props for the generic record show page (subclasses may override).
     *
     * @param  \Illuminate\Database\Eloquent\Model  $record
     */
    protected function showPageExtraProps($record): array
    {
        return [];
    }

    public function edit($id)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();

        // Build relationships array for loading related data
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        // Add record type relationships with id, display_name, and custom displayField
        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                // Convert field key like 'assigned_id' to relationship name like 'assigned'
                $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);

                // Determine which fields to select for this relationship
                $selectFields = ['id'];

                // Handle special cases for models that don't have display_name column
                if ($fieldDef['typeDomain'] === 'AssetUnit') {
                    // AssetUnit uses accessor for display_name, so select the underlying columns and load asset relationship
                    $selectFields = ['id', 'serial_number', 'hin', 'sku', 'asset_id'];
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'serial_number', 'hin', 'sku', 'asset_id'])
                            ->with(['asset' => function ($q) {
                                $q->select(['id', 'display_name']);
                            }]);
                    };
                } elseif (in_array($fieldDef['typeDomain'], ['Qualification', 'Contract', 'Delivery'], true)) {
                    // Accessor display_name from sequence, not a DB column
                    $selectFields = ['id', 'sequence'];
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'sequence']);
                    };
                } elseif ($fieldDef['typeDomain'] === 'Transaction' || $fieldDef['typeDomain'] === 'Estimate') {
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'sequence']);
                    };
                } elseif ($fieldDef['typeDomain'] === 'Customer') {
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'contact_id'])
                            ->with(['contact' => function ($q) {
                                $q->select(['id', 'display_name', 'first_name', 'last_name']);
                            }]);
                    };
                } else {
                    $selectFields[] = 'display_name';
                }

                // If a custom displayField is specified, add it to the select
                if (isset($fieldDef['displayField']) && $fieldDef['displayField'] !== 'display_name') {
                    $selectFields[] = $fieldDef['displayField'];
                }

                // Make sure we have unique fields
                $selectFields = array_unique($selectFields);

                // Only set the relationship if it wasn't already set for AssetUnit
                if (! isset($relationships[$relationshipName])) {
                    $relationships[$relationshipName] = function ($query) use ($selectFields) {
                        $query->select($selectFields);
                    };
                }
            }
        }

        $formSchema = $this->getFormSchema();

        // Add sublist relationships (same as show(), so edit pages receive variants, units, etc.)
        if (isset($formSchema['sublists']) && is_array($formSchema['sublists'])) {
            foreach ($formSchema['sublists'] as $sublist) {
                if (isset($sublist['modelRelationship'])) {
                    $relationships[$sublist['modelRelationship']] = function ($query) {
                        $query->select('*');
                    };
                }
            }
        }

        // Detect if the form has a specs section and eager-load spec values
        $formGroups = $formSchema['form'] ?? $formSchema;
        $hasSpecsGroup = is_array($formGroups) && collect($formGroups)
            ->contains(fn ($g) => is_array($g) && ($g['type'] ?? null) === 'specs');

        if ($hasSpecsGroup) {
            $relationships['specValues'] = fn ($q) => $q->with('definition');
        }

        $this->appendShowRelationships($relationships);

        // Load the record with relationships
        $record = $this->recordModel->with($relationships)->findOrFail($id);

        $availableSpecs = $hasSpecsGroup && isset($record->type)
            ? AvailableAssetSpecsCache::get((int) $record->type)
            : [];

        $enumOptions = $this->getEnumOptions();

        // Get account settings for timezone display (cached)
        $account = \App\Models\AccountSettings::getCurrent();

        return inertia('Tenant/'.$this->domainName.'/Edit', array_merge([
            'record' => $record,
            'recordType' => $this->recordType,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'imageUrls' => $this->getImageUrls($record, $fieldsSchema),
            'account' => $account,
            'timezones' => Timezone::options(),
            'availableSpecs' => $availableSpecs,
        ], $this->editPageExtraProps($record)));
    }

    /**
     * Extra Inertia props for the generic record edit page (subclasses may override).
     *
     * @param  \Illuminate\Database\Eloquent\Model  $record
     */
    protected function editPageExtraProps($record): array
    {
        return [];
    }

    /**
     * Redirect after a successful Inertia update (subclasses may override).
     */
    protected function inertiaUpdateSuccessRedirect(Request $request, int|string $id): RedirectResponse
    {
        return back()->with('success', $this->domainName.' updated successfully');
    }

    public function update(Request $request, $id, PublicStorage $publicStorage)
    {
        try {
            $data = $request->all();
            // dd($data);
            $fieldsSchema = $this->getUnwrappedFieldsSchema();

            // Handle image uploads (creates Document records)
            foreach ($fieldsSchema as $fieldKey => $fieldDef) {
                if (isset($fieldDef['type']) && $fieldDef['type'] === 'image') {
                    // Get current record to find existing image
                    $currentRecord = $this->recordModel->find($id);
                    $existingDocumentId = $currentRecord ? $currentRecord->{$fieldKey} : null;

                    if ($request->hasFile($fieldKey)) {
                        $file = $request->file($fieldKey);

                        // Get meta options
                        $meta = $fieldDef['meta'] ?? [];

                        // Default options
                        $directory = $meta['directory'] ?? ($this->domainName.'/'.$fieldKey);
                        $isPrivate = $meta['private'] ?? false;
                        $resizeWidth = $meta['max_width'] ?? null;
                        $crop = $meta['crop'] ?? false;

                        $existingDocument = $existingDocumentId ? Document::find($existingDocumentId) : null;
                        $existingFileKey = $existingDocument ? $existingDocument->file : null;

                        // Store new image and delete old one
                        $storageResult = $publicStorage->store(
                            file: $file,
                            directory: $directory,
                            resizeWidth: $resizeWidth,
                            existingFile: $existingFileKey,
                            crop: $crop,
                            deleteOld: true,
                            isPrivate: $isPrivate
                        );

                        // Create New Document record
                        $document = Document::create([
                            'display_name' => $storageResult['display_name'],
                            'file' => $storageResult['key'],
                            'file_extension' => $storageResult['file_extension'],
                            'file_size' => $storageResult['file_size'],
                            'created_by_id' => auth()->id(),
                            'updated_by_id' => auth()->id(),
                        ]);

                        // Delete old document record
                        if ($existingDocument) {
                            $existingDocument->delete();
                        }

                        // Update data
                        $data[$fieldKey] = $document->id;
                    } elseif (isset($data[$fieldKey]) && $data[$fieldKey] == $existingDocumentId) {
                        // Value is unchanged ID, remove from data to bypass validation "image" rule
                        unset($data[$fieldKey]);
                    }
                }
            }
            $result = ($this->updateAction)($id, $data);

            if ($result['success']) {

                // Check if this is a non-Inertia AJAX request (axios from preventRedirect)
                if ($request->ajax() && ! $request->header('X-Inertia')) {

                    // Reload the record with relationships to ensure display_name is available
                    $fieldsSchema = $this->getUnwrappedFieldsSchema();
                    $relationships = $this->getRelationshipsToLoad($fieldsSchema);

                    // Add record type relationships with id, display_name, and custom displayField
                    foreach ($fieldsSchema as $fieldKey => $fieldDef) {
                        if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                            $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);

                            // Determine which fields to select for this relationship
                            $selectFields = ['id'];

                            // Handle special cases for models that don't have display_name column
                            if ($fieldDef['typeDomain'] === 'AssetUnit') {
                                // AssetUnit uses accessor for display_name, so select the underlying columns and load asset relationship
                                $selectFields = ['id', 'serial_number', 'hin', 'sku', 'asset_id'];
                                $relationships[$relationshipName] = function ($query) {
                                    $query->select(['id', 'serial_number', 'hin', 'sku', 'asset_id'])
                                        ->with(['asset' => function ($q) {
                                            $q->select(['id', 'display_name']);
                                        }]);
                                };
                            } elseif (in_array($fieldDef['typeDomain'], ['Qualification', 'Contract', 'Delivery'], true)) {
                                // Accessor display_name from sequence, not a DB column
                                $selectFields = ['id', 'sequence'];
                                $relationships[$relationshipName] = function ($query) {
                                    $query->select(['id', 'sequence']);
                                };
                            } elseif ($fieldDef['typeDomain'] === 'Transaction' || $fieldDef['typeDomain'] === 'Estimate') {
                                $relationships[$relationshipName] = function ($query) {
                                    $query->select(['id', 'sequence']);
                                };
                            } elseif ($fieldDef['typeDomain'] === 'Customer') {
                                $relationships[$relationshipName] = function ($query) {
                                    $query->select(['id', 'contact_id'])
                                        ->with(['contact' => function ($q) {
                                            $q->select(['id', 'display_name', 'first_name', 'last_name']);
                                        }]);
                                };
                            } else {
                                $selectFields[] = 'display_name';
                            }

                            // If a custom displayField is specified, add it to the select
                            if (isset($fieldDef['displayField']) && $fieldDef['displayField'] !== 'display_name') {
                                $selectFields[] = $fieldDef['displayField'];
                            }

                            // Make sure we have unique fields
                            $selectFields = array_unique($selectFields);

                            // Only set the relationship if it wasn't already set for AssetUnit
                            if (! isset($relationships[$relationshipName])) {
                                $relationships[$relationshipName] = function ($query) use ($selectFields) {
                                    $query->select($selectFields);
                                };
                            }
                        }
                    }

                    // Reload the record with relationships
                    $record = $this->recordModel->with($relationships)->find($id);

                    return response()->json([
                        'success' => true,
                        'record' => $record,
                        'message' => $this->domainName.' updated successfully',
                    ]);
                }

                // Inertia Response (Always redirect for Inertia requests)
                return $this->inertiaUpdateSuccessRedirect($request, $id);
            }

            // Handle business logic errors
            if ($request->ajax() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'errors' => $result['errors'] ?? [],
                    'message' => $result['message'] ?? 'Failed to update '.$this->recordTitle,
                ], 422);
            }

            // For Inertia requests, return back with errors
            return back()
                ->withInput()
                ->withErrors(['general' => $result['message'] ?? 'Failed to update '.$this->recordTitle]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors
            if ($request->ajax() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                    'message' => 'Validation failed',
                ], 422);
            }

            // For Inertia requests, throw the exception and let Inertia handle it
            throw $e;
        }
    }

    public function destroy($id)
    {
        $result = ($this->deleteAction)($id);

        if ($result['success']) {
            return redirect()
                ->route($this->recordType.'.index')
                ->with('success', $this->domainName.' deleted successfully');
        }

        return back()
            ->with('error', $result['message'] ?? 'Failed to delete '.$this->recordTitle);
    }

    public function lookup(Request $request)
    {
        // Determine which columns to select
        $columns = ['id', 'display_name'];

        $query = $this->recordModel->select($columns);

        // Apply search query
        $searchQuery = $request->get('search');
        if ($searchQuery && ! empty(trim($searchQuery))) {
            // Default search on display_name
            $query->whereRaw('LOWER(display_name) LIKE ?', ['%'.strtolower(trim($searchQuery)).'%']);
        }

        // Get per_page from request, default to 15
        $perPage = $request->get('per_page', 15);
        $records = $query->paginate($perPage);

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

    protected function getEnumOptions(): array
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = [];

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['enum']) && $fieldDef['enum']) {
                $enumClass = $fieldDef['enum'];

                if (class_exists($enumClass) && method_exists($enumClass, 'options')) {
                    $enumOptions[$enumClass] = $enumClass::options();
                } elseif (class_exists($enumClass)) {
                    // Fallback: if no options method, create from cases
                    $enumOptions[$enumClass] = array_map(fn ($case) => [
                        'id' => $case->value,
                        'name' => $case->name ?? $case->value,
                    ], $enumClass::cases());
                }
            }
        }

        return $enumOptions;
    }

    /**
     * Attach a related record via Many-to-Many relationship
     */
    public function attachRelationship(Request $request, $id)
    {
        $request->validate([
            'relationship' => 'required|string',
            'related_id' => 'required|integer',
        ]);

        try {
            $record = $this->recordModel->findOrFail($id);
            $relationship = $request->input('relationship');
            $relatedId = $request->input('related_id');

            // Check if relationship exists and is a BelongsToMany
            if (! method_exists($record, $relationship)) {
                return response()->json([
                    'success' => false,
                    'message' => "Relationship '{$relationship}' does not exist on this model.",
                ], 400);
            }

            $relationshipInstance = $record->$relationship();

            if (! ($relationshipInstance instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany)) {
                return response()->json([
                    'success' => false,
                    'message' => "Relationship '{$relationship}' is not a Many-to-Many relationship.",
                ], 400);
            }

            // Check if already attached
            if ($relationshipInstance->where($relationshipInstance->getRelated()->getQualifiedKeyName(), $relatedId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This record is already attached.',
                ], 400);
            }

            // Attach the record
            $relationshipInstance->attach($relatedId);

            return response()->json([
                'success' => true,
                'message' => 'Record attached successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to attach record: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Detach a related record via Many-to-Many relationship
     */
    public function detachRelationship(Request $request, $id)
    {
        $request->validate([
            'relationship' => 'required|string',
            'related_id' => 'required|integer',
        ]);

        try {
            $record = $this->recordModel->findOrFail($id);
            $relationship = $request->input('relationship');
            $relatedId = $request->input('related_id');

            // Check if relationship exists and is a BelongsToMany
            if (! method_exists($record, $relationship)) {
                return response()->json([
                    'success' => false,
                    'message' => "Relationship '{$relationship}' does not exist on this model.",
                ], 400);
            }

            $relationshipInstance = $record->$relationship();

            if (! ($relationshipInstance instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany)) {
                return response()->json([
                    'success' => false,
                    'message' => "Relationship '{$relationship}' is not a Many-to-Many relationship.",
                ], 400);
            }

            // Detach the record
            $relationshipInstance->detach($relatedId);

            return response()->json([
                'success' => true,
                'message' => 'Record detached successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to detach record: '.$e->getMessage(),
            ], 500);
        }
    }
}
