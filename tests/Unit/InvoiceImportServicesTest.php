<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\AssetUnit\Support\InvoiceImport\BoatMakeInvoiceProfileService;
use App\Domain\AssetUnit\Support\InvoiceImport\InvoiceBillFactory;
use App\Domain\AssetUnit\Support\InvoiceImport\InvoicePdfTextExtractor;
use App\Domain\AssetUnit\Support\InvoiceImport\InvoiceUnitImportService;
use App\Domain\BoatMake\Models\BoatMake;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceImportServicesTest extends TestCase
{
    #[Test]
    public function profile_service_returns_null_when_no_saved_instructions(): void
    {
        $brand = new BoatMake(['id' => 1]);
        $brand->setRelation('invoiceImportProfile', null);

        $service = new BoatMakeInvoiceProfileService;

        $this->assertNull($service->instructionsFor($brand));
    }

    #[Test]
    public function it_extracts_text_from_ab_example_invoice_pdf(): void
    {
        $path = base_path('docs/Example_Data/AB-invoice.pdf');
        if (! is_readable($path)) {
            $this->markTestSkipped('AB example invoice PDF not available.');
        }

        $extractor = new InvoicePdfTextExtractor;
        $text = $extractor->extractFromPath($path);

        $this->assertTrue($extractor->isTextSufficient($text));
        $this->assertStringContainsString('21375', $text);
        $this->assertStringContainsString('LAMMINA', $text);
        $this->assertStringContainsString('XMO50167H324', $text);
    }

    #[Test]
    public function invoice_unit_import_blocks_rows_without_asset(): void
    {
        $service = new InvoiceUnitImportService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('validateRow');
        $method->setAccessible(true);

        $error = $method->invoke($service, ['asset_id' => null], 0);

        $this->assertStringContainsString('select an asset', (string) $error);
    }

    #[Test]
    public function invoice_bill_factory_requires_brand_vendor(): void
    {
        $brand = new BoatMake(['id' => 1, 'vendor_id' => null]);
        $factory = new InvoiceBillFactory;

        $result = $factory->create($brand, [
            'invoice_number' => '21375',
            'invoice_date' => '2025-05-01',
            'invoice_lines' => [],
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('vendor', strtolower((string) $result['message']));
    }
}
