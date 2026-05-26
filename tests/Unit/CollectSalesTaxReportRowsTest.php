<?php

namespace Tests\Unit;

use App\Domain\Reports\Support\CollectSalesTaxReportRows;
use PHPUnit\Framework\TestCase;

class CollectSalesTaxReportRowsTest extends TestCase
{
    public function test_allocate_payment_tax_proportional(): void
    {
        $this->assertSame(2585.0, CollectSalesTaxReportRows::allocatePaymentTax(54285.0, 54285.0, 2585.0));
        $this->assertSame(50.0, CollectSalesTaxReportRows::allocatePaymentTax(1000.0, 10000.0, 500.0));
        $this->assertSame(0.0, CollectSalesTaxReportRows::allocatePaymentTax(100, 0, 50));
        $this->assertSame(0.0, CollectSalesTaxReportRows::allocatePaymentTax(0, 100, 50));
    }

    public function test_group_for_liability_buckets_jurisdiction_and_rate(): void
    {
        $rows = [
            $this->row(jurisdiction: 'CA', tax_rate: 5.0, taxable: 1000, tax: 50, collected: 10),
            $this->row(jurisdiction: 'CA', tax_rate: 5.0, taxable: 2000, tax: 100, collected: 20),
            $this->row(jurisdiction: 'FL', tax_rate: 6.0, taxable: 500, tax: 30, collected: 0),
        ];
        $g = CollectSalesTaxReportRows::groupForLiability($rows);
        $this->assertCount(2, $g);
        $ca = collect($g)->firstWhere('jurisdiction', 'CA');
        $this->assertNotNull($ca);
        $this->assertSame(5.0, $ca['tax_rate']);
        $this->assertSame(3000.0, $ca['taxable_amount']);
        $this->assertSame(150.0, $ca['tax_amount']);
        $this->assertSame(30.0, $ca['tax_collected']);
        $this->assertSame(2, $ca['row_count']);
    }

    public function test_group_for_payable_buckets_source_and_payment_status(): void
    {
        $rows = [
            $this->row(source_type: 'invoice', payment_status: 'paid', tax: 10),
            $this->row(source_type: 'invoice', payment_status: 'open', tax: 5),
            $this->row(source_type: 'transaction', payment_status: 'uninvoiced', tax: 3),
        ];
        $g = CollectSalesTaxReportRows::groupForPayable($rows);
        $this->assertCount(3, $g);
        $byKey = collect($g)->keyBy(fn ($x) => $x['source_type'].'|'.$x['payment_status']);
        $this->assertSame(10.0, $byKey['invoice|paid']['tax_amount']);
        $this->assertSame(5.0, $byKey['invoice|open']['tax_amount']);
        $this->assertSame(3.0, $byKey['transaction|uninvoiced']['tax_amount']);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        string $jurisdiction = 'CA',
        float $tax_rate = 5,
        float $taxable = 0,
        float $tax = 0,
        float $collected = 0,
        string $source_type = 'invoice',
        string $payment_status = 'open',
    ): array {
        return [
            'source_type' => $source_type,
            'source_id' => 1,
            'source_label' => 'X',
            'customer_name' => 'C',
            'jurisdiction' => $jurisdiction,
            'tax_rate' => $tax_rate,
            'taxable_amount' => $taxable,
            'tax_amount' => $tax,
            'tax_collected' => $collected,
            'document_date' => '2026-01-01',
            'invoice_status' => null,
            'payment_status' => $payment_status,
        ];
    }
}
