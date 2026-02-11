<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Str;

trait HasSchemaSupport
{
    protected function getTableSchema()
    {
        $domainName = $this->domainName ?? $this->recordTitle ?? $this->recordType;
        $schemaPath = app_path("Domain/{$domainName}/Schema/table.json");

        if (!file_exists($schemaPath)) {
            return null;
        }

        $schema = json_decode(file_get_contents($schemaPath), true);
        return $schema;
    }

    protected function getFormSchema()
    {
        $domainName = $this->domainName ?? $this->recordTitle ?? $this->recordType;
        $schemaPath = app_path("Domain/{$domainName}/Schema/form.json");

        if (!file_exists($schemaPath)) {
            return null;
        }

        $schema = json_decode(file_get_contents($schemaPath), true);
        return $schema;
    }

    protected function getFieldsSchema()
    {
        $domainName = $this->domainName ?? $this->recordTitle ?? $this->recordType;
        $schemaPath = app_path("Domain/{$domainName}/Schema/fields.json");

        if (!file_exists($schemaPath)) {
            return null;
        }

        $schema = json_decode(file_get_contents($schemaPath), true);
        return $schema;
    }

    protected function getSchemaColumns()
    {
        $tableSchema = $this->getTableSchema();
        
        if (!$tableSchema || !isset($tableSchema['columns'])) {
            return [];
        }

        // Extract just the 'key' values from the columns array
        return array_map(function($column) {
            return is_array($column) ? ($column['key'] ?? $column) : $column;
        }, $tableSchema['columns']);
    }

    protected function getEnumOptions()
    {
        $fieldsSchemaRaw = $this->getFieldsSchema();

        if (!$fieldsSchemaRaw) {
            return [];
        }

        // Handle fields wrapper like GeneralController does
        $fieldsSchema = isset($fieldsSchemaRaw['fields']) ? $fieldsSchemaRaw['fields'] : $fieldsSchemaRaw;

        $enumOptions = [];

        // Iterate through fields to find enum and record fields
        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            $fieldType = $fieldDef['type'] ?? 'text';

            // Handle enum fields (existing functionality)
            if (isset($fieldDef['enum']) && !empty($fieldDef['enum'])) {
                $enumClass = $fieldDef['enum'];

                // Check if the enum class exists and has an options() method
                if (class_exists($enumClass) && method_exists($enumClass, 'options')) {
                    $enumOptions[$enumClass] = $enumClass::options();
                }
            }

            // Handle record type fields (new functionality)
            if ($fieldType === 'record' && isset($fieldDef['typeDomain'])) {
                $domainName = $fieldDef['typeDomain'];
                $modelClass = "App\\Domain\\{$domainName}\\Models\\{$domainName}";

                // Check if the model class exists
                if (class_exists($modelClass)) {
                    try {
                        // Get all records from the related domain
                        $records = $modelClass::select('id', 'display_name')->get();

                        // Format as options array
                        $options = $records->map(function ($record) {
                            return [
                                'id' => $record->id,
                                'name' => $record->display_name,
                                'value' => $record->id,
                            ];
                        })->toArray();

                        // Use the field key as the options key
                        $enumOptions[$fieldKey] = $options;
                    } catch (\Exception $e) {
                        // Log error but don't break the page
                        \Log::warning("Failed to load record options for {$domainName}: " . $e->getMessage());
                        $enumOptions[$fieldKey] = [];
                    }
                }
            }
        }

