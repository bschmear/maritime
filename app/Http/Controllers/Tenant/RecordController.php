<?php

namespace App\Http\Controllers\Tenant;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class RecordController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

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

    protected function getTableSchema()
    {
        $schemaPath = app_path("Domain/{$this->domainName}/Schema/table.json");
        
        if (!file_exists($schemaPath)) {
            return null;
        }

        $schema = json_decode(file_get_contents($schemaPath), true);
        return $schema;
    }

    protected function getFormSchema()
    {
        $schemaPath = app_path("Domain/{$this->domainName}/Schema/form.json");

        if (!file_exists($schemaPath)) {
            return null;
        }

        $schema = json_decode(file_get_contents($schemaPath), true);
        return $schema;
    }

    protected function getFieldsSchema()
    {
        $schemaPath = app_path("Domain/{$this->domainName}/Schema/fields.json");

        if (!file_exists($schemaPath)) {
            return null;
        }

        $schema = json_decode(file_get_contents($schemaPath), true);
        return $schema;
    }

    protected function getEnumOptions()
    {
        $fieldsSchema = $this->getFieldsSchema();

        if (!$fieldsSchema) {
            return [];
        }

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
                $modelClass = "Domain\\{$domainName}\\Models\\{$domainName}";

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

    protected function getSchemaColumns()
    {
        $schema = $this->getTableSchema();
        
        if (!$schema || !isset($schema['columns'])) {
            return ['*'];
        }

        // Extract column keys from schema, always include 'id'
        $columns = ['id'];
        foreach ($schema['columns'] as $column) {
            if (isset($column['key'])) {
                $columns[] = $column['key'];
            }
        }

        return $columns;
    }

    public function index(Request $request)
    {
        $columns = $this->getSchemaColumns();
        $schema = $this->getTableSchema();
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getFieldsSchema();
        $enumOptions = $this->getEnumOptions();

        $query = $this->recordModel->select($columns)->with($this->getRelationshipsToLoad($fieldsSchema));
        
        // Apply search query (fuzzy search on display_name, case-insensitive)
        $searchQuery = $request->get('search');
        if ($searchQuery && !empty(trim($searchQuery))) {
            $query->whereRaw('LOWER(display_name) LIKE ?', ['%' . strtolower(trim($searchQuery)) . '%']);
        }
        
        // Apply filters from query parameters
        $filtersParam = $request->get('filters');
        if ($filtersParam) {
            try {
                $filters = json_decode(urldecode($filtersParam), true);
                if (is_array($filters)) {
                    $query = $this->applyFilters($query, $filters, $fieldsSchema);
                }
            } catch (\Exception $e) {
                // Invalid filters, ignore
            }
        }
        
        $records = $query->paginate(15);

        // Pluralize the record title for the index page
        $pluralTitle = Str::plural($this->recordTitle);

        return inertia('Tenant/' . $this->domainName . '/Index', [
            'records' => $records,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle, // Singular for "Add" button
            'pluralTitle' => $pluralTitle, // Plural for table heading
            'schema' => $schema,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
        ]);
    }

    protected function applyFilters($query, array $filters, $fieldsSchema)
    {
        foreach ($filters as $filter) {
            if (!isset($filter['field']) || !isset($filter['operator'])) {
                continue;
            }
            
            $field = $filter['field'];
            $operator = $filter['operator'];
            $value = $filter['value'] ?? null;
            
            $fieldConfig = $fieldsSchema[$field] ?? [];
            $fieldType = $fieldConfig['type'] ?? 'text';
            
            switch ($operator) {
                case 'contains':
                    $query->where($field, 'LIKE', "%{$value}%");
                    break;
                case 'equals':
                    $query->where($field, '=', $value);
                    break;
                case 'starts_with':
                    $query->where($field, 'LIKE', "{$value}%");
                    break;
                case 'ends_with':
                    $query->where($field, 'LIKE', "%{$value}");
                    break;
                case 'is_empty':
                    $query->where(function($q) use ($field) {
                        $q->whereNull($field)->orWhere($field, '');
                    });
                    break;
                case 'is_not_empty':
                    $query->whereNotNull($field)->where($field, '!=', '');
                    break;
                case 'not_equals':
                    $query->where($field, '!=', $value);
                    break;
                case 'any_of':
                    if (is_array($value)) {
                        $query->whereIn($field, $value);
                    } else {
                        $query->where($field, '=', $value);
                    }
                    break;
                case 'none_of':
                    if (is_array($value)) {
                        $query->whereNotIn($field, $value);
                    } else {
                        $query->where($field, '!=', $value);
                    }
                    break;
                case 'before':
                    $query->where($field, '<', $value);
                    break;
                case 'after':
                    $query->where($field, '>', $value);
                    break;
                case 'between':
                    if (is_array($value)) {
                        $start = $value['start'] ?? $value['min'] ?? null;
                        $end = $value['end'] ?? $value['max'] ?? null;
                        if ($start && $end) {
                            $query->whereBetween($field, [$start, $end]);
                        }
                    }
                    break;
                case 'today':
                    $query->whereDate($field, '=', now()->toDateString());
                    break;
                case 'this_week':
                    $query->whereBetween($field, [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'this_month':
                    $query->whereMonth($field, now()->month)->whereYear($field, now()->year);
                    break;
                case 'greater_than':
                    $query->where($field, '>', $value);
                    break;
                case 'less_than':
                    $query->where($field, '<', $value);
                    break;
                case 'is_true':
                    $query->where($field, '=', 1)->orWhere($field, '=', true);
                    break;
                case 'is_false':
                    $query->where(function($q) use ($field) {
                        $q->where($field, '=', 0)
                          ->orWhere($field, '=', false)
                          ->orWhereNull($field);
                    });
                    break;
            }
        }
        
        return $query;
    }

    public function create()
    {
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getFieldsSchema();
        $enumOptions = $this->getEnumOptions();

        return inertia('Tenant/' . $this->domainName . '/Create', [
            'recordType' => $this->recordType,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $result = ($this->createAction)($request->all());

            // Handle case where action returns a model directly (for backward compatibility)
            if (!is_array($result)) {
                $result = [
                    'success' => true,
                    'record' => $result,
                ];
            }

            if ($result['success']) {
                // If it's an AJAX request that wants JSON, return JSON instead of redirecting
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'recordId' => $result['record']->id,
                        'message' => $this->domainName . ' created successfully',
                    ]);
                }

                return redirect()
                    ->route($this->recordType . '.show', $result['record']->id)
                    ->with('success', $this->domainName . ' created successfully')
                    ->with('recordId', $result['record']->id);
            }

            // Handle errors for AJAX requests
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $result['errors'] ?? [],
                    'message' => $result['message'] ?? 'Failed to create ' . $this->recordTitle,
                ], 422);
            }

            return back()
                ->withInput()
                ->with('error', $result['message'] ?? 'Failed to create ' . $this->recordTitle);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors
            if ($request->wantsJson()) {
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

    public function show(Request $request, $id)
    {
        $fieldsSchema = $this->getFieldsSchema();
        
        // Load the record with relationships
        $record = $this->recordModel
            ->with($this->getRelationshipsToLoad($fieldsSchema))
            ->findOrFail($id);
            
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();

        // Always return Inertia response for navigation
        // Inertia requests have X-Inertia header, not X-Requested-With
        return inertia('Tenant/' . $this->domainName . '/Show', [
            'record' => $record,
            'recordType' => $this->recordType,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
        ]);
    }

    public function edit($id)
    {
        $record = $this->recordModel->findOrFail($id);
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getFieldsSchema();
        $enumOptions = $this->getEnumOptions();

        return inertia('Tenant/' . $this->domainName . '/Edit', [
            'record' => $record,
            'recordType' => $this->recordType,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
        ]);
    }

    public function update(Request $request, $id)
    {
        $result = ($this->updateAction)($id, $request->all());

        if ($result['success']) {
            // If it's an AJAX request that wants JSON, return JSON instead of redirecting
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'record' => $result['record'],
                    'message' => $this->domainName . ' updated successfully',
                ]);
            }

            return redirect()
                ->route($this->recordType . '.show', $id)
                ->with('success', $this->domainName . ' updated successfully');
        }

        // Handle errors for AJAX requests
        if ($request->wantsJson()) {
            return response()->json([
                'success' => false,
                'errors' => $result['errors'] ?? [],
                'message' => $result['message'] ?? 'Failed to update ' . $this->recordTitle,
            ], 422);
        }

        return back()
            ->withInput()
            ->with('error', $result['message'] ?? 'Failed to update ' . $this->recordTitle);
    }

    public function destroy($id)
    {
        $result = ($this->deleteAction)($id);

        if ($result['success']) {
            return redirect()
                ->route($this->recordType . '.index')
                ->with('success', $this->domainName . ' deleted successfully');
        }

        return back()
            ->with('error', $result['message'] ?? 'Failed to delete ' . $this->recordTitle);
    }
}
