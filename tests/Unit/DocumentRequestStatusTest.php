<?php

namespace Tests\Unit;

use App\Domain\DocumentRequest\Enums\DocumentRequestStatus;
use PHPUnit\Framework\TestCase;

class DocumentRequestStatusTest extends TestCase
{
    public function test_status_values(): void
    {
        $this->assertSame(['pending', 'fulfilled', 'cancelled'], DocumentRequestStatus::values());
    }
}
