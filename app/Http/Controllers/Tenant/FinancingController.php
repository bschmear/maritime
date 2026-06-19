<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Financing\Models\Financing as RecordModel;
use App\Domain\Financing\Actions\CreateFinancing as CreateAction;
use App\Domain\Financing\Actions\UpdateFinancing as UpdateAction;
use App\Domain\Financing\Actions\DeleteFinancing as DeleteAction;
use Illuminate\Http\Request;

class FinancingController extends RecordController
{
    protected $recordType = 'Financing';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'financings',
            'Financing',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}