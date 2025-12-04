<?php

namespace App\Http\Controllers\Tenant;

use Illuminate\Http\Request;
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

        // Iterate through fields to find enum fields
        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['enum']) && !empty($fieldDef['enum'])) {
                $enumClass = $fieldDef['enum'];

                // Check if the enum class exists and has an options() method
                if (class_exists($enumClass) && method_exists($enumClass, 'options')) {
                    $enumOptions[$enumClass] = $enumClass::options();
                }
            }
        }

        return $enumOptions;
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
        $records = $this->recordModel->select($columns)->paginate(15);

        return inertia('Tenant/' . $this->domainName . '/Index', [
            'records' => $records,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'schema' => $schema,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
        ]);
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
        $record = $this->recordModel->findOrFail($id);
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getFieldsSchema();
        $enumOptions = $this->getEnumOptions();

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
