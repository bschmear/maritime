<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Contract\Models\Contract as RecordModel;
use App\Domain\Contract\Actions\CreateContract as CreateAction;
use App\Domain\Contract\Actions\UpdateContract as UpdateAction;
use App\Domain\Contract\Actions\DeleteContract as DeleteAction;
use Illuminate\Http\Request;

class ContractController extends RecordController
{
    protected $recordType = 'Contract';
    protected $table = null;

    public function __construct()
    {
        parent::__construct(
            'contracts',
            'Contract',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType 
        );
    }
}