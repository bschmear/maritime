<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Payment\Actions\CreatePayment as CreateAction;
use App\Domain\Payment\Actions\DeletePayment as DeleteAction;
use App\Domain\Payment\Actions\UpdatePayment as UpdateAction;
use App\Domain\Payment\Models\Payment as RecordModel;
use Illuminate\Http\Request;

class PaymentController extends RecordController
{
    protected $recordType = 'Payment';

    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'payments',
            'Payment',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            $this->recordType // Domain name for schema lookup
        );
    }
}
