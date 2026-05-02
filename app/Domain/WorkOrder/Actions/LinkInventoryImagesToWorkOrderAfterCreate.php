<?php

declare(strict_types=1);

namespace App\Domain\WorkOrder\Actions;

use App\Domain\Attachment\Models\AttachmentLink;
use App\Domain\Attachment\Services\InventoryImageAttachmentService;
use App\Domain\InventoryImage\Models\InventoryImage;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\WorkOrder\Models\WorkOrder;

final class LinkInventoryImagesToWorkOrderAfterCreate
{
    /**
     * @param  list<int|string>  $explicitImageIds
     */
    public function __invoke(WorkOrder $workOrder, array $explicitImageIds, bool $linkAll): void
    {
        $stId = $workOrder->service_ticket_id ? (int) $workOrder->service_ticket_id : 0;
        if ($stId <= 0) {
            return;
        }

        $service = app(InventoryImageAttachmentService::class);

        $ids = $linkAll
            ? $this->serviceTicketImageIds($stId)
            : array_values(array_unique(array_map(static fn ($v) => (int) $v, $explicitImageIds)));

        $max = (int) AttachmentLink::query()
            ->where('attachable_type', WorkOrder::class)
            ->where('attachable_id', $workOrder->id)
            ->max('sort_order');

        foreach ($ids as $inventoryImageId) {
            if ($inventoryImageId <= 0 || ! $service->imageBelongsToServiceTicket($inventoryImageId, $stId)) {
                continue;
            }
            $max++;
            $service->linkTo(WorkOrder::class, (int) $workOrder->id, $inventoryImageId, $max, false);
        }

        $service->normalizePrimaryLinksForAttachable(WorkOrder::class, (int) $workOrder->id);
    }

    /**
     * @return list<int>
     */
    private function serviceTicketImageIds(int $serviceTicketId): array
    {
        $fromLinks = AttachmentLink::query()
            ->where('attachable_type', ServiceTicket::class)
            ->where('attachable_id', $serviceTicketId)
            ->pluck('inventory_image_id');

        $fromMorph = InventoryImage::query()
            ->where('imageable_type', ServiceTicket::class)
            ->where('imageable_id', $serviceTicketId)
            ->pluck('id');

        return $fromLinks->merge($fromMorph)->unique()->values()->all();
    }
}
