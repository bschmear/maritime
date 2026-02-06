<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\InventoryItem\Models\InventoryItem as RecordModel;
use App\Domain\InventoryItem\Actions\CreateInventoryItem as CreateAction;
use App\Domain\InventoryItem\Actions\UpdateInventoryItem as UpdateAction;
use App\Domain\InventoryItem\Actions\DeleteInventoryItem as DeleteAction;
use Illuminate\Http\Request;

class InventoryItemController extends RecordController
{
    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'inventoryitems',     // recordType (route parameter name)
            'InventoryItem',      // recordTitle (display name)
            new RecordModel(),    // Model instance
            new CreateAction(),   // Create action
            new UpdateAction(),   // Update action
            new DeleteAction(),   // Delete action
            'InventoryItem'       // domainName (for schema lookup)
        );
    }
}
