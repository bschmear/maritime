<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\PaymentRefund\Actions\CreatePaymentRefund as CreateAction;
use App\Domain\PaymentRefund\Actions\DeletePaymentRefund as DeleteAction;
use App\Domain\PaymentRefund\Actions\UpdatePaymentRefund as UpdateAction;
use App\Domain\PaymentRefund\Models\PaymentRefund as RecordModel;
use Illuminate\Http\Request;

class PaymentRefundController extends RecordController
{
    protected $recordType = 'PaymentRefund';

    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'paymentrefunds',
            'PaymentRefund',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            $this->recordType // Domain name for schema lookup
        );
    }
}