        return $enumOptions;
    }

    protected function getRelationshipsToLoad($fieldsSchema)
    {
        if (!$fieldsSchema) {
            return [];
        }

        $relationships = [];

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

                // Check if the relationship exists
                if (method_exists($this->recordModel, $relationshipName)) {
                    $relationships[] = $relationshipName;
                }
            }

            if ($fieldType === 'record' && isset($fieldDef['typeDomain'])) {
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

                // Check if the relationship exists on the model
                if (method_exists($this->recordModel, $relationshipName)) {
                    $relationships[] = $relationshipName;
                } else {
                    // Try alternative relationship names
                    $alternatives = [
                        $fieldKey, // Original field key
                        $fieldKey . '_data', // With _data suffix
                        strtolower($fieldDef['typeDomain']), // Domain name lowercase
                    ];

                    // Add common Laravel relationship naming patterns
                    if (str_ends_with($fieldKey, '_by')) {
                        // For fields like created_by, updated_by -> try creator, updater
                        $baseName = str_replace('_by', '', $fieldKey);
                        $alternatives[] = $baseName . 'r'; // creator, updater
                        $alternatives[] = $baseName . 'By'; // createdBy, updatedBy (camelCase)
                    }

                    foreach ($alternatives as $altRelationship) {
                        if (method_exists($this->recordModel, $altRelationship)) {
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
        // For domains that may join with other tables, prefix fields that exist on the main table
        $domainsWithJoins = ['AssetUnit', 'InventoryUnit'];

        if (in_array($this->domainName, $domainsWithJoins)) {
            $tableName = $this->recordModel->getTable();
            // Check if this field exists on the main table
            if (\Schema::connection($this->recordModel->getConnectionName())->hasColumn($tableName, $field)) {
                return $tableName . '.' . $field;
            }
        }

        return $field;
    }

    protected function applyFilters($query, array $filters, $fieldsSchema)
    {
        foreach ($filters as $key => $filter) {
            // Handle simple key-value filters (e.g., ['inventory_item_id' => 2])
            if (!is_array($filter)) {
                $query->where($key, '=', $filter);
                continue;
            }
            
            // Handle structured filters with field, operator, value
            if (!isset($filter['field'])) {
                continue;
            }
            
            $field = $filter['field'];
            $operator = $filter['operator'] ?? 'equals';
            $value = $filter['value'] ?? null;
            
            $fieldConfig = $fieldsSchema[$field] ?? [];
            $fieldType = $fieldConfig['type'] ?? 'text';
            
            switch ($operator) {
                case 'contains':
                    // Case-insensitive search using ILIKE (PostgreSQL) or LOWER()
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    if ($fieldType === 'text' || $fieldType === 'textarea' || $fieldType === 'email') {
                        $query->whereRaw('LOWER(' . $fieldWithPrefix . ') LIKE ?', ['%' . strtolower($value) . '%']);
                    } else {
                        $query->where($fieldWithPrefix, 'LIKE', "%{$value}%");
                    }
                    break;
                case 'equals':
                    // Check if this is a many-to-many relationship filter
                    $relationshipHandled = false;

                    // Handle Location subsidiary filtering (many-to-many)
                    if ($this->domainName === 'Location' && $field === 'subsidiary_id') {
                        $query->whereExists(function($subQuery) use ($value) {
                            $subQuery->selectRaw('1')
                                ->from('location_subsidiary')
                                ->whereColumn('location_subsidiary.location_id', 'locations.id')
                                ->where('location_subsidiary.subsidiary_id', $value);
                        });
                        $relationshipHandled = true;
                    }

                    // If not a special relationship case, handle as normal field
                    if (!$relationshipHandled) {
                        // Case-insensitive for text fields
                        if ($fieldType === 'text' || $fieldType === 'textarea' || $fieldType === 'email') {
                            $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                            $query->whereRaw('LOWER(' . $fieldWithPrefix . ') = ?', [strtolower($value)]);
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
                        $query->whereRaw('LOWER(' . $fieldWithPrefix . ') LIKE ?', [strtolower($value) . '%']);
                    } else {
                        $query->where($fieldWithPrefix, 'LIKE', "{$value}%");
                    }
                    break;
                case 'ends_with':
                    // Case-insensitive search
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    if ($fieldType === 'text' || $fieldType === 'textarea' || $fieldType === 'email') {
                        $query->whereRaw('LOWER(' . $fieldWithPrefix . ') LIKE ?', ['%' . strtolower($value)]);
                    } else {
                        $query->where($fieldWithPrefix, 'LIKE', "%{$value}");
                    }
                    break;
                case 'is_empty':
                    $fieldWithPrefix = $this->getFieldWithTablePrefix($field);
                    $query->where(function($q) use ($fieldWithPrefix) {
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
                        $query->whereRaw('LOWER(' . $fieldWithPrefix . ') != ?', [strtolower($value)]);
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
                    $query->where(function($q) use ($fieldWithPrefix) {
                        $q->where($fieldWithPrefix, '=', 0)
                          ->orWhere($fieldWithPrefix, '=', false)
                          ->orWhereNull($fieldWithPrefix);
                    });
                    break;
            }
        }
        
        return $query;
    }
}
