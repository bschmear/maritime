<?php
namespace App\Http\Controllers\Tenant;
use Illuminate\Routing\Controller as BaseController;
use App\Domain\BoatShowEvent\Models\BoatShowEvent as RecordModel;
use App\Domain\BoatShowEvent\Actions\CreateBoatShowEvent as CreateAction;
use App\Domain\BoatShowEvent\Actions\UpdateBoatShowEvent as UpdateAction;
use App\Domain\BoatShowEvent\Actions\DeleteBoatShowEvent as DeleteAction;
use Illuminate\Http\Request;

class BoatShowEventController extends BaseController
{
    protected $recordType = 'BoatShowEvent';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'boatshowevents',
            'BoatShowEvent',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}