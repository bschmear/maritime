<?php

namespace Tests\Unit;

use App\Services\Payments\QuickBooksAccountingService;
use App\Services\Payments\QuickBooksOAuthService;
use App\Services\Payments\QuickBooksTaxService;
use App\Services\Payments\QuickBooksTermsService;
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
}
