<?php

namespace App\Http\Controllers\Concerns;

use App\Domain\Transaction\Models\Transaction;
use App\Support\Enum\StoredEnumNormalizer;
use App\Support\Validation\ActionResultErrors;
use App\Support\Validation\SchemaFormValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait HasSchemaSupport
{
    /**
     * @var array<string, list<string>>
     */
    private static array $schemaTableColumnListingCache = [];

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

    /**
     * @return array<string, mixed>
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

    protected function getSchemaColumns()
    {
        $tableSchema = $this->getTableSchema();

        if (! $tableSchema || ! isset($tableSchema['columns'])) {
            return [];
        }

        $keys = [];

        foreach ($tableSchema['columns'] as $column) {
            if (! is_array($column)) {
                if (is_string($column) && $column !== '') {
                    $keys[] = $column;
                }

                continue;
            }

            $key = $column['key'] ?? null;
            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }

            $isJoined = ($column['format'] ?? '') === 'joined'
                || ($column['format'] ?? '') === 'mobile_home'
                || isset($column['keys'])
                || isset($column['join']);

            if ($isJoined) {
                $parts = $column['keys'] ?? $column['join'] ?? [];
                foreach ($parts as $part) {
                    if (is_array($part) && isset($part['key']) && is_string($part['key']) && $part['key'] !== '') {
                        $keys[] = $part['key'];
                    }
                }
            }
        }

        return array_values(array_unique($keys));
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
            ->orderBy($orderColumn)
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
                'assigned_user_id', 'type', 'source_id', 'status',
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
                'assigned_user_id', 'type', 'source_id', 'status',
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
            $value = StoredEnumNormalizer::normalizeForField(
                $filter['value'] ?? null,
                $field,
                $fieldsSchema,
            );

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

    protected function modelConnectionName(Model $model): string
    {
        $name = $model->getConnectionName();
        if (is_string($name) && $name !== '') {
            return $name;
        }

        return $model->getConnection()->getName();
    }

    /**
     * @return list<string>
     */
    protected function getTableColumnListingFor(string $connection, string $table): array
    {
        $key = $connection.'.'.$table;

        if (! isset(self::$schemaTableColumnListingCache[$key])) {
            self::$schemaTableColumnListingCache[$key] = Schema::connection($connection)->getColumnListing($table);
        }

        return self::$schemaTableColumnListingCache[$key];
    }

    /**
     * Map a table column key from table.json to a real database column on the primary table.
     */
    protected function resolveRecordIndexSortColumn(string $requestKey, array $dbColumns): ?string
    {
        if (in_array($requestKey, $dbColumns, true)) {
            return $requestKey;
        }

        if ($requestKey === 'display_name' && in_array('sequence', $dbColumns, true)) {
            return 'sequence';
        }

        $domainName = $this->domainName ?? null;
        if ($requestKey === 'display_name' && $domainName === 'ChartOfAccount' && in_array('name', $dbColumns, true)) {
            return 'name';
        }

        return null;
    }

    protected function shouldSortColumnCaseInsensitive(string $column): bool
    {
        return in_array($column, [
            'display_name',
            'email',
            'company',
            'source',
            'first_name',
            'last_name',
            'name',
            'title',
            'position',
            'city',
            'secondary_email',
            'website',
        ], true);
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
            $id = $opt['id'] ?? $opt['value'] ?? null;
            $name = $opt['name'] ?? $opt['label'] ?? null;
            if ($id === null || $name === null || (! is_string($name) && ! is_numeric($name))) {
                continue;
            }
            $caseParts[] = 'when '.$qualifiedColumn.' = ? then lower(?)';
            $bindings[] = $id;
            $bindings[] = (string) $name;
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

        /** @var Model $relatedModel */
        $relatedModel = new $modelClass;
        if ($relatedModel->getTable() !== $joinTable) {
            return false;
        }

        if (! in_array($sortRequestKey, $dbColumns, true)) {
            return false;
        }

        $connection = $this->modelConnectionName($relatedModel);
        if (! in_array($joinColumn, $this->getTableColumnListingFor($connection, $joinTable), true)) {
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

    protected function applyRecordForeignKeySort(
        $query,
        string $sortRequestKey,
        array $fieldsSchema,
        string $tableName,
        array $dbColumns,
        array $actualColumns,
        string $dir,
        string $joinColumn = 'display_name',
    ): bool {
        if (! in_array($sortRequestKey, $dbColumns, true)) {
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

        /** @var Model $relatedModel */
        $relatedModel = new $modelClass;

        return $this->applyRelatedTableSortFromRecordField(
            $query,
            $relatedModel->getTable().'.'.$joinColumn,
            $sortRequestKey,
            $fieldsSchema,
            $tableName,
            $dbColumns,
            $actualColumns,
            $dir,
        );
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
        $domainName = $this->domainName ?? null;

        if ($domainName === 'AssetUnit') {
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

        if ($domainName === 'InventoryUnit') {
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
            if (preg_match('/^([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)$/', $resolved, $m)
                && $this->shouldSortColumnCaseInsensitive($m[2])) {
                $query->orderByRaw('LOWER('.$resolved.') '.$dir);
            } else {
                $query->orderBy($resolved, $dir);
            }

            return true;
        }

        if (! in_array($resolved, $dbColumns, true)) {
            return false;
        }

        if ($this->applyRecordForeignKeySort(
            $query,
            $sp['key'],
            $fieldsSchema,
            $tableName,
            $dbColumns,
            $actualColumns,
            $dir,
        )) {
            return true;
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

        if ($this->shouldSortColumnCaseInsensitive($resolved)) {
            $query->orderByRaw('LOWER('.$tableName.'.'.$resolved.') '.$dir);
        } else {
            $query->orderBy($tableName.'.'.$resolved, $dir);
        }

        return true;
    }

    /**
     * Sort index queries that join `contacts` (customers, leads). Caller must already join contacts.
     *
     * @param  list<string>  $contactBackedSortKeys
     */
    protected function applyJoinedContactIndexSort(
        $query,
        Request $request,
        ?array $schema,
        string $primaryTable,
        array $primaryDbColumns,
        array $fieldsSchema,
        array $contactBackedSortKeys = [],
    ): bool {
        $allowed = $this->sortableColumnsFromTableSchema($schema);
        $sp = $this->sortParamsFromRequest($request);
        if ($sp['key'] === null || ! isset($allowed[$sp['key']])) {
            return false;
        }

        $key = $sp['key'];
        $dir = $sp['dir'];

        if ($contactBackedSortKeys === []) {
            $contactBackedSortKeys = [
                'display_name',
                'email',
                'phone',
                'mobile',
                'first_name',
                'last_name',
                'company',
                'position',
                'title',
                'secondary_email',
                'website',
                'assigned_user_id',
            ];
        }

        if ($key === 'assigned_user_id' && in_array($key, $contactBackedSortKeys, true)) {
            $query->leftJoin('users', 'contacts.assigned_user_id', '=', 'users.id')
                ->orderByRaw('LOWER(users.display_name) '.$dir);

            return true;
        }

        if (in_array($key, $contactBackedSortKeys, true)) {
            $qualified = 'contacts.'.$key;
            if ($this->shouldSortColumnCaseInsensitive($key)) {
                $query->orderByRaw('LOWER('.$qualified.') '.$dir);
            } else {
                $query->orderBy($qualified, $dir);
            }

            return true;
        }

        if (in_array($key, $primaryDbColumns, true)) {
            $qualified = $primaryTable.'.'.$key;

            if ($this->applyEnumLabelSortIfApplicable(
                $query,
                $qualified,
                $fieldsSchema,
                $key,
                $key,
                $dir
            )) {
                return true;
            }

            if ($this->applyRecordForeignKeySort(
                $query,
                $key,
                $fieldsSchema,
                $primaryTable,
                $primaryDbColumns,
                $primaryDbColumns,
                $dir,
            )) {
                return true;
            }

            if ($this->shouldSortColumnCaseInsensitive($key)) {
                $query->orderByRaw('LOWER('.$qualified.') '.$dir);
            } else {
                $query->orderBy($qualified, $dir);
            }

            return true;
        }

        return false;
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
    protected function actionFailureResponse(Request $request, array $result, ?array $fieldsSchema = null, string $action = 'create'): RedirectResponse|JsonResponse
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
