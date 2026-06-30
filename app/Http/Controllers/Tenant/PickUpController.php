<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\PickUp\Models\PickUp as RecordModel;
use App\Domain\PickUp\Actions\CreatePickUp as CreateAction;
use App\Domain\PickUp\Actions\UpdatePickUp as UpdateAction;
use App\Domain\PickUp\Actions\DeletePickUp as DeleteAction;
use Illuminate\Http\Request;

class PickUpController extends RecordController
{
    protected $recordType = 'PickUp';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'pickups',
            'PickUp',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}