<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\InvoiceLine\Models\InvoiceLine as RecordModel;
use App\Domain\InvoiceLine\Actions\CreateInvoiceLine as CreateAction;
use App\Domain\InvoiceLine\Actions\UpdateInvoiceLine as UpdateAction;
use App\Domain\InvoiceLine\Actions\DeleteInvoiceLine as DeleteAction;
use Illuminate\Http\Request;

class InvoiceLineController extends RecordController
{
    protected $recordType = 'InvoiceLine';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'invoicelines',
            'InvoiceLine',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}