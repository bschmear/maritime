<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use Domain\Task\Models\Task as RecordModel;
use Domain\Task\Actions\CreateTask as CreateAction;
use Domain\Task\Actions\UpdateTask as UpdateAction;
use Domain\Task\Actions\DeleteTask as DeleteAction;
use Illuminate\Http\Request;

class TaskController extends RecordController
{
    protected $recordType = 'Task';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'tasks',
            'Task',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}
