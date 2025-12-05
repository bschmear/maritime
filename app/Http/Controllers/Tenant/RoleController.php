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
            'roles',
            'Role',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}