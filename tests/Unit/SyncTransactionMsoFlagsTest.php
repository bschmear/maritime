<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\MsoRecord\Support\SyncTransactionMsoFlags;
use PHPUnit\Framework\TestCase;

class SyncTransactionMsoFlagsTest extends TestCase
{
    public function test_sync_class_is_loadable(): void
    {
        $this->assertTrue(class_exists(SyncTransactionMsoFlags::class));
        $this->assertTrue(method_exists(SyncTransactionMsoFlags::class, 'forTransaction'));
    }
}
