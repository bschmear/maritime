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
    protected $typeTitle;
    protected $recordModel;
    protected $createAction;
    protected $updateAction;
    protected $deleteAction;
    protected $domainName;

    public function __construct(
        Request $request,
        $recordType,
        $typeTitle,
        $recordModel,
        $createAction,
        $updateAction,
        $deleteAction,
        $domainName = null
    ) {
        $this->middleware('auth');
        $this->recordType = $recordType;
        $this->typeTitle = $typeTitle;
        $this->recordModel = $recordModel;
        $this->createAction = $createAction;
        $this->updateAction = $updateAction;
        $this->deleteAction = $deleteAction;
        $this->domainName = $domainName ?? $typeTitle;
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
        // dd($schema);
        // Select only the columns defined in the schema
        $records = $this->recordModel->select($columns)->paginate(15);

        return inertia('Tenant/' . $this->typeTitle . '/Index', [
            'records' => $records,
            'recordType' => $this->recordType,
            'schema' => $schema,
        ]);
    }

    public function create()
    {
        return inertia('Tenant/' . $this->typeTitle . '/Create', [
            'recordType' => $this->recordType,
        ]);
    }

    public function store(Request $request)
    {
        $result = ($this->createAction)($request->all());

        if ($result['success']) {
            return redirect()
                ->route($this->recordType . '.show', $result['record']->id)
                ->with('success', $this->typeTitle . ' created successfully');
        }

        return back()
            ->withInput()
            ->with('error', $result['message'] ?? 'Failed to create ' . $this->typeTitle);
    }

    public function show(Request $request, $id)
    {
        $record = $this->recordModel->findOrFail($id);

        return inertia('Tenant/' . $this->typeTitle . '/Show', [
            'record' => $record,
            'recordType' => $this->recordType,
        ]);
    }

    public function edit($id)
    {
        $record = $this->recordModel->findOrFail($id);

        return inertia('Tenant/' . $this->typeTitle . '/Edit', [
            'record' => $record,
            'recordType' => $this->recordType,
        ]);
    }

    public function update(Request $request, $id)
    {
        $result = ($this->updateAction)($id, $request->all());

        if ($result['success']) {
            return redirect()
                ->route($this->recordType . '.show', $id)
                ->with('success', $this->typeTitle . ' updated successfully');
        }

        return back()
            ->withInput()
            ->with('error', $result['message'] ?? 'Failed to update ' . $this->typeTitle);
    }

    public function destroy($id)
    {
        $result = ($this->deleteAction)($id);

        if ($result['success']) {
            return redirect()
                ->route($this->recordType . '.index')
                ->with('success', $this->typeTitle . ' deleted successfully');
        }

        return back()
            ->with('error', $result['message'] ?? 'Failed to delete ' . $this->typeTitle);
    }
}
