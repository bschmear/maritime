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
    protected $recordType = 'InventoryItem';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'inventoryitems',
            'InventoryItem',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}