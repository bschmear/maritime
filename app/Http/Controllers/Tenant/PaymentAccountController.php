<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\PaymentAccount\Actions\CreatePaymentAccount as CreateAction;
use App\Domain\PaymentAccount\Actions\DeletePaymentAccount as DeleteAction;
use App\Domain\PaymentAccount\Actions\UpdatePaymentAccount as UpdateAction;
use App\Domain\PaymentAccount\Models\PaymentAccount as RecordModel;
use Illuminate\Http\Request;

class PaymentAccountController extends RecordController
{
    protected $recordType = 'PaymentAccount';

    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'paymentaccounts',
            'PaymentAccount',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            $this->recordType // Domain name for schema lookup
        );
    }
}
