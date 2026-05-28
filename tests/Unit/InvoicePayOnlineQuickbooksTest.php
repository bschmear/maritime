<?php

namespace Tests\Unit;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\Invoice\Support\InvoicePayOnline;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoicePayOnlineQuickbooksTest extends TestCase
{
    #[Test]
    public function cannot_pay_online_when_invoice_is_quickbooks_managed(): void
    {
        $invoice = new Invoice([
            'status' => 'sent',
            'amount_due' => 100,
            'quickbooks_invoice_id' => '123',
        ]);

        $this->assertTrue($invoice->isQuickbooksManaged());
        $this->assertFalse(InvoicePayOnline::canPayOnline($invoice));
    }

    #[Test]
    public function is_quickbooks_managed_requires_invoice_id(): void
    {
        $invoice = new Invoice([
            'quickbooks_invoice_id' => null,
        ]);

        $this->assertFalse($invoice->isQuickbooksManaged());
    }
}
