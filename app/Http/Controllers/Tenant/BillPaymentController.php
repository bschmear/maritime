<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\BillPayment\Models\BillPayment as RecordModel;
use App\Domain\BillPayment\Actions\CreateBillPayment as CreateAction;
use App\Domain\BillPayment\Actions\UpdateBillPayment as UpdateAction;
use App\Domain\BillPayment\Actions\DeleteBillPayment as DeleteAction;
use Illuminate\Http\Request;

class BillPaymentController extends RecordController
{
    protected $recordType = 'BillPayment';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'billpayments',
            'BillPayment',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}