<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Bill\Models\Bill;
use Tests\TestCase;

class BillEditRestrictionsTest extends TestCase
{
    public function test_quickbooks_synced_bill_has_restricted_editing(): void
    {
        $bill = new Bill([
            'quickbooks_bill_id' => '42',
            'status' => 'open',
        ]);

        $this->assertTrue($bill->isQuickbooksManaged());
        $this->assertTrue($bill->hasRestrictedEditing());
    }

    public function test_paid_bill_has_restricted_editing(): void
    {
        $bill = new Bill([
            'status' => 'paid',
        ]);

        $this->assertTrue($bill->isPaid());
        $this->assertTrue($bill->hasRestrictedEditing());
    }

    public function test_open_local_bill_is_fully_editable(): void
    {
        $bill = new Bill([
            'status' => 'open',
        ]);

        $this->assertFalse($bill->isQuickbooksManaged());
        $this->assertFalse($bill->isPaid());
        $this->assertFalse($bill->hasRestrictedEditing());
    }

    public function test_link_fields_are_allowed_when_editing_is_restricted(): void
    {
        $this->assertSame(['vendor_id', 'chart_of_account_id'], Bill::RESTRICTED_EDIT_ALLOWED_FIELDS);
    }
}
