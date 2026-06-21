<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\AssetUnit\Support\AssetUnitGoogleSheetImportService;
use App\Enums\Inventory\UnitCondition;
use App\Enums\Inventory\UnitStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetUnitGoogleSheetImportServiceTest extends TestCase
{
    #[Test]
    public function it_rejects_empty_sheet_rows(): void
    {
        $service = new AssetUnitGoogleSheetImportService;
        $result = $service->import([]);

        $this->assertSame(0, $result['updated']);
        $this->assertNotEmpty($result['errors']);
    }

    #[Test]
    public function it_resolves_status_and_condition_from_labels(): void
    {
        $service = new AssetUnitGoogleSheetImportService;
        $reflection = new \ReflectionClass($service);

        $resolveStatus = $reflection->getMethod('resolveStatus');
        $resolveStatus->setAccessible(true);
        $resolveCondition = $reflection->getMethod('resolveCondition');
        $resolveCondition->setAccessible(true);

        $this->assertSame(UnitStatus::Sold->id(), $resolveStatus->invoke($service, 'Sold'));
        $this->assertSame(UnitCondition::BrandNew->id(), $resolveCondition->invoke($service, 'New'));
    }

    #[Test]
    public function it_parses_length_in_feet_to_millimeters(): void
    {
        $service = new AssetUnitGoogleSheetImportService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseLengthFeet');
        $method->setAccessible(true);

        $mm = $method->invoke($service, '22.5 ft');
        $this->assertSame(6858, $mm);
    }
}
