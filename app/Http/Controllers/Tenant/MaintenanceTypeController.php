<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\MaintenanceType\Actions\CreateMaintenanceType as CreateAction;
use App\Domain\MaintenanceType\Actions\DeleteMaintenanceType as DeleteAction;
use App\Domain\MaintenanceType\Actions\UpdateMaintenanceType as UpdateAction;
use App\Domain\MaintenanceType\Models\MaintenanceType as RecordModel;
use Illuminate\Http\Request;

class MaintenanceTypeController extends RecordController
{
    protected $recordType = 'MaintenanceType';

    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'maintenance-types',
            'Maintenance type',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            $this->recordType
        );
    }
}
