<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Portal\Models\Portal as RecordModel;
use App\Domain\Portal\Actions\CreatePortal as CreateAction;
use App\Domain\Portal\Actions\UpdatePortal as UpdateAction;
use App\Domain\Portal\Actions\DeletePortal as DeleteAction;
use Illuminate\Http\Request;

class PortalController extends RecordController
{
    protected $recordType = 'Portal';
    protected $table = null;

    public function index(Request $request)
    {
        // Get current user first
        $currentUser = Auth::user();


    }
}
