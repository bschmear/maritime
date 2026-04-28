<?php

declare(strict_types=1);

namespace App\Domain\WarrantyClaim\Support;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Enums\ServiceTicketServiceItem\WarrantyCoverageType;
use App\Enums\WarrantyClaim\Status;

/**
 * Determines whether an invoice may be marked paid or void given manufacturer warranty line items and linked claims.
 */
final class InvoiceManufacturerWarrantyCloseEligibility
{
    public function reasonIfBlocked(Invoice $invoice): ?string
    {
        if (! $this->invoiceHasManufacturerWarrantyLineItems($invoice)) {
            return null;
        }

        $query = $this->linkedClaimsQuery($invoice);

        if (! $query->clone()->exists()) {
            return 'This invoice has manufacturer warranty line items. Create and complete a warranty claim (paid or voided) before closing this invoice.';
        }

        $nonTerminal = $query->clone()
            ->whereNotIn('status', [Status::Paid->value, Status::Voided->value])
            ->exists();

        if ($nonTerminal) {
            return 'All linked warranty claims must be paid or voided before this invoice can be closed.';
        }

        return null;
    }

    public function isAllowed(Invoice $invoice): bool
    {
        return $this->reasonIfBlocked($invoice) === null;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Domain\WarrantyClaim\Models\WarrantyClaim>
     */
    private function linkedClaimsQuery(Invoice $invoice)
    {
        return WarrantyClaim::query()
            ->where(function ($q) use ($invoice) {
                $q->where('invoice_id', $invoice->id);
                if ($invoice->work_order_id) {
                    $q->orWhere(function ($q2) use ($invoice) {
                        $q2->whereNull('invoice_id')
                            ->where('work_order_id', $invoice->work_order_id);
                    });
                }
            });
    }

    private function invoiceHasManufacturerWarrantyLineItems(Invoice $invoice): bool
    {
        return $invoice->items()
            ->where('is_warranty', true)
            ->where('warranty_type', WarrantyCoverageType::Manufacturer->value)
            ->exists();
    }
}
