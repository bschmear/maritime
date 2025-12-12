<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\InventoryUnit\Models\InventoryUnit as RecordModel;
use App\Domain\InventoryUnit\Actions\CreateInventoryUnit as CreateAction;
use App\Domain\InventoryUnit\Actions\UpdateInventoryUnit as UpdateAction;
use App\Domain\InventoryUnit\Actions\DeleteInventoryUnit as DeleteAction;
use Illuminate\Http\Request;

class InventoryUnitController extends RecordController
{
    protected $recordType = 'InventoryUnit';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'inventoryunits',
            'InventoryUnit',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}