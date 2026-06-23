<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\AssetUnit\Support\InvoiceImport\InvoiceLineItemReconciler;
use App\Domain\AssetUnit\Support\InvoiceImport\InvoicePdfTextExtractor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceLineItemReconcilerTest extends TestCase
{
    #[Test]
    public function it_parses_fifteen_hins_from_ab_example_invoice(): void
    {
        $path = base_path('docs/Example_Data/AB-invoice.pdf');
        if (! is_readable($path)) {
            $this->markTestSkipped('AB example invoice PDF not available.');
        }

        $text = (new InvoicePdfTextExtractor)->extractFromPath($path);
        $identifiers = (new InvoiceLineItemReconciler)->parseIdentifiersFromPdf($text);

        $this->assertCount(15, $identifiers);
        $this->assertSame('XMO50167H324', $identifiers[0]['value']);
        $this->assertSame('hin', $identifiers[0]['type']);
        $this->assertSame('XMO51216J324', $identifiers[14]['value']);
    }

    #[Test]
    public function it_expands_collapsed_ai_rows_to_match_invoice_quantities_for_ab_invoice(): void
    {
        $path = base_path('docs/Example_Data/AB-invoice.pdf');
        if (! is_readable($path)) {
            $this->markTestSkipped('AB example invoice PDF not available.');
        }

        $text = (new InvoicePdfTextExtractor)->extractFromPath($path);

        $collapsedLineItems = [
            [
                'row_index' => 0,
                'source_line_index' => 0,
                'item_code' => '24 9.5AL GY',
                'description' => 'LAMMINA, Bow Locker, No Powder',
                'extracted_model' => 'LAMMINA 9.5 AL',
                'extracted_variant' => 'GY',
                'unit_price' => 4260.96,
                'hin' => 'XMO50167H324',
                'serial_number' => null,
                'asset_id' => 42,
                'asset_variant_id' => 7,
                'mapping_confidence' => 0.92,
            ],
            [
                'row_index' => 1,
                'source_line_index' => 1,
                'item_code' => '24 10AL GY',
                'description' => 'LAMMINA, Bow Locker, No Powder',
                'extracted_model' => 'LAMMINA 10 AL',
                'extracted_variant' => 'GY',
                'unit_price' => 4586.56,
                'hin' => 'XMO51217H324',
                'serial_number' => null,
                'asset_id' => 43,
                'asset_variant_id' => 8,
                'mapping_confidence' => 0.92,
            ],
        ];

        $invoiceLines = [
            [
                'source_line_index' => 0,
                'item_code' => '24 9.5AL GY',
                'description' => 'LAMMINA, Bow Locker, No Powder',
                'quantity' => 5,
                'unit_price' => 4260.96,
                'extension' => 21304.80,
            ],
            [
                'source_line_index' => 1,
                'item_code' => '24 10AL GY',
                'description' => 'LAMMINA, Bow Locker, No Powder',
                'quantity' => 10,
                'unit_price' => 4586.56,
                'extension' => 45865.60,
            ],
        ];

        $reconciled = (new InvoiceLineItemReconciler)->reconcile($collapsedLineItems, $invoiceLines, $text);

        $this->assertCount(15, $reconciled);
        $this->assertSame('XMO50167H324', $reconciled[0]['hin']);
        $this->assertSame('XMO50200H324', $reconciled[4]['hin']);
        $this->assertSame('LAMMINA 9.5 AL', $reconciled[4]['extracted_model']);
        $this->assertSame('XMO51217H324', $reconciled[5]['hin']);
        $this->assertSame('LAMMINA 10 AL', $reconciled[5]['extracted_model']);
        $this->assertSame('XMO51216J324', $reconciled[14]['hin']);
        $this->assertSame(42, $reconciled[4]['asset_id']);
        $this->assertSame(43, $reconciled[9]['asset_id']);
        $this->assertSame(43, $reconciled[14]['asset_id']);
    }

    #[Test]
    public function it_limits_rows_to_invoice_quantity_when_ai_over_expands(): void
    {
        $invoiceLines = [
            [
                'source_line_index' => 0,
                'item_code' => '25-1-HB270AX-PG',
                'description' => '4 PERS 8\'10" ALUMINUM RIB HB270AX',
                'quantity' => 1,
                'unit_price' => 3940.00,
                'extension' => 3940.00,
            ],
        ];

        $overExpanded = [
            [
                'row_index' => 0,
                'source_line_index' => 0,
                'item_code' => '25-1-HB270AX-PG',
                'description' => '4 PERS 8\'10" ALUMINUM RIB HB270AX',
                'extracted_model' => 'HB270AX',
                'extracted_variant' => 'PG',
                'unit_price' => 3940.00,
                'hin' => null,
                'serial_number' => '00046J425',
                'asset_id' => 10,
                'asset_variant_id' => 20,
                'mapping_confidence' => 0.9,
            ],
            [
                'row_index' => 1,
                'source_line_index' => 0,
                'item_code' => '25-1-HB270AX-PG',
                'description' => '4 PERS 8\'10" ALUMINUM RIB HB270AX',
                'extracted_model' => 'HB270AX',
                'extracted_variant' => 'PG',
                'unit_price' => 3940.00,
                'hin' => null,
                'serial_number' => '00046J425',
                'asset_id' => 10,
                'asset_variant_id' => 20,
                'mapping_confidence' => 0.9,
            ],
        ];

        $text = 'serial#00046J425';
        $reconciled = (new InvoiceLineItemReconciler)->reconcile($overExpanded, $invoiceLines, $text);

        $this->assertCount(1, $reconciled);
        $this->assertSame('00046J425', $reconciled[0]['serial_number']);
    }
}
