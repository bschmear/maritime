<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use Domain\Lead\Models\Lead as RecordModel;
use Domain\Lead\Actions\CreateLead as CreateAction;
use Domain\Lead\Actions\UpdateLead as UpdateAction;
use Domain\Lead\Actions\DeleteLead as DeleteAction;
use Illuminate\Http\Request;

class LeadController extends RecordController
{
    protected $recordType = 'Lead';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'leads',
            'Lead',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}