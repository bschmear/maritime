<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\MsoRecord\Status;
use PHPUnit\Framework\TestCase;

class MsoRecordStatusTest extends TestCase
{
    public function test_resolved_statuses(): void
    {
        $this->assertTrue(Status::Submitted->isResolved());
        $this->assertTrue(Status::NotRequired->isResolved());
        $this->assertFalse(Status::Draft->isResolved());
    }

    public function test_options_include_all_cases(): void
    {
        $options = Status::options();
        $this->assertCount(3, $options);
        $this->assertSame('draft', $options[0]['value']);
    }
}
