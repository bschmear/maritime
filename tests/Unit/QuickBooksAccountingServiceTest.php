<?php

namespace Tests\Unit;

use App\Domain\Integration\Models\Integration;
use App\Services\Payments\QuickBooksAccountingService;
use App\Services\Payments\QuickBooksOAuthService;
use App\Services\Payments\QuickBooksTaxService;
use App\Services\Payments\QuickBooksTermsService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuickBooksAccountingServiceTest extends TestCase
{
    #[Test]
    public function invoice_url_uses_sandbox_host_when_not_production(): void
    {
        config(['services.quickbooks.environment' => 'sandbox']);

        $oauth = new QuickBooksOAuthService;
        $service = new QuickBooksAccountingService($oauth, new QuickBooksTermsService($oauth), new QuickBooksTaxService($oauth));

        $this->assertSame(
            'https://sandbox.qbo.intuit.com/app/invoice?txnId=55',
            $service->invoiceUrl('55'),
        );
    }

    #[Test]
    public function invoice_url_uses_production_host_when_configured(): void
    {
        config(['services.quickbooks.environment' => 'production']);

        $oauth = new QuickBooksOAuthService;
        $service = new QuickBooksAccountingService($oauth, new QuickBooksTermsService($oauth), new QuickBooksTaxService($oauth));

        $this->assertSame(
            'https://qbo.intuit.com/app/invoice?txnId=99',
            $service->invoiceUrl('99'),
        );
    }

    #[Test]
    public function resolve_customer_invoice_url_returns_online_invoice_link(): void
    {
        config(['services.quickbooks.environment' => 'sandbox']);

        $integration = new Integration([
            'id' => 1,
            'external_id' => 'realm-1',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
        ]);

        Http::fake([
            '*/invoice/42*' => Http::response([
                'Invoice' => [
                    'Id' => '42',
                    'InvoiceLink' => 'https://pay.intuit.com/invoice/abc123',
                ],
            ]),
        ]);

        $oauth = $this->createMock(QuickBooksOAuthService::class);
        $oauth->method('refreshAccessTokenIfExpiredForIntegration');
        $oauth->method('accountingApiBaseUrl')->willReturn('https://sandbox-quickbooks.api.intuit.com');

        $service = new QuickBooksAccountingService($oauth, new QuickBooksTermsService($oauth), new QuickBooksTaxService($oauth));

        $url = $service->resolveCustomerInvoiceUrl($integration, '42', 'https://sandbox.qbo.intuit.com/app/invoice?txnId=42');

        $this->assertSame('https://pay.intuit.com/invoice/abc123', $url);
    }

    #[Test]
    public function resolve_customer_invoice_url_ignores_stored_staff_url_when_api_fails(): void
    {
        config(['services.quickbooks.environment' => 'sandbox']);

        $integration = new Integration([
            'id' => 2,
            'external_id' => 'realm-2',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
        ]);

        Http::fake([
            '*/invoice/42*' => Http::response([], 404),
        ]);

        $oauth = $this->createMock(QuickBooksOAuthService::class);
        $oauth->method('refreshAccessTokenIfExpiredForIntegration');
        $oauth->method('accountingApiBaseUrl')->willReturn('https://sandbox-quickbooks.api.intuit.com');

        $service = new QuickBooksAccountingService($oauth, new QuickBooksTermsService($oauth), new QuickBooksTaxService($oauth));

        $staffUrl = 'https://sandbox.qbo.intuit.com/app/invoice?txnId=42';
        $url = $service->resolveCustomerInvoiceUrl($integration, '42', $staffUrl);

        $this->assertNull($url);
    }
}
