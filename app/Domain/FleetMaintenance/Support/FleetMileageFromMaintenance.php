<?php

declare(strict_types=1);

namespace App\Domain\FleetMaintenance\Support;

use App\Domain\Fleet\Models\Fleet;
use App\Domain\FleetMaintenance\Models\FleetMaintenance;

/**
 * When a maintenance log records odometer mileage for a truck, mirror it onto the fleet unit.
 */
final class FleetMileageFromMaintenance
{
    public static function syncFromLog(FleetMaintenance $log): void
    {
        if ($log->mileage === null) {
            return;
        }

        $fleet = Fleet::query()->whereKey($log->fleet_id)->lockForUpdate()->first();
        if ($fleet === null || ! $fleet->isTruck()) {
            return;
        }

        $fleet->mileage = (int) $log->mileage;
        $fleet->save();
    }
}
