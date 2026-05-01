<?php

declare(strict_types=1);

namespace App\Domain\WarrantyClaim\Support;

use App\Domain\WorkOrder\Models\WorkOrder;
use Illuminate\Validation\ValidationException;

final class AssertWorkOrderManufacturerWarrantyClaimsAllowClose
{
    public function __construct(
        private WorkOrderManufacturerWarrantyCloseEligibility $eligibility,
    ) {}

    /**
     * @param  list<array<string, mixed>>|null  $incomingServiceItems
     */
    public function __invoke(WorkOrder $workOrder, ?array $incomingServiceItems, string $errorKey = 'status'): void
    {
        $reason = $this->eligibility->reasonIfBlocked($workOrder, $incomingServiceItems);
        if ($reason !== null) {
            throw ValidationException::withMessages([
                $errorKey => [$reason],
            ]);
        }
    }
}
