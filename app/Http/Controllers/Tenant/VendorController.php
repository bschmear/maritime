<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Vendor\Models\Vendor as RecordModel;
use App\Domain\Vendor\Actions\CreateVendor as CreateAction;
use App\Domain\Vendor\Actions\UpdateVendor as UpdateAction;
use App\Domain\Vendor\Actions\DeleteVendor as DeleteAction;
use Illuminate\Http\Request;

class VendorController extends RecordController
{
    protected $recordType = 'Vendor';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'vendors',
            'Vendor',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }
}