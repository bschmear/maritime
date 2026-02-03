<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\BoatMake\Models\BoatMake as RecordModel;
use App\Domain\BoatMake\Actions\CreateBoatMake as CreateAction;
use App\Domain\BoatMake\Actions\UpdateBoatMake as UpdateAction;
use App\Domain\BoatMake\Actions\DeleteBoatMake as DeleteAction;
use Illuminate\Http\Request;

class BoatMakeController extends RecordController
{
    protected $recordType = 'BoatMake';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'boatmakes',
            'BoatMake',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}