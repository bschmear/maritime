<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Domain\PortalAccess\Models\PortalAccess as RecordModel;
use App\Domain\PortalAccess\Actions\CreatePortalAccess as CreateAction;
use App\Domain\PortalAccess\Actions\UpdatePortalAccess as UpdateAction;
use App\Domain\PortalAccess\Actions\DeletePortalAccess as DeleteAction;

class PortalAccessController extends Controller
{
    protected $model;
    protected $createAction;
    protected $updateAction;
    protected $deleteAction;

    public function __construct(
        RecordModel $model,
        CreateAction $createAction,
        UpdateAction $updateAction,
        DeleteAction $deleteAction
    ) {
        $this->model = $model;
        $this->createAction = $createAction;
        $this->updateAction = $updateAction;
        $this->deleteAction = $deleteAction;
    }

    /**
     * List portal access records
     */
    public function index()
    {
        return $this->model->latest()->paginate(25);
    }

    /**
     * Store new portal access
     */
    public function store(Request $request)
    {
        $record = ($this->createAction)($request->all());

        return response()->json($record, 201);
    }

    /**
     * Show portal access record
     */
    public function show($id)
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Update portal access
     */
    public function update(Request $request, $id)
    {
        $record = $this->model->findOrFail($id);

        $updated = ($this->updateAction)($record, $request->all());

        return response()->json($updated);
    }

    /**
     * Delete portal access
     */
    public function destroy($id)
    {
        $record = $this->model->findOrFail($id);

        ($this->deleteAction)($record);

        return response()->json([
            'message' => 'Portal access deleted'
        ]);
    }

    /**
     * Revoke portal access token
     */
    public function revoke($id)
    {
        $record = $this->model->findOrFail($id);

        $record->update([
            'revoked_at' => now()
        ]);

        return response()->json([
            'message' => 'Portal access revoked'
        ]);
    }
}
