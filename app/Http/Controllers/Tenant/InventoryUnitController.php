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
    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'inventoryunits',        // recordType (route parameter name)
            'InventoryUnit',         // recordTitle (display name)
            new RecordModel(),       // Model instance
            new CreateAction(),      // Create action
            new UpdateAction(),      // Update action
            new DeleteAction(),      // Delete action
            'InventoryUnit'          // domainName (for schema lookup)
        );
    }
}