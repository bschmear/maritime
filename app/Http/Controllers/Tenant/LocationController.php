<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Location\Models\Location as RecordModel;
use App\Domain\Location\Actions\CreateLocation as CreateAction;
use App\Domain\Location\Actions\UpdateLocation as UpdateAction;
use App\Domain\Location\Actions\DeleteLocation as DeleteAction;
use Illuminate\Http\Request;

class LocationController extends RecordController
{
    protected $recordType = 'Location';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'locations',
            'Location',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType
        );
    }
}