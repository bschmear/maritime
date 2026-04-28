<?php

declare(strict_types=1);

namespace App\Domain\WarrantyClaim\Support;

use App\Domain\Invoice\Models\Invoice;
use Illuminate\Validation\ValidationException;

/**
 * When an invoice has manufacturer-warranty line items, it may not be marked paid or void
 * until every linked warranty claim is terminal (paid or voided) and at least one claim exists.
 */
final class AssertInvoiceManufacturerWarrantyClaimsAllowClose
{
    public function __construct(
        private InvoiceManufacturerWarrantyCloseEligibility $eligibility,
    ) {}

    /**
     * @param  string  $errorKey  Request field name for validation errors (e.g. status on invoice, amount on payment).
     */
    public function __invoke(Invoice $invoice, string $errorKey = 'status'): void
    {
        $reason = $this->eligibility->reasonIfBlocked($invoice);
        if ($reason !== null) {
            throw ValidationException::withMessages([
                $errorKey => [$reason],
            ]);
        }
    }
}
