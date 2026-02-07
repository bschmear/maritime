<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\WorkOrder\Models\WorkOrder as RecordModel;
use App\Domain\WorkOrder\Actions\CreateWorkOrder as CreateAction;
use App\Domain\WorkOrder\Actions\UpdateWorkOrder as UpdateAction;
use App\Domain\WorkOrder\Actions\DeleteWorkOrder as DeleteAction;
use Illuminate\Http\Request;

class WorkOrderController extends RecordController
{
    protected $recordType = 'WorkOrder';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'workorders',
            'WorkOrder',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}