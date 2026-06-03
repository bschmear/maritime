<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\InventoryCatalog\Support\CatalogImportSpecSync;
use PHPUnit\Framework\TestCase;

class CatalogImportSpecSyncTest extends TestCase
{
    public function test_kilograms_to_pounds_conversion(): void
    {
        $this->assertEqualsWithDelta(220.5, CatalogImportSpecSync::kilogramsToPounds(100), 0.1);
    }
}
