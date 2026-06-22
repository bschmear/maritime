<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support\InvoiceImport;

use App\Domain\Bill\Actions\CreateBill;
use App\Domain\BoatMake\Models\BoatMake;

class InvoiceBillFactory
{
    public function __construct(
        private readonly ?CreateBill $createBill = null,
    ) {}

    /**
     * @param  array{
     *   invoice_number: ?string,
     *   invoice_date: ?string,
     *   invoice_lines: list<array<string, mixed>>
     * }  $extraction
     * @return array{success: bool, bill?: Bill, message?: string, quickbooks_sync?: array<string, mixed>|null}
     */
    public function create(BoatMake $brand, array $extraction, bool $syncQuickBooks = false): array
    {
        if (! $brand->vendor_id) {
            return ['success' => false, 'message' => 'Brand has no default vendor.'];
        }

        $items = [];
        foreach ($extraction['invoice_lines'] ?? [] as $index => $line) {
            $description = trim(implode(' — ', array_filter([
                $line['item_code'] ?? null,
                $line['description'] ?? null,
            ])));

            $items[] = [
                'description' => $description !== '' ? $description : 'Invoice line',
                'quantity' => (float) ($line['quantity'] ?? 1),
                'unit_price' => (float) ($line['unit_price'] ?? 0),
                'amount' => (float) ($line['extension'] ?? 0),
                'position' => $index,
            ];
        }

        $total = array_reduce($items, fn (float $sum, array $item) => $sum + (float) $item['amount'], 0.0);

        $payload = [
            'vendor_id' => $brand->vendor_id,
            'doc_number' => $extraction['invoice_number'] ?? null,
            'txn_date' => $extraction['invoice_date'] ?? now()->toDateString(),
            'due_date' => $extraction['invoice_date'] ?? null,
            'total_amt' => $total,
            'balance' => $total,
            'private_note' => 'Created from invoice import.',
            'items' => $items,
            'for_import' => ! $syncQuickBooks,
        ];

        $result = $this->createBill()($payload);
        if (! ($result['success'] ?? false)) {
            return [
                'success' => false,
                'message' => $result['message'] ?? 'Bill creation failed.',
            ];
        }

        return [
            'success' => true,
            'bill' => $result['record'] ?? null,
            'quickbooks_sync' => $result['quickbooks_sync'] ?? null,
        ];
    }

    private function createBill(): CreateBill
    {
        return $this->createBill ?? app(CreateBill::class);
    }
}
