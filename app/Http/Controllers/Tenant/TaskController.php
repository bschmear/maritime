<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Task\Models\Task as RecordModel;
use App\Domain\Task\Actions\CreateTask as CreateAction;
use App\Domain\Task\Actions\UpdateTask as UpdateAction;
use App\Domain\Task\Actions\DeleteTask as DeleteAction;
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
