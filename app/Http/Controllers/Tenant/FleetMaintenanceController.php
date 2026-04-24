<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\FleetMaintenance\Actions\CreateFleetMaintenance as CreateAction;
use App\Domain\FleetMaintenance\Actions\DeleteFleetMaintenance as DeleteAction;
use App\Domain\FleetMaintenance\Actions\UpdateFleetMaintenance as UpdateAction;
use App\Domain\FleetMaintenance\Models\FleetMaintenance as RecordModel;
use Illuminate\Http\Request;

class FleetMaintenanceController extends RecordController
{
    protected $recordType = 'FleetMaintenance';

    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'fleetmaintenances',
            'FleetMaintenance',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            $this->recordType // Domain name for schema lookup
        );
    }
}
