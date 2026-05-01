<?php

declare(strict_types=1);

namespace App\Domain\WorkOrder\Support;

use App\Domain\WorkOrder\Models\WorkOrder;
use Illuminate\Support\Facades\Schema;

final class SyncWorkOrderWarrantyFlags
{
    public function __invoke(WorkOrder $workOrder): void
    {
        if (! Schema::hasColumn($workOrder->getTable(), 'has_warranty')) {
            return;
        }

        $workOrder->loadMissing('serviceItems');

        $flags = WorkOrderManufacturerWarrantyState::computeWarrantyFlags($workOrder, null);

        if (
            (bool) $workOrder->getAttribute('has_warranty') === $flags['has_warranty']
            && (bool) $workOrder->getAttribute('warranty_closed') === $flags['warranty_closed']
        ) {
            return;
        }

        $workOrder->forceFill([
            'has_warranty' => $flags['has_warranty'],
            'warranty_closed' => $flags['warranty_closed'],
        ]);
        $workOrder->saveQuietly();
    }
}
