<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\ServiceTicketRevision\Models\ServiceTicketRevision as RecordModel;
use App\Domain\ServiceTicketRevision\Actions\CreateServiceTicketRevision as CreateAction;
use App\Domain\ServiceTicketRevision\Actions\UpdateServiceTicketRevision as UpdateAction;
use App\Domain\ServiceTicketRevision\Actions\DeleteServiceTicketRevision as DeleteAction;
use Illuminate\Http\Request;

class ServiceTicketRevisionController extends RecordController
{
    protected $recordType = 'ServiceTicketRevision';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'serviceticketrevisions',
            'ServiceTicketRevision',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}