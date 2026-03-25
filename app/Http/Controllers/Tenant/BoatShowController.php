<?php
namespace App\Http\Controllers\Tenant;
use Illuminate\Routing\Controller as BaseController;
use App\Domain\BoatShow\Models\BoatShow as RecordModel;
use App\Domain\BoatShow\Actions\CreateBoatShow as CreateAction;
use App\Domain\BoatShow\Actions\UpdateBoatShow as UpdateAction;
use App\Domain\BoatShow\Actions\DeleteBoatShow as DeleteAction;
use Illuminate\Http\Request;

class BoatShowController extends BaseController
{
    protected $recordType = 'BoatShow';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'boatshows',
            'BoatShow',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}