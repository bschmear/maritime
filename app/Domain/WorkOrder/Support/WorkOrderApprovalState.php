<?php

declare(strict_types=1);

namespace App\Domain\WorkOrder\Support;

use App\Domain\WorkOrder\Models\WorkOrder;

final class WorkOrderApprovalState
{
    public const NOT_REQUIRED = 'not_required';

    public const IN_PROGRESS = 'in_progress';

    public const PENDING_MANAGER = 'pending_manager';

    public const APPROVED = 'approved';

    public static function resolve(WorkOrder $workOrder): string
    {
        if (! $workOrder->requires_manager_approval) {
            return self::NOT_REQUIRED;
        }

        if ($workOrder->manager_signed_off_at) {
            return self::APPROVED;
        }

        if ($workOrder->technician_submitted_at) {
            return self::PENDING_MANAGER;
        }

        return self::IN_PROGRESS;
    }

    public static function canClose(WorkOrder $workOrder): bool
    {
        if (! $workOrder->requires_manager_approval) {
            return true;
        }

        return $workOrder->manager_signed_off_at !== null;
    }
}
