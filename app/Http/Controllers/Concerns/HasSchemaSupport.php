<?php

namespace App\Http\Controllers\Concerns;

use App\Domain\Transaction\Models\Transaction;
use App\Support\Validation\ActionResultErrors;
use App\Support\Validation\SchemaFormValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait HasSchemaSupport
{
    protected function getTableSchema()
    {
        $domainName = $this->domainName ?? $this->recordTitle ?? $this->recordType;
        $schemaPath = app_path("Domain/{$domainName}/Schema/table.json");

        if (! file_exists($schemaPath)) {
            return null;
        }

        $schema = json_decode(file_get_contents($schemaPath), true);

        return $schema;
    }

    protected function getFormSchema()
    {
        $domainName = $this->domainName ?? $this->recordTitle ?? $this->recordType;
        $schemaPath = app_path("Domain/{$domainName}/Schema/form.json");

        if (! file_exists($schemaPath)) {
            return null;
        }

        $schema = json_decode(file_get_contents($schemaPath), true);

        return $schema;
    }

    protected function getFieldsSchema()
    {
        $domainName = $this->domainName ?? $this->recordTitle ?? $this->recordType;
        $schemaPath = app_path("Domain/{$domainName}/Schema/fields.json");

        if (! file_exists($schemaPath)) {
            return null;
        }

        $schema = json_decode(file_get_contents($schemaPath), true);

        return $schema;
    }

    protected function getSchemaColumns()
    {
        $tableSchema = $this->getTableSchema();

        if (! $tableSchema || ! isset($tableSchema['columns'])) {
            return [];
        }

        // Extract just the 'key' values from the columns array
        return array_map(function ($column) {
            return is_array($column) ? ($column['key'] ?? $column) : $column;
        }, $tableSchema['columns']);
    }

    protected function getEnumOptions()
    {
        $fieldsSchemaRaw = $this->getFieldsSchema();

        if (! $fieldsSchemaRaw) {
            return [];
        }

        // Handle fields wrapper like GeneralController does
        $fieldsSchema = isset($fieldsSchemaRaw['fields']) ? $fieldsSchemaRaw['fields'] : $fieldsSchemaRaw;

        return static::enumOptionsFromUnwrappedFields($fieldsSchema);
    }

    /**
     * Build enum + record select options from an unwrapped fields schema (same keys as {@see getEnumOptions()}).
     *
     * @param  array<string, mixed>  $fieldsSchema
     * @return array<string, mixed>
     */
    public static function enumOptionsFromUnwrappedFields(array $fieldsSchema): array
    {
        $enumOptions = [];

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            $fieldType = $fieldDef['type'] ?? 'text';

            if (isset($fieldDef['enum']) && ! empty($fieldDef['enum'])) {
                $enumClass = $fieldDef['enum'];

                if (class_exists($enumClass) && method_exists($enumClass, 'options')) {
                    $enumOptions[$enumClass] = $enumClass::options();
                }
            }

            if ($fieldType === 'record' && isset($fieldDef['typeDomain'])) {
                $domainName = $fieldDef['typeDomain'];
                $modelClass = "App\\Domain\\{$domainName}\\Models\\{$domainName}";

                if (class_exists($modelClass)) {
                    try {
                        if ($domainName === 'BoatShow') {
                            $records = $modelClass::query()->select(['id', 'display_name'])->orderBy('display_name')->get();
                            $options = $records->map(function ($record) {
                                return [
                                    'id' => $record->id,
                                    'name' => $record->display_name,
                                    'value' => $record->id,
                                ];
                            })->toArray();
                        } elseif ($domainName === 'Customer') {
                            $model = new $modelClass;
                            $profileTable = $model->getTable();
                            $records = $modelClass::query()
                                ->join('contacts', 'contacts.id', '=', $profileTable.'.contact_id')
                                ->select([$profileTable.'.id', 'contacts.display_name'])
                                ->orderBy('contacts.display_name')
                                ->get();
                            $options = $records->map(function ($record) {
                                return [
                                    'id' => $record->id,
                                    'name' => $record->display_name,
                                    'value' => $record->id,
                                ];
                            })->toArray();
                        } else {
                            $options = static::loadRecordSelectOptions($modelClass);
                        }

                        $enumOptions[$fieldKey] = $options;
                    } catch (\Exception $e) {
                        \Log::warning("Failed to load record options for {$domainName}: ".$e->getMessage());
                        $enumOptions[$fieldKey] = [];
                    }
                }
            }
        }

        return $enumOptions;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return list<array{id: mixed, name: string, value: mixed}>
     */
    public static function loadRecordSelectOptions(string $modelClass, int $limit = 500): array
    {
        $model = new $modelClass;
        $columns = static::recordOptionSelectColumns($model);
        $orderColumn = $columns[1] ?? 'id';

        $records = $modelClass::query()
            ->select($columns)
            ->orderByDesc($orderColumn)
            ->limit($limit)
            ->get();

        return $records->map(fn (Model $record) => [
            'id' => $record->id,
            'name' => static::recordOptionDisplayName($record),
            'value' => $record->id,
        ])->all();
    }

    /**
     * Columns to select for record dropdowns (display_name may be an accessor, not a DB column).
     *
     * @return list<string>
     */
    protected static function recordOptionSelectColumns(Model $model): array
    {
        $table = $model->getTable();
        $connection = $model->getConnectionName();

        $columns = ['id'];
        if (Schema::connection($connection)->hasColumn($table, 'display_name')) {
            $columns[] = 'display_name';
        } elseif (Schema::connection($connection)->hasColumn($table, 'sequence')) {
            $columns[] = 'sequence';
        } elseif (Schema::connection($connection)->hasColumn($table, 'name')) {
            $columns[] = 'name';
        } elseif (Schema::connection($connection)->hasColumn($table, 'work_order_number')) {
            $columns[] = 'work_order_number';
        }

        return $columns;
    }

    protected static function recordOptionDisplayName(Model $record): string
    {
        if (method_exists($record, 'getDisplayNameAttribute')) {
            $name = $record->display_name;
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        $workOrderNumber = $record->getAttribute('work_order_number');
        if ($workOrderNumber !== null && $workOrderNumber !== '') {
            return 'WO-'.$workOrderNumber;
        }

        foreach (['display_name', 'name', 'sequence'] as $attribute) {
            $value = $record->getAttribute($attribute);
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return '#'.$record->getKey();
    }

    /**
     * Exact public method names on the model. Used instead of method_exists() for relationship
     * names because PHP treats method names as case-insensitive, so "created_by" would match
     * createdBy() but Eloquent::with() requires the real method name string.
     *
     * @return array<string, true>
     */
    protected function getModelMethodIndex(): array
    {
        return array_flip(get_class_methods($this->recordModel));
    }

    protected function getRelationshipsToLoad($fieldsSchema)
    {
        if (! $fieldsSchema) {
            return [];
        }

        $relationships = [];
        $methods = $this->getModelMethodIndex();

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            $fieldType = $fieldDef['type'] ?? 'text';

            // Handle morph relationships
            if ($fieldType === 'morph') {
                // For morph fields, the relationship is stored in the field key itself
                // e.g., relatable_type -> relatable relationship
                $relationshipName = $fieldKey;

                // Remove '_type' suffix if present
                if (str_ends_with($relationshipName, '_type')) {
                    $relationshipName = substr($relationshipName, 0, -5);
                }

                if (isset($methods[$relationshipName])) {
                    $relationships[] = $relationshipName;
                }
            }

            if ($fieldType === 'record' && isset($fieldDef['typeDomain'])) {
                if (! empty($fieldDef['relationship']) && isset($methods[$fieldDef['relationship']])) {
                    $relationships[] = $fieldDef['relationship'];

                    continue;
                }

                // Try to infer the relationship name from the field key
                $relationshipName = $fieldKey;

                // Remove common suffixes and prefixes
                if (substr($relationshipName, -3) === '_id') {
                    $relationshipName = substr($relationshipName, 0, -3); // Remove '_id'
                }
                if (substr($relationshipName, 0, 8) === 'current_') {
                    $relationshipName = substr($relationshipName, 8); // Remove 'current_' prefix
                }

                // Try singular version if it ends with 's'
                if (substr($relationshipName, -1) === 's') {
                    $relationshipName = substr($relationshipName, 0, -1);
                }

                if (isset($methods[$relationshipName])) {
                    $relationships[] = $relationshipName;
                } else {
                    // Try alternative relationship names
                    $alternatives = [
                        $fieldKey, // Original field key
                        $fieldKey.'_data', // With _data suffix
                        strtolower($fieldDef['typeDomain']), // Domain name lowercase
                    ];

                    // created_by / technician_submitted_by -> createdBy, technicianSubmittedBy
                    if (str_ends_with($fieldKey, '_by')) {
                        $alternatives[] = Str::camel($fieldKey);
                        $baseName = str_replace('_by', '', $fieldKey);
                        $alternatives[] = $baseName.'By';
                    }

                    foreach ($alternatives as $altRelationship) {
                        if (isset($methods[$altRelationship])) {
                            $relationships[] = $altRelationship;
                            break;
                        }
                    }
                }
            }
        }

        return array_unique($relationships);
    }

    protected function getFieldWithTablePrefix($field)
    {
        if ($this->domainName === 'Lead') {
            $contactBacked = [
                'display_name', 'first_name', 'last_name', 'email', 'phone', 'mobile',
                'company', 'position', 'title', 'secondary_email', 'website', 'linkedin', 'facebook',
                'notes', 'inactive', 'preferred_contact_method', 'preferred_contact_time',
                'assigned_user_id', 'type', 'source', 'status',
            ];
            if (in_array($field, $contactBacked, true)) {
                return 'contacts.'.$field;
            }
            $tableName = $this->recordModel->getTable();
            if (\Schema::connection($this->recordModel->getConnectionName())->hasColumn($tableName, $field)) {
                return $tableName.'.'.$field;
            }

            return $field;
        }

        if ($this->domainName === 'Customer') {
            $contactBacked = [
                'display_name', 'first_name', 'last_name', 'email', 'phone', 'mobile',
                'company', 'position', 'title', 'secondary_email', 'website', 'linkedin', 'facebook',
                'notes', 'inactive', 'preferred_contact_method', 'preferred_contact_time',
                'assigned_user_id', 'type', 'source', 'status',
            ];
            if (in_array($field, $contactBacked, true)) {
                return 'contacts.'.$field;
            }
            $tableName = $this->recordModel->getTable();
            if (\Schema::connection($this->recordModel->getConnectionName())->hasColumn($tableName, $field)) {
                return $tableName.'.'.$field;
            }

            return $field;
        }

        // For domains that may join with other tables, prefix fields that exist on the main table
        $domainsWithJoins = ['AssetUnit', 'InventoryUnit'];

        if (in_array($this->domainName, $domainsWithJoins)) {
            $tableName = $this->recordModel->getTable();
            // Check if this field exists on the main table
            if (\Schema::connection($this->recordModel->getConnectionName())->hasColumn($tableName, $field)) {
                return $tableName.'.'.$field;
            }
        }

        return $field;
    }

    /**
     * Filters from ?filters= (JSON). When the query param is absent, apply table.json defaults.
     * When present (including encoded empty array), use only URL filters — no merge.
     */
    protected function resolveIndexFiltersFromRequest(Request $request, ?array $tableSchema): array
    {
        $filtersParam = $request->query('filters');
        if ($filtersParam !== null && $filtersParam !== '') {
            try {
                $decoded = json_decode(urldecode((string) $filtersParam), true);

                return is_array($decoded) ? $decoded : [];
            } catch (\Throwable $e) {
                return [];
            }
        }

        return $this->defaultFiltersFromTableSchema(is_array($tableSchema) ? $tableSchema : []);
    }

    /**
     * Build filter rows from table.json "filters". Only rows that explicitly request a default
     * are merged when ?filters= is absent — quick-filter-only rows (field + label, etc.) are skipped.
     *
     * Opt-in:
     * - `apply_as_default`: true
     * - `default_value`: present and not false (use true for unary ops; scalars/arrays for operands)
     */
    protected function defaultFiltersFromTableSchema(array $tableSchema): array
    {
        $defs = $tableSchema['filters'] ?? [];
        $out = [];
        $baseId = (int) (microtime(true) * 10000);

        foreach ($defs as $i => $def) {
            if (! is_array($def)) {
                continue;
            }

            $field = $def['field'] ?? $def['key'] ?? null;
            if (empty($field)) {
                continue;
            }

            if (array_key_exists('default_value', $def) && $def['default_value'] === false) {
                continue;
            }

            $explicitDefault =
                ($def['apply_as_default'] ?? false) === true
                || array_key_exists('default_value', $def);

            if (! $explicitDefault) {
                continue;
            }

            $row = [
                'id' => $baseId + $i,
                'field' => $field,
                'operator' => $def['operator'] ?? 'equals',
            ];

            if (array_key_exists('value', $def)) {
                $row['value'] = $def['value'];
            } elseif (array_key_exists('default_value', $def)
                && $def['default_value'] !== true
                && $def['default_value'] !== false) {
                $row['value'] = $def['default_value'];
            }

            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    protected function specialFilterFields(): array
    {
        $common = ['make_id', 'asset_unit_id', 'asset_types'];

        return match ($this->domainName ?? '') {
            'Location' => array_merge($common, ['subsidiary_id']),
            'AssetUnit' => array_merge($common, ['customer_id']),
            'Payment' => array_merge($common, ['contact_id']),
            default => $common,
        };
    }

    protected function isAllowedFilterField(string $field, array $fieldsSchema): bool
    {
        if ($field === '' || ! preg_match('/^[a-z][a-z0-9_]*$/i', $field)) {
            return false;
        }

        return isset($fieldsSchema[$field])
            || in_array($field, $this->specialFilterFields(), true);
    }

    protected function applyFilters($query, array $filters, $fieldsSchema)
    {
        foreach ($filters as $key => $filter) {
            // Handle simple key-value filters (e.g., ['inventory_item_id' => 2])
            if (! is_array($filter)) {
                if (! $this->isAllowedFilterField((string) $key, $fieldsSchema)) {
                    continue;
                }

                $query->where($key, '=', $filter);

                continue;
            }

            // Handle structured filters with field, operator, value
            if (! isset($filter['field'])) {
                continue;
            }

            $field = $filter['field'];
            if (! $this->isAllowedFilterField($field, $fieldsSchema)) {
                continue;
            }
            $operator = $filter['operator'] ?? 'equals';
            $value = $filter['value'] ?? null;

            $fieldConfig = $fieldsSchema[$field] ?? [];
            $fieldType = $fieldConfig['type'] ?? 'text';

            switch ($operator) {
                case 'contains':
                    // Case-insensitive search using ILIKE (PostgreSQL) or LOWER()
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    if ($fieldType === 'text' || $fieldType === 'textarea' || $fieldType === 'email') {
                        $query->whereRaw('LOWER('.$fieldWithPrefix.') LIKE ?', ['%'.strtolower($value).'%']);
                    } else {
                        $query->where($fieldWithPrefix, 'LIKE', "%{$value}%");
                    }
                    break;
                case 'equals':
                    // Payment sublists on contact/lead profiles filter by invoice.contact_id.
                    if ($field === 'contact_id' && $value !== null && $value !== ''
                        && strcasecmp((string) $this->domainName, 'Payment') === 0) {
                        $query->whereHas('invoice', function ($q) use ($value) {
                            $q->where('contact_id', $value);
                        });
                        break;
                    }

                    // Transactions link to units on line items, not a transactions.asset_unit_id column.
                    if ($field === 'asset_unit_id' && $value !== null && $value !== ''
                        && strcasecmp((string) $this->domainName, 'Transaction') === 0) {
                        $query->whereHas('items', function ($q) use ($value) {
                            $q->where('asset_unit_id', $value)
                                ->where('parent_type', Transaction::class);
                        });
                        break;
                    }

                    // BoatMake: filter by selected inventory asset type (JSON array on boat_make).
                    // NULL asset_types = legacy / unrestricted (show for every type).
                    // Domain may be miscased as "Boatmake" when derived from Str::studly('boatmake').
                    if ($field === 'asset_types' && $value !== null && $value !== ''
                        && strcasecmp((string) $this->domainName, 'BoatMake') === 0) {
                        $assetType = (int) $value;
                        $query->where(function ($q) use ($assetType) {
                            $q->whereJsonContains('asset_types', $assetType)
                                ->orWhereNull('asset_types');
                        });
                        break;
                    }

                    // Check if this is a many-to-many relationship filter
                    $relationshipHandled = false;

                    // Handle Location subsidiary filtering (many-to-many)
                    if ($this->domainName === 'Location' && $field === 'subsidiary_id') {
                        $query->whereExists(function ($subQuery) use ($value) {
                            $subQuery->selectRaw('1')
                                ->from('location_subsidiary')
                                ->whereColumn('location_subsidiary.location_id', 'locations.id')
                                ->where('location_subsidiary.subsidiary_id', $value);
                        });
                        $relationshipHandled = true;
                    }

                    // Service ticket / customer scoping: show units for this customer OR unassigned (stock) units
                    if (! $relationshipHandled
                        && $this->domainName === 'AssetUnit'
                        && $field === 'customer_id'
                        && $value !== null
                        && $value !== ''
                    ) {
                        $t = $this->recordModel->getTable();
                        $cid = (int) $value;
                        $query->where(function ($q) use ($t, $cid) {
                            $q->where($t.'.customer_id', '=', $cid)
                                ->orWhereNull($t.'.customer_id');
                        });
                        $relationshipHandled = true;
                    }

                    // If not a special relationship case, handle as normal field
                    if (! $relationshipHandled) {
                        // Case-insensitive for text fields
                        if ($fieldType === 'text' || $fieldType === 'textarea' || $fieldType === 'email') {
                            $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                            $query->whereRaw('LOWER('.$fieldWithPrefix.') = ?', [strtolower($value)]);
                        } else {
                            $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                            $query->where($fieldWithPrefix, '=', $value);
                        }
                    }
                    break;
                case 'starts_with':
                    // Case-insensitive search
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    if ($fieldType === 'text' || $fieldType === 'textarea' || $fieldType === 'email') {
                        $query->whereRaw('LOWER('.$fieldWithPrefix.') LIKE ?', [strtolower($value).'%']);
                    } else {
                        $query->where($fieldWithPrefix, 'LIKE', "{$value}%");
                    }
                    break;
                case 'ends_with':
                    // Case-insensitive search
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    if ($fieldType === 'text' || $fieldType === 'textarea' || $fieldType === 'email') {
                        $query->whereRaw('LOWER('.$fieldWithPrefix.') LIKE ?', ['%'.strtolower($value)]);
                    } else {
                        $query->where($fieldWithPrefix, 'LIKE', "%{$value}");
                    }
                    break;
                case 'is_empty':
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    $query->where(function ($q) use ($fieldWithPrefix) {
                        $q->whereNull($fieldWithPrefix)->orWhere($fieldWithPrefix, '');
                    });
                    break;
                case 'is_not_empty':
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    $query->whereNotNull($fieldWithPrefix)->where($fieldWithPrefix, '!=', '');
                    break;
                case 'not_equals':
                    // Case-insensitive for text fields
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    if ($fieldType === 'text' || $fieldType === 'textarea' || $fieldType === 'email') {
                        $query->whereRaw('LOWER('.$fieldWithPrefix.') != ?', [strtolower($value)]);
                    } else {
                        $query->where($fieldWithPrefix, '!=', $value);
                    }
                    break;
                case 'any_of':
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    if (is_array($value)) {
                        $query->whereIn($fieldWithPrefix, $value);
                    } else {
                        $query->where($fieldWithPrefix, '=', $value);
                    }
                    break;
                case 'none_of':
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    if (is_array($value)) {
                        $query->whereNotIn($fieldWithPrefix, $value);
                    } else {
                        $query->where($fieldWithPrefix, '!=', $value);
                    }
                    break;
                case 'before':
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    $query->where($fieldWithPrefix, '<', $value);
                    break;
                case 'after':
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    $query->where($fieldWithPrefix, '>', $value);
                    break;
                case 'between':
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    if (is_array($value)) {
                        $start = $value['start'] ?? $value['min'] ?? null;
                        $end = $value['end'] ?? $value['max'] ?? null;
                        if ($start && $end) {
                            $query->whereBetween($fieldWithPrefix, [$start, $end]);
                        }
                    }
                    break;
                case 'today':
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    $query->whereDate($fieldWithPrefix, '=', now()->toDateString());
                    break;
                case 'this_week':
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    $query->whereBetween($fieldWithPrefix, [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'this_month':
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    $query->whereMonth($fieldWithPrefix, now()->month)->whereYear($fieldWithPrefix, now()->year);
                    break;
                case 'greater_than':
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    $query->where($fieldWithPrefix, '>', $value);
                    break;
                case 'less_than':
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    $query->where($fieldWithPrefix, '<', $value);
                    break;
                case 'is_true':
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    $query->where($fieldWithPrefix, '=', 1)->orWhere($fieldWithPrefix, '=', true);
                    break;
                case 'is_false':
                    // Handle table prefixing for domains that may have joins
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    $query->where(function ($q) use ($fieldWithPrefix) {
                        $q->where($fieldWithPrefix, '=', 0)
                            ->orWhere($fieldWithPrefix, '=', false)
                            ->orWhereNull($fieldWithPrefix);
                    });
                    break;
                case 'is_null':
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    $query->whereNull($fieldWithPrefix);
                    break;
                case 'is_not_null':
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    $query->whereNotNull($fieldWithPrefix);
                    break;
            }
        }

        return $query;
    }

    /**
     * Columns from table.json that accept sort (sortable defaults to true when omitted).
     *
     * @return array<string, array<string, mixed>>
     */
    protected function sortableColumnsFromTableSchema(?array $schema): array
    {
        $out = [];
        foreach ($schema['columns'] ?? [] as $col) {
            $key = is_array($col) ? ($col['key'] ?? null) : $col;
            if (! is_string($key) || $key === '') {
                continue;
            }
            $sortable = is_array($col) ? ($col['sortable'] ?? true) : true;
            if ($sortable === false) {
                continue;
            }
            $out[$key] = is_array($col) ? $col : ['key' => $key];
        }

        return $out;
    }

    /**
     * @return array{key: ?string, dir: 'asc'|'desc'}
     */
    protected function sortParamsFromRequest(Request $request): array
    {
        $raw = $request->get('sort');
        $key = is_string($raw) && $raw !== '' ? $raw : null;
        $dir = strtolower((string) $request->get('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        return ['key' => $key, 'dir' => $dir];
    }

    /**
     * JSON payload for Sublist / Table AJAX index requests (not Inertia visits).
     */
    protected function indexAjaxJsonResponse(
        Request $request,
        LengthAwarePaginator $paginator,
        array $schema,
        array $fieldsSchema,
        array $extra = [],
    ): ?JsonResponse {
        if (! ($request->ajax() && ! $request->header('X-Inertia'))) {
            return null;
        }

        return response()->json(array_merge([
            'records' => $paginator->items(),
            'schema' => $schema,
            'fieldsSchema' => $fieldsSchema,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], $extra));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{success: false, message: string, errors: array<string, array<int, string>>}|null
     */
    protected function validateSchemaFormInput(array $data, ?array $formSchema = null, ?array $fieldsSchema = null): ?array
    {
        $fieldsSchema ??= $this->getUnwrappedFieldsSchema();
        $formSchema ??= $this->getFormSchema();

        return SchemaFormValidator::validate($data, $formSchema, $fieldsSchema);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{errors: array<string, string|array<int, string>>, message: string}
     */
    protected function normalizeActionFailure(array $result, ?array $fieldsSchema = null): array
    {
        $fieldsSchema ??= $this->getUnwrappedFieldsSchema();
        $title = $this->recordTitle ?? $this->domainName ?? 'record';

        return ActionResultErrors::normalize($result, $fieldsSchema, $title);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function actionFailureResponse(Request $request, array $result, ?array $fieldsSchema = null, string $action = 'create'): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $normalized = $this->normalizeActionFailure($result, $fieldsSchema);
        $errors = $normalized['errors'];
        $message = $normalized['message'];

        if ($request->ajax() && ! $request->header('X-Inertia')) {
            return response()->json([
                'success' => false,
                'errors' => $errors,
                'message' => $message,
            ], 422);
        }

        return back()
            ->withInput()
            ->withErrors($errors);
    }
}
