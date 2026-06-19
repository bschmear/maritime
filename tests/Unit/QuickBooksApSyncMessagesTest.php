<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\QuickBooks\QuickBooksApSyncMessages;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class QuickBooksApSyncMessagesTest extends TestCase
{
    #[Test]
    public function failure_message_instructs_user_to_check_quickbooks(): void
    {
        $message = QuickBooksApSyncMessages::failure('bill', 'Vendor is not linked.');

        $this->assertStringContainsString('QuickBooks sync failed', $message);
        $this->assertStringContainsString('saved in Helmful', $message);
        $this->assertStringContainsString('Vendor is not linked.', $message);
        $this->assertStringContainsString('open QuickBooks Online', $message);
        $this->assertStringContainsString('check whether the bill is there', $message);
    }
}
