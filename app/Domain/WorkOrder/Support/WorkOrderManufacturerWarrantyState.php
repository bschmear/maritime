<?php

declare(strict_types=1);

namespace App\Domain\WorkOrder\Support;

use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Enums\ServiceTicketServiceItem\WarrantyCoverageType;
use App\Enums\WarrantyClaim\Status;

/**
 * Shared rules for manufacturer warranty lines and claim pipeline (eligibility + persisted flags).
 */
final class WorkOrderManufacturerWarrantyState
{
    /**
     * @param  list<array<string, mixed>>|null  $incomingServiceItems
     */
    public static function hasManufacturerWarrantyServiceItems(WorkOrder $workOrder, ?array $incomingServiceItems): bool
    {
        if ($incomingServiceItems !== null) {
            foreach ($incomingServiceItems as $item) {
                if (empty($item['display_name'])) {
                    continue;
                }
                if (! empty($item['warranty']) && ($item['warranty_type'] ?? null) === WarrantyCoverageType::Manufacturer->value) {
                    return true;
                }
            }

            return false;
        }

        return $workOrder->serviceItems()
            ->where('warranty', true)
            ->where('warranty_type', WarrantyCoverageType::Manufacturer)
            ->exists();
    }

    /**
     * True when every claim on the WO is terminal AND at least one claim exists.
     */
    public static function manufacturerWarrantyClaimPipelineComplete(int $workOrderId): bool
    {
        $query = WarrantyClaim::query()->where('work_order_id', $workOrderId);

        if (! $query->clone()->exists()) {
            return false;
        }

        return ! $query->clone()
            ->whereNotIn('status', [Status::Paid->value, Status::Voided->value])
            ->exists();
    }

    /**
     * Human-readable block reason for closing the work order, or null if not blocked by claims.
     *
     * @param  list<array<string, mixed>>|null  $incomingServiceItems
     */
    public static function claimPipelineBlockingReason(WorkOrder $workOrder, ?array $incomingServiceItems): ?string
    {
        if (! self::hasManufacturerWarrantyServiceItems($workOrder, $incomingServiceItems)) {
            return null;
        }

        $workOrderId = (int) $workOrder->getKey();
        $query = WarrantyClaim::query()->where('work_order_id', $workOrderId);

        if (! $query->clone()->exists()) {
            return 'This work order has manufacturer warranty line items. Create and complete a warranty claim (paid or voided) before closing this work order.';
        }

        if ($query->clone()
            ->whereNotIn('status', [Status::Paid->value, Status::Voided->value])
            ->exists()) {
            return 'All warranty claims for this work order must be paid or voided before it can be closed.';
        }

        return null;
    }

    /**
     * @return array{has_warranty: bool, warranty_closed: bool}
     */
    public static function computeWarrantyFlags(WorkOrder $workOrder, ?array $incomingServiceItems = null): array
    {
        $hasWarranty = self::hasManufacturerWarrantyServiceItems($workOrder, $incomingServiceItems);

        if (! $hasWarranty) {
            return [
                'has_warranty' => false,
                'warranty_closed' => true,
            ];
        }

        return [
            'has_warranty' => true,
            'warranty_closed' => self::manufacturerWarrantyClaimPipelineComplete((int) $workOrder->getKey()),
        ];
    }
}
