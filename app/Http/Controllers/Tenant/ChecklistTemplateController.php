<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\ChecklistTemplate\Models\ChecklistTemplate as RecordModel;
use App\Domain\ChecklistTemplate\Actions\CreateChecklistTemplate as CreateAction;
use App\Domain\ChecklistTemplate\Actions\UpdateChecklistTemplate as UpdateAction;
use App\Domain\ChecklistTemplate\Actions\DeleteChecklistTemplate as DeleteAction;
use Illuminate\Http\Request;

class ChecklistTemplateController extends RecordController
{
    protected $recordType = 'ChecklistTemplate';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'checklisttemplates',
            'ChecklistTemplate',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}