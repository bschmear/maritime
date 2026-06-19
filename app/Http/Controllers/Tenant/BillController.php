<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Bill\Models\Bill as RecordModel;
use App\Domain\Bill\Actions\CreateBill as CreateAction;
use App\Domain\Bill\Actions\UpdateBill as UpdateAction;
use App\Domain\Bill\Actions\DeleteBill as DeleteAction;
use Illuminate\Http\Request;

class BillController extends RecordController
{
    protected $recordType = 'Bill';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'bills',
            'Bill',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}