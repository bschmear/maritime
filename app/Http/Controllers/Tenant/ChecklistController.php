<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Checklist\Models\Checklist as RecordModel;
use App\Domain\Checklist\Actions\CreateChecklist as CreateAction;
use App\Domain\Checklist\Actions\UpdateChecklist as UpdateAction;
use App\Domain\Checklist\Actions\DeleteChecklist as DeleteAction;
use Illuminate\Http\Request;

class ChecklistController extends RecordController
{
    protected $recordType = 'Checklist';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'checklists',
            'Checklist',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}