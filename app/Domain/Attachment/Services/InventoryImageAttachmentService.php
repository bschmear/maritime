<?php

declare(strict_types=1);

namespace App\Domain\Attachment\Services;

use App\Domain\Attachment\Models\AttachmentLink;
use App\Domain\InventoryImage\Models\InventoryImage;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\WorkOrder\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class InventoryImageAttachmentService
{
    /**
     * Ensure a link row exists mirroring the image's morph owner (for ST / WO / WC).
     */
    public function ensureLinkForMorphOwner(InventoryImage $image): void
    {
        if (! AttachmentLink::usesLinksForMorphClass($image->imageable_type)) {
            return;
        }

        AttachmentLink::query()->firstOrCreate(
            [
                'inventory_image_id' => $image->id,
                'attachable_type' => $image->imageable_type,
                'attachable_id' => (int) $image->imageable_id,
            ],
            [
                'sort_order' => (int) $image->sort_order,
                'is_primary' => (bool) $image->is_primary,
            ]
        );
    }

    /**
     * Link an existing image to an attachable (no file duplication).
     *
     * @param  class-string<Model>  $attachableType
     */
    public function linkTo(
        string $attachableType,
        int $attachableId,
        int $inventoryImageId,
        int $sortOrder = 0,
        bool $isPrimary = false,
        bool $visibleToCustomer = false,
    ): AttachmentLink {
        if (! in_array($attachableType, AttachmentLink::linkableMorphClasses(), true)) {
            throw new \InvalidArgumentException('Unsupported attachable type for attachment links.');
        }

        return AttachmentLink::query()->firstOrCreate(
            [
                'inventory_image_id' => $inventoryImageId,
                'attachable_type' => $attachableType,
                'attachable_id' => $attachableId,
            ],
            [
                'sort_order' => $sortOrder,
                'is_primary' => $isPrimary,
                'visible_to_customer' => $visibleToCustomer,
            ]
        );
    }

    /**
     * Remove the link for this attachable. Deletes the inventory image (and S3 file) when no links remain.
     *
     * @param  class-string<Model>  $attachableType
     */
    public function unlinkFromAttachable(int $inventoryImageId, string $attachableType, int $attachableId): void
    {
        AttachmentLink::query()
            ->where('inventory_image_id', $inventoryImageId)
            ->where('attachable_type', $attachableType)
            ->where('attachable_id', $attachableId)
            ->delete();

        $remaining = AttachmentLink::query()->where('inventory_image_id', $inventoryImageId)->count();
        if ($remaining === 0) {
            InventoryImage::query()->whereKey($inventoryImageId)->delete();
        }
    }

    /**
     * When a new image is uploaded on a Work Order, optionally also link it to the linked Service Ticket.
     */
    public function maybeAlsoLinkWorkOrderUploadToServiceTicket(InventoryImage $image, bool $alsoAttachToServiceTicket): void
    {
        if (! $alsoAttachToServiceTicket) {
            return;
        }
        if ($image->imageable_type !== WorkOrder::class) {
            return;
        }

        $wo = WorkOrder::query()->find((int) $image->imageable_id);
        if (! $wo || ! $wo->service_ticket_id) {
            return;
        }

        $this->linkTo(
            ServiceTicket::class,
            (int) $wo->service_ticket_id,
            $image->id,
            (int) $image->sort_order,
            false
        );
    }

    /**
     * Clear primary on all links for this attachable, then set one link as primary.
     *
     * @param  class-string<Model>  $attachableType
     */
    public function setPrimaryForAttachable(string $attachableType, int $attachableId, int $inventoryImageId): void
    {
        DB::transaction(function () use ($attachableType, $attachableId, $inventoryImageId) {
            AttachmentLink::query()
                ->where('attachable_type', $attachableType)
                ->where('attachable_id', $attachableId)
                ->update(['is_primary' => false]);

            AttachmentLink::query()
                ->where('attachable_type', $attachableType)
                ->where('attachable_id', $attachableId)
                ->where('inventory_image_id', $inventoryImageId)
                ->update(['is_primary' => true]);
        });
    }

    /**
     * @param  class-string<Model>  $attachableType
     */
    public function updateSortOrderForAttachable(string $attachableType, int $attachableId, int $inventoryImageId, int $sortOrder): void
    {
        AttachmentLink::query()
            ->where('attachable_type', $attachableType)
            ->where('attachable_id', $attachableId)
            ->where('inventory_image_id', $inventoryImageId)
            ->update(['sort_order' => $sortOrder]);
    }

    /**
     * @param  class-string<Model>  $attachableType
     */
    public function updateVisibleToCustomerForAttachable(
        string $attachableType,
        int $attachableId,
        int $inventoryImageId,
        bool $visibleToCustomer,
    ): void {
        AttachmentLink::query()
            ->where('attachable_type', $attachableType)
            ->where('attachable_id', $attachableId)
            ->where('inventory_image_id', $inventoryImageId)
            ->update(['visible_to_customer' => $visibleToCustomer]);
    }

    /**
     * True when the image is linked (or morph-owned) on this work order or its service ticket.
     */
    public function imageIsUsableOnWarrantyClaimFromWorkOrder(int $inventoryImageId, WorkOrder $wo): bool
    {
        if (AttachmentLink::query()
            ->where('inventory_image_id', $inventoryImageId)
            ->where('attachable_type', WorkOrder::class)
            ->where('attachable_id', $wo->id)
            ->exists()) {
            return true;
        }

        $stId = $wo->service_ticket_id ? (int) $wo->service_ticket_id : null;
        if ($stId && AttachmentLink::query()
            ->where('inventory_image_id', $inventoryImageId)
            ->where('attachable_type', ServiceTicket::class)
            ->where('attachable_id', $stId)
            ->exists()) {
            return true;
        }

        if ($stId) {
            $img = InventoryImage::query()->find($inventoryImageId);
            if (
                $img
                && $img->imageable_type === ServiceTicket::class
                && (int) $img->imageable_id === $stId
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the image is linked to the service ticket or is morph-owned by it.
     */
    public function imageBelongsToServiceTicket(int $inventoryImageId, int $serviceTicketId): bool
    {
        if (AttachmentLink::query()
            ->where('inventory_image_id', $inventoryImageId)
            ->where('attachable_type', ServiceTicket::class)
            ->where('attachable_id', $serviceTicketId)
            ->exists()) {
            return true;
        }

        $img = InventoryImage::query()->find($inventoryImageId);

        return $img
            && $img->imageable_type === ServiceTicket::class
            && (int) $img->imageable_id === $serviceTicketId;
    }

    /**
     * After creating links for an attachable, ensure exactly one pivot row is primary (if any links exist).
     *
     * @param  class-string<Model>  $attachableType
     */
    public function normalizePrimaryLinksForAttachable(string $attachableType, int $attachableId): void
    {
        $ids = AttachmentLink::query()
            ->where('attachable_type', $attachableType)
            ->where('attachable_id', $attachableId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        AttachmentLink::query()
            ->where('attachable_type', $attachableType)
            ->where('attachable_id', $attachableId)
            ->update(['is_primary' => false]);

        AttachmentLink::query()
            ->whereKey($ids->first())
            ->update(['is_primary' => true]);
    }
}
