<?php

declare(strict_types=1);

namespace App\Domain\WarrantyClaim\Actions;

use App\Domain\Attachment\Models\AttachmentLink;
use App\Domain\Attachment\Services\InventoryImageAttachmentService;
use App\Domain\InventoryImage\Actions\CreateInventoryImage;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Domain\WorkOrder\Models\WorkOrder;
use Illuminate\Http\UploadedFile;

/**
 * After a warranty claim is created: link selected existing images (from the work order or its
 * service ticket) and attach newly uploaded images — no S3 duplication.
 */
final class AttachWarrantyClaimImagesAfterCreate
{
    /**
     * @param  list<int|string>  $reuseInventoryImageIds
     * @param  array<int, UploadedFile>|null  $uploadedImages
     */
    public function __invoke(
        WarrantyClaim $claim,
        array $reuseInventoryImageIds,
        ?array $uploadedImages,
    ): void {
        $attach = app(InventoryImageAttachmentService::class);
        $claimFqcn = WarrantyClaim::class;
        $claimId = (int) $claim->id;

        $sortOrder = (int) AttachmentLink::query()
            ->where('attachable_type', $claimFqcn)
            ->where('attachable_id', $claimId)
            ->max('sort_order');

        $workOrder = $claim->work_order_id ? WorkOrder::query()->find((int) $claim->work_order_id) : null;

        foreach ($reuseInventoryImageIds as $rawId) {
            $imageId = (int) $rawId;
            if ($imageId <= 0 || ! $workOrder) {
                continue;
            }
            if (! $attach->imageIsUsableOnWarrantyClaimFromWorkOrder($imageId, $workOrder)) {
                continue;
            }
            $sortOrder++;
            $attach->linkTo($claimFqcn, $claimId, $imageId, $sortOrder, false);
        }

        $create = new CreateInventoryImage;
        $files = $uploadedImages ?? [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $sortOrder++;
            ($create)([
                'imageable_type' => $claimFqcn,
                'imageable_id' => $claimId,
                'display_name' => $file->getClientOriginalName(),
                'description' => null,
                'file' => $file,
                'sort_order' => $sortOrder,
                'role' => null,
                'is_primary' => false,
                'also_attach_to_service_ticket' => false,
            ]);
        }

        $attach->normalizePrimaryLinksForAttachable($claimFqcn, $claimId);
    }
}
