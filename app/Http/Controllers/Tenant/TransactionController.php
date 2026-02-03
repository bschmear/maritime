<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Transaction\Models\Transaction as RecordModel;
use App\Domain\Transaction\Actions\CreateTransaction as CreateAction;
use App\Domain\Transaction\Actions\UpdateTransaction as UpdateAction;
use App\Domain\Transaction\Actions\DeleteTransaction as DeleteAction;
use Illuminate\Http\Request;

class TransactionController extends RecordController
{
    protected $recordType = 'Transaction';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'transactions',
            'Transaction',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }


    public function index(Request $request)
    {
        return inertia('Tenant/Transaction/Index');
    }

    /**
     * Show a specific user with role relationship loaded.
     */
    public function show(Request $request, $id)
    {
        return inertia('Tenant/Transaction/Show');
    }

    /**
     * Show a specific user with role relationship loaded.
     */
    public function create()
    {
        return inertia('Tenant/' . $this->domainName . '/Create');
    }



}
