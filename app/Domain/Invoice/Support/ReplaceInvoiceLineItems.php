<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Support;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\InvoiceItem\Models\InvoiceItem;

final class ReplaceInvoiceLineItems
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public static function apply(Invoice $invoice, array $items): Invoice
    {
        InvoiceItem::query()->where('invoice_id', $invoice->id)->delete();

        foreach ($items as $position => $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'transaction_line_item_id' => $item['transaction_line_item_id'] ?? $item['transaction_item_id'] ?? null,
                'service_item_id' => ! empty($item['service_item_id']) ? (int) $item['service_item_id'] : null,
                'itemable_type' => $item['itemable_type'] ?? null,
                'itemable_id' => isset($item['itemable_id']) ? (int) $item['itemable_id'] : null,
                'asset_variant_id' => ! empty($item['asset_variant_id']) ? (int) $item['asset_variant_id'] : null,
                'asset_unit_id' => ! empty($item['asset_unit_id']) ? (int) $item['asset_unit_id'] : null,
                'name' => $item['name'] ?? '',
                'description' => $item['description'] ?? null,
                'quantity' => (float) ($item['quantity'] ?? 1),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'cost' => (float) ($item['cost'] ?? 0),
                'discount' => (float) ($item['discount'] ?? 0),
                'is_warranty' => (bool) ($item['is_warranty'] ?? false),
                'warranty_type' => $item['warranty_type'] ?? null,
                'billable_to' => $item['billable_to'] ?? 'customer',
                'taxable' => (bool) ($item['taxable'] ?? false),
                'tax_rate' => (float) ($item['tax_rate'] ?? 0),
                'position' => $item['position'] ?? $position,
            ]);
        }

        return $invoice->fresh(['items']);
    }
}
