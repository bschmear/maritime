<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Tenant\RecordController;
use Domain\Role\Models\Role as RecordModel;
use Domain\Role\Actions\CreateRole as CreateAction;
use Domain\Role\Actions\UpdateRole as UpdateAction;
use Domain\Role\Actions\DeleteRole as DeleteAction;
use Illuminate\Http\Request;

class RoleController extends RecordController
{
    protected $recordType = 'Role';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'account.roles',
            'Role',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            'Role' // Domain name for schema lookup
        );
    }

    /**
     * Show a specific role with users relationship loaded.
     */
    public function show(Request $request, $id)
    {
        $record = $this->recordModel->with('users')->findOrFail($id);
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getFieldsSchema();
        $enumOptions = $this->getEnumOptions();

        // If it's an AJAX request, return JSON
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'record' => $record,
                'recordType' => $this->recordType,
                'formSchema' => $formSchema,
                'fieldsSchema' => $fieldsSchema,
                'enumOptions' => $enumOptions,
            ]);
        }

        return inertia('Tenant/' . $this->domainName . '/Show', [
            'record' => $record,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
        ]);
    }
}