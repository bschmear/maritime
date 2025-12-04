<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use Domain\Customer\Models\Customer as RecordModel;
use Domain\Customer\Actions\CreateCustomer as CreateAction;
use Domain\Customer\Actions\UpdateCustomer as UpdateAction;
use Domain\Customer\Actions\DeleteCustomer as DeleteAction;
use Illuminate\Http\Request;

class CustomerController extends RecordController
{
    protected $recordType = 'Customer';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'customers',
            'Customer',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}