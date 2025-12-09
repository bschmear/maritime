<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Lead\Models\Lead as RecordModel;
use App\Domain\Lead\Actions\CreateLead as CreateAction;
use App\Domain\Lead\Actions\UpdateLead as UpdateAction;
use App\Domain\Lead\Actions\DeleteLead as DeleteAction;
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