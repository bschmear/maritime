<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\ServiceTicketServiceItem\Models\ServiceTicketServiceItem as RecordModel;
use App\Domain\ServiceTicketServiceItem\Actions\CreateServiceTicketServiceItem as CreateAction;
use App\Domain\ServiceTicketServiceItem\Actions\UpdateServiceTicketServiceItem as UpdateAction;
use App\Domain\ServiceTicketServiceItem\Actions\DeleteServiceTicketServiceItem as DeleteAction;
use Illuminate\Http\Request;

class ServiceTicketServiceItemController extends RecordController
{
    protected $recordType = 'ServiceTicketServiceItem';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'serviceticketserviceitems',
            'ServiceTicketServiceItem',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}