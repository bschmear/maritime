<?php

declare(strict_types=1);

namespace App\Domain\WarrantyClaim\Support;

use App\Domain\WorkOrder\Models\WorkOrder;
use App\Domain\WorkOrder\Support\WorkOrderManufacturerWarrantyState;

/**
 * Blocks setting a work order to Closed while manufacturer-warranty service lines exist
 * and linked warranty claims are not all terminal (paid or voided).
 */
final class WorkOrderManufacturerWarrantyCloseEligibility
{
    /**
     * @param  list<array<string, mixed>>|null  $incomingServiceItems  Replacement line items from the request, or null to use persisted items.
     */
    public function reasonIfBlocked(WorkOrder $workOrder, ?array $incomingServiceItems): ?string
    {
        return WorkOrderManufacturerWarrantyState::claimPipelineBlockingReason($workOrder, $incomingServiceItems);
    }

    /**
     * @param  list<array<string, mixed>>|null  $incomingServiceItems
     */
    public function isAllowed(WorkOrder $workOrder, ?array $incomingServiceItems): bool
    {
        return $this->reasonIfBlocked($workOrder, $incomingServiceItems) === null;
    }
}
