<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\InvoiceItem\Models\InvoiceItem;
use App\Domain\ServiceItem\Models\ServiceItem;
use App\Services\Payments\QuickBooksAccountingService;
use App\Services\Payments\QuickBooksOAuthService;
use App\Services\Payments\QuickBooksTaxService;
use App\Services\Payments\QuickBooksTermsService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class QuickBooksInvoiceLineItemRefTest extends TestCase
{
    #[Test]
    public function resolve_line_item_id_uses_linked_service_item_quickbooks_id(): void
    {
        $service = new QuickBooksAccountingService(
            new QuickBooksOAuthService,
            new QuickBooksTermsService(new QuickBooksOAuthService),
            new QuickBooksTaxService(new QuickBooksOAuthService),
        );

        $method = new ReflectionMethod(QuickBooksAccountingService::class, 'resolveLineItemId');
        $method->setAccessible(true);

        $invoiceItem = new InvoiceItem;
        $invoiceItem->setRelation('serviceItem', new ServiceItem([
            'quickbooks_item_id' => '99',
        ]));

        $resolved = $method->invoke($service, $invoiceItem, '1');

        $this->assertSame('99', $resolved);
    }

    #[Test]
    public function resolve_line_item_id_falls_back_to_default_when_unlinked(): void
    {
        $service = new QuickBooksAccountingService(
            new QuickBooksOAuthService,
            new QuickBooksTermsService(new QuickBooksOAuthService),
            new QuickBooksTaxService(new QuickBooksOAuthService),
        );

        $method = new ReflectionMethod(QuickBooksAccountingService::class, 'resolveLineItemId');
        $method->setAccessible(true);

        $invoiceItem = new InvoiceItem;

        $resolved = $method->invoke($service, $invoiceItem, '1');

        $this->assertSame('1', $resolved);
    }
}
