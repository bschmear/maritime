<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\ServiceTicket\Models\ServiceTicket as RecordModel;
use App\Domain\ServiceTicket\Actions\CreateServiceTicket as CreateAction;
use App\Domain\ServiceTicket\Actions\UpdateServiceTicket as UpdateAction;
use App\Domain\ServiceTicket\Actions\DeleteServiceTicket as DeleteAction;
use Illuminate\Http\Request;

class ServiceTicketController extends RecordController
{
    protected $recordType = 'ServiceTicket';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'servicetickets',
            'ServiceTicket',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}