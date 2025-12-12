<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Invoice\Models\Invoice as RecordModel;
use App\Domain\Invoice\Actions\CreateInvoice as CreateAction;
use App\Domain\Invoice\Actions\UpdateInvoice as UpdateAction;
use App\Domain\Invoice\Actions\DeleteInvoice as DeleteAction;
use Illuminate\Http\Request;

class InvoiceController extends RecordController
{
    protected $recordType = 'Invoice';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'invoices',
            'Invoice',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }

    public function index(Request $request)
    {
        return 'invoice table';
    }

    /**
     * Show a specific user with role relationship loaded.
     */
    public function show(Request $request, $id)
    {
        return inertia('Tenant/' . $this->domainName . '/Show');
    }

    /**
     * Show a specific user with role relationship loaded.
     */
    public function create()
    {
        return inertia('Tenant/' . $this->domainName . '/Create');
    }

}
