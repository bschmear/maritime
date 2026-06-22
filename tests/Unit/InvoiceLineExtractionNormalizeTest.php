<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\AssetUnit\Support\InvoiceImport\InvoiceLineExtractionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceLineExtractionNormalizeTest extends TestCase
{
    #[Test]
    public function it_normalizes_extraction_payload_with_fifteen_expanded_units(): void
    {
        $service = new InvoiceLineExtractionService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('normalize');
        $method->setAccessible(true);

        $lineItems = [];
        $hins = [
            'XMO50167H324', 'XMO50168H324', 'XMO50182H324', 'XMO50199H324', 'XMO50200H324',
        ];
        foreach ($hins as $i => $hin) {
            $lineItems[] = [
                'source_line_index' => 1,
                'item_code' => '24 9.5AL GY',
                'description' => 'LAMMINA, Bow Locker, No Powder',
                'extracted_model' => 'LAMMINA 9.5 AL',
                'extracted_variant' => 'GY',
                'unit_price' => 4260.96,
                'hin' => $hin,
                'serial_number' => null,
                'asset_id' => 42,
                'asset_variant_id' => 7,
                'mapping_confidence' => 0.92,
            ];
        }

        $moreHins = [
            'XMO51217H324', 'XMO51207H324', 'XMO51209H324', 'XMO51219H324', 'XMO51211H324',
            'XMO51199H324', 'XMO51210H324', 'XMO51201H324', 'XMO51220H324', 'XMO51216J324',
        ];
        foreach ($moreHins as $hin) {
            $lineItems[] = [
                'source_line_index' => 2,
                'item_code' => '24 10AL GY',
                'description' => 'LAMMINA, Bow Locker, No Powder',
                'extracted_model' => 'LAMMINA 10 AL',
                'extracted_variant' => 'GY',
                'unit_price' => 4586.56,
                'hin' => $hin,
                'serial_number' => null,
                'asset_id' => 42,
                'asset_variant_id' => 7,
                'mapping_confidence' => 0.92,
            ];
        }

        $result = $method->invoke($service, [
            'invoice_number' => '21375',
            'invoice_date' => '2025-05-01',
            'line_items' => $lineItems,
            'invoice_lines' => [
                [
                    'source_line_index' => 1,
                    'item_code' => '24 9.5AL GY',
                    'description' => 'LAMMINA, Bow Locker, No Powder',
                    'quantity' => 5,
                    'unit_price' => 4260.96,
                    'extension' => 21304.80,
                ],
                [
                    'source_line_index' => 2,
                    'item_code' => '24 10AL GY',
                    'description' => 'LAMMINA, Bow Locker, No Powder',
                    'quantity' => 10,
                    'unit_price' => 4586.56,
                    'extension' => 45865.60,
                ],
            ],
        ]);

        $this->assertSame('21375', $result['invoice_number']);
        $this->assertCount(15, $result['line_items']);
        $this->assertSame(4260.96, $result['line_items'][0]['unit_price']);
        $this->assertSame('XMO50167H324', $result['line_items'][0]['hin']);
        $this->assertSame(42, $result['line_items'][0]['asset_id']);
        $this->assertSame(7, $result['line_items'][0]['asset_variant_id']);
        $this->assertSame(0.92, $result['line_items'][0]['mapping_confidence']);
        $this->assertCount(2, $result['invoice_lines']);
    }

    #[Test]
    public function it_drops_duplicate_hins_and_serial_numbers(): void
    {
        $service = new InvoiceLineExtractionService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('normalize');
        $method->setAccessible(true);

        $result = $method->invoke($service, [
            'invoice_number' => '100',
            'invoice_date' => '2025-05-01',
            'line_items' => [
                [
                    'source_line_index' => 0,
                    'item_code' => 'A',
                    'description' => 'Unit A',
                    'extracted_model' => 'Model',
                    'extracted_variant' => null,
                    'unit_price' => 1000,
                    'hin' => 'ABC123',
                    'serial_number' => null,
                    'asset_id' => 1,
                    'asset_variant_id' => null,
                    'mapping_confidence' => 0.9,
                ],
                [
                    'source_line_index' => 1,
                    'item_code' => 'A',
                    'description' => 'Unit A duplicate',
                    'extracted_model' => 'Model',
                    'extracted_variant' => null,
                    'unit_price' => 1000,
                    'hin' => 'ABC123',
                    'serial_number' => null,
                    'asset_id' => 1,
                    'asset_variant_id' => null,
                    'mapping_confidence' => 0.9,
                ],
                [
                    'source_line_index' => 2,
                    'item_code' => 'B',
                    'description' => 'Unit B',
                    'extracted_model' => 'Model',
                    'extracted_variant' => null,
                    'unit_price' => 2000,
                    'hin' => null,
                    'serial_number' => 'SN-9',
                    'asset_id' => 2,
                    'asset_variant_id' => null,
                    'mapping_confidence' => 0.8,
                ],
                [
                    'source_line_index' => 3,
                    'item_code' => 'B',
                    'description' => 'Unit B duplicate',
                    'extracted_model' => 'Model',
                    'extracted_variant' => null,
                    'unit_price' => 2000,
                    'hin' => null,
                    'serial_number' => 'SN-9',
                    'asset_id' => 2,
                    'asset_variant_id' => null,
                    'mapping_confidence' => 0.8,
                ],
            ],
            'invoice_lines' => [],
        ]);

        $this->assertCount(2, $result['line_items']);
        $this->assertSame('ABC123', $result['line_items'][0]['hin']);
        $this->assertSame('SN-9', $result['line_items'][1]['serial_number']);
        $this->assertSame(0, $result['line_items'][0]['row_index']);
        $this->assertSame(1, $result['line_items'][1]['row_index']);
    }
}
