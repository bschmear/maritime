<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\AssetUnit\Support\AssetUnitImportService;
use App\Domain\AssetUnit\Support\AssetUnitSpreadsheetParser;
use App\Enums\Inventory\UnitCondition;
use App\Enums\Inventory\UnitStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetUnitImportServiceTest extends TestCase
{
    #[Test]
    public function it_resolves_status_and_condition_labels(): void
    {
        $service = new AssetUnitImportService;

        $reflection = new \ReflectionClass($service);

        $resolveStatus = $reflection->getMethod('resolveStatus');
        $resolveStatus->setAccessible(true);

        $resolveCondition = $reflection->getMethod('resolveCondition');
        $resolveCondition->setAccessible(true);

        $this->assertSame(UnitStatus::Available->id(), $resolveStatus->invoke($service, 'Available'));
        $this->assertSame(UnitStatus::Sold->id(), $resolveStatus->invoke($service, 'sold'));
        $this->assertSame(UnitCondition::BrandNew->id(), $resolveCondition->invoke($service, 'New'));
        $this->assertSame(UnitCondition::Used->id(), $resolveCondition->invoke($service, '2'));
    }

    #[Test]
    public function default_column_map_matches_export_headers(): void
    {
        $map = AssetUnitSpreadsheetParser::defaultColumnMap();

        $this->assertSame('status', $map['Status']);
        $this->assertSame('condition', $map['Condition']);
        $this->assertSame('cost', $map['Cost']);
        $this->assertSame('asking_price', $map['Asking Price']);
        $this->assertSame('id', $map['ID']);
    }
}
