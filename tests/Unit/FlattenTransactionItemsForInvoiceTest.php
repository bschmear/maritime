<?php

namespace Tests\Unit;

use App\Domain\Invoice\Support\FlattenTransactionItemsForInvoice;
use PHPUnit\Framework\TestCase;

class FlattenTransactionItemsForInvoiceTest extends TestCase
{
    public function test_rollup_includes_flattened_boat_option_rows(): void
    {
        $items = [
            [
                'quantity' => 1,
                'unit_price' => 50000,
                'discount' => 0,
                'taxable' => true,
                'tax_rate' => 5,
            ],
            [
                'quantity' => 1,
                'unit_price' => 50,
                'discount' => 0,
                'taxable' => true,
                'tax_rate' => 5,
            ],
            [
                'quantity' => 1,
                'unit_price' => 150,
                'discount' => 0,
                'taxable' => true,
                'tax_rate' => 5,
            ],
            [
                'quantity' => 1,
                'unit_price' => 1500,
                'discount' => 0,
                'taxable' => true,
                'tax_rate' => 5,
            ],
        ];

        $totals = FlattenTransactionItemsForInvoice::rollupTotals($items);

        $this->assertSame(51700.0, $totals['subtotal']);
        $this->assertSame(2585.0, $totals['tax_total']);
        $this->assertSame(54285.0, $totals['total']);
    }
}
