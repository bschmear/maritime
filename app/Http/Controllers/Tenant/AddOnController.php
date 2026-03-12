<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\AddOn\Models\AddOn as RecordModel;
use App\Domain\AddOn\Actions\CreateAddOn as CreateAction;
use App\Domain\AddOn\Actions\UpdateAddOn as UpdateAction;
use App\Domain\AddOn\Actions\DeleteAddOn as DeleteAction;
use Illuminate\Http\Request;

class AddOnController extends RecordController
{
    protected $recordType = 'AddOn';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'addons',
            'AddOn',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}