<?php

namespace Tests\Unit;

use App\Domain\Integration\Models\Integration;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\InvoiceItem\Models\InvoiceItem;
use App\Services\Payments\QuickBooksOAuthService;
use App\Services\Payments\QuickBooksTaxService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuickBooksTaxServiceTest extends TestCase
{
    #[Test]
    public function automated_sales_tax_marks_taxable_lines_with_tax_code(): void
    {
        $service = new class(new QuickBooksOAuthService) extends QuickBooksTaxService
        {
            public function usesAutomatedSalesTax(Integration $integration): bool
            {
                return true;
            }
        };

        $invoice = new Invoice([
            'tax_total' => 8.25,
            'subtotal' => 100,
        ]);
        $invoice->setRelation('items', new Collection([
            new InvoiceItem(['taxable' => true, 'tax_rate' => 8.25, 'quantity' => 1, 'unit_price' => 100, 'discount' => 0]),
        ]));

        $lines = [[
            'Amount' => 100,
            'DetailType' => 'SalesItemLineDetail',
            'SalesItemLineDetail' => ['ItemRef' => ['value' => '1']],
        ]];

        $result = $service->enrichInvoicePayload(
            new Integration(['id' => 1, 'external_id' => 'r1']),
            $invoice,
            [],
            $lines,
        );

        $this->assertSame('TAX', $result['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);
        $this->assertSame('TAX', $result['TxnTaxDetail']['TxnTaxCodeRef']['value']);
    }

    #[Test]
    public function automated_sales_tax_marks_non_taxable_lines_with_non(): void
    {
        $service = new class(new QuickBooksOAuthService) extends QuickBooksTaxService
        {
            public function usesAutomatedSalesTax(Integration $integration): bool
            {
                return true;
            }
        };

        $invoice = new Invoice(['tax_total' => 0, 'subtotal' => 50]);
        $invoice->setRelation('items', new Collection([
            new InvoiceItem(['taxable' => false, 'tax_rate' => 0, 'quantity' => 1, 'unit_price' => 50, 'discount' => 0]),
        ]));

        $lines = [[
            'Amount' => 50,
            'DetailType' => 'SalesItemLineDetail',
            'SalesItemLineDetail' => ['ItemRef' => ['value' => '1']],
        ]];

        $result = $service->enrichInvoicePayload(
            new Integration(['id' => 2, 'external_id' => 'r2']),
            $invoice,
            [],
            $lines,
        );

        $this->assertSame('NON', $result['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);
        $this->assertArrayNotHasKey('TxnTaxDetail', $result);
    }

    #[Test]
    public function taxable_line_without_item_tax_rate_uses_invoice_and_transaction_rate(): void
    {
        $service = new class(new QuickBooksOAuthService) extends QuickBooksTaxService
        {
            public function usesAutomatedSalesTax(Integration $integration): bool
            {
                return true;
            }
        };

        $invoice = new Invoice([
            'tax_total' => 6.0,
            'subtotal' => 100,
            'tax_rate' => 6.0,
            'billing_state' => 'FL',
        ]);
        $invoice->setRelation('items', new Collection([
            new InvoiceItem([
                'taxable' => true,
                'tax_rate' => 0,
                'quantity' => 1,
                'unit_price' => 100,
                'discount' => 0,
            ]),
        ]));
        $invoice->setRelation('transaction', (object) [
            'tax_rate' => 6.0,
            'tax_jurisdiction' => 'Florida',
        ]);

        $lines = [[
            'Amount' => 100,
            'DetailType' => 'SalesItemLineDetail',
            'SalesItemLineDetail' => ['ItemRef' => ['value' => '1']],
        ]];

        $result = $service->enrichInvoicePayload(
            new Integration(['id' => 3, 'external_id' => 'r3']),
            $invoice,
            [],
            $lines,
        );

        $this->assertSame('TAX', $result['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);
        $this->assertSame(6.0, $result['TxnTaxDetail']['TotalTax']);
    }

    #[Test]
    public function manual_tax_matches_jurisdiction_tax_code_name(): void
    {
        $service = new class(new QuickBooksOAuthService) extends QuickBooksTaxService
        {
            public function usesAutomatedSalesTax(Integration $integration): bool
            {
                return false;
            }

            protected function cachedTaxRates(Integration $integration): array
            {
                return [
                    ['Id' => '99', 'Name' => 'Florida', 'RateValue' => 6.0],
                ];
            }

            protected function cachedTaxCodes(Integration $integration): array
            {
                return [
                    [
                        'Id' => '55',
                        'Name' => 'Florida',
                        'SalesTaxRateList' => [
                            'TaxRateDetail' => [
                                ['TaxRateRef' => ['value' => '99']],
                            ],
                        ],
                    ],
                    ['Id' => '2', 'Name' => 'Non', 'SalesTaxRateList' => []],
                ];
            }
        };

        $invoice = new Invoice([
            'tax_total' => 6.0,
            'subtotal' => 100,
            'tax_jurisdiction' => 'Fort Lauderdale, FL, 33316',
            'tax_jurisdiction_code' => 'FL',
        ]);
        $invoice->setRelation('items', new Collection([
            new InvoiceItem([
                'taxable' => true,
                'tax_rate' => 6.0,
                'quantity' => 1,
                'unit_price' => 100,
                'discount' => 0,
            ]),
        ]));
        $invoice->setRelation('transaction', null);

        $lines = [[
            'Amount' => 100,
            'DetailType' => 'SalesItemLineDetail',
            'SalesItemLineDetail' => ['ItemRef' => ['value' => '1']],
        ]];

        $result = $service->enrichInvoicePayload(
            new Integration(['id' => 4, 'external_id' => 'r4']),
            $invoice,
            [],
            $lines,
        );

        $this->assertSame('55', $result['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);
        $this->assertSame('TaxExcluded', $result['GlobalTaxCalculation']);
        $this->assertSame(6.0, $result['TxnTaxDetail']['TotalTax']);
        $this->assertSame('99', $result['TxnTaxDetail']['TaxLine'][0]['TaxLineDetail']['TaxRateRef']['value']);
    }

    #[Test]
    public function taxable_item_in_db_wins_over_false_line_flag(): void
    {
        $service = new class(new QuickBooksOAuthService) extends QuickBooksTaxService
        {
            public function usesAutomatedSalesTax(Integration $integration): bool
            {
                return true;
            }
        };

        $item = new InvoiceItem([
            'taxable' => true,
            'tax_rate' => 0,
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 0,
        ]);
        $item->syncOriginal();

        $invoice = new Invoice(['tax_total' => 6.0, 'subtotal' => 100, 'tax_rate' => 6.0]);
        $invoice->setRelation('items', new Collection([$item]));

        $lines = [[
            'Amount' => 100,
            'DetailType' => 'SalesItemLineDetail',
            'SalesItemLineDetail' => ['ItemRef' => ['value' => '1']],
            QuickBooksTaxService::LINE_TAXABLE_FLAG => false,
        ]];

        $result = $service->enrichInvoicePayload(
            new Integration(['id' => 6, 'external_id' => 'r6']),
            $invoice,
            [],
            $lines,
        );

        $this->assertSame('TAX', $result['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);
    }

    #[Test]
    public function manual_tax_with_no_tax_codes_routes_to_ast_when_rates_exist(): void
    {
        $service = new class(new QuickBooksOAuthService) extends QuickBooksTaxService
        {
            public function usesAutomatedSalesTax(Integration $integration): bool
            {
                return false;
            }

            protected function cachedTaxRates(Integration $integration): array
            {
                return [
                    ['Id' => '1', 'Name' => 'Florida', 'RateValue' => 6.0],
                ];
            }

            protected function cachedTaxCodes(Integration $integration): array
            {
                return [];
            }
        };

        $invoice = new Invoice([
            'tax_total' => 6.0,
            'subtotal' => 100,
            'tax_rate' => 6.0,
        ]);
        $invoice->setRelation('items', new Collection([
            new InvoiceItem([
                'taxable' => true,
                'tax_rate' => 6.0,
                'quantity' => 1,
                'unit_price' => 100,
                'discount' => 0,
            ]),
        ]));

        $lines = [[
            'Amount' => 100,
            'DetailType' => 'SalesItemLineDetail',
            'SalesItemLineDetail' => ['ItemRef' => ['value' => '1']],
        ]];

        $result = $service->enrichInvoicePayload(
            new Integration(['id' => 7, 'external_id' => 'r7']),
            $invoice,
            [],
            $lines,
        );

        $this->assertSame('TAX', $result['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);
        $this->assertSame('TAX', $result['TxnTaxDetail']['TxnTaxCodeRef']['value']);
        $this->assertSame(6.0, $result['TxnTaxDetail']['TotalTax']);
    }

    #[Test]
    public function preferences_country_code_us_enables_automated_sales_tax(): void
    {
        $integration = new Integration([
            'id' => 8,
            'external_id' => 'realm-us',
            'access_token' => 'token',
        ]);

        $oauth = $this->createMock(QuickBooksOAuthService::class);
        $oauth->method('refreshAccessTokenIfExpiredForIntegration');
        $oauth->method('accountingApiBaseUrl')->willReturn('https://sandbox-quickbooks.api.intuit.com');

        \Illuminate\Support\Facades\Http::fake([
            '*/preferences*' => \Illuminate\Support\Facades\Http::response([
                'Preferences' => [
                    'OtherPrefs' => [
                        'NameValue' => [
                            ['Name' => 'CountryCode', 'Value' => 'US'],
                        ],
                    ],
                    'TaxPrefs' => [
                        'PartnerTaxEnabled' => false,
                    ],
                ],
            ]),
        ]);

        $service = new QuickBooksTaxService($oauth);
        Cache::forget("qbo_ast_{$integration->id}_{$integration->external_id}");

        $this->assertTrue($service->usesAutomatedSalesTax($integration));
    }

    #[Test]
    public function line_taxable_flag_on_payload_drives_ast_tax_code_when_item_not_taxable_in_db(): void
    {
        $service = new class(new QuickBooksOAuthService) extends QuickBooksTaxService
        {
            public function usesAutomatedSalesTax(Integration $integration): bool
            {
                return true;
            }
        };

        $invoice = new Invoice(['tax_total' => 10, 'subtotal' => 100]);
        $invoice->setRelation('items', new Collection([
            new InvoiceItem(['taxable' => false, 'tax_rate' => 0, 'quantity' => 1, 'unit_price' => 100, 'discount' => 0]),
        ]));

        $lines = [[
            'Amount' => 100,
            'DetailType' => 'SalesItemLineDetail',
            'SalesItemLineDetail' => ['ItemRef' => ['value' => '1']],
            QuickBooksTaxService::LINE_TAXABLE_FLAG => true,
        ]];

        $result = $service->enrichInvoicePayload(
            new Integration(['id' => 5, 'external_id' => 'r5']),
            $invoice,
            [],
            $lines,
        );

        $this->assertSame('TAX', $result['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);
        $this->assertArrayNotHasKey(QuickBooksTaxService::LINE_TAXABLE_FLAG, $result['Line'][0]);
    }
}
