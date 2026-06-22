<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\AssetUnit\Support\AssetUnitGoogleSheetColumnRegistry;
use App\Domain\AssetUnit\Support\AssetUnitGoogleSheetUnitResolver;
use App\Enums\Inventory\AssetType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetUnitGoogleSheetUnitResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'tenant',
            'database.connections.tenant' => array_merge(
                config('database.connections.sqlite'),
                ['database' => ':memory:']
            ),
        ]);
        DB::purge('tenant');

        Schema::connection('tenant')->create('assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('type');
            $table->string('display_name')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('asset_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->unsignedTinyInteger('status')->nullable();
            $table->string('hin')->nullable();
            $table->string('serial_number')->nullable();
            $table->timestamps();
        });
    }
    #[Test]
    public function it_matches_unit_by_hin_before_serial(): void
    {
        $asset = $this->createAsset();
        $hinUnit = $this->createUnit($asset, ['hin' => 'ABC-123', 'serial_number' => 'SN-ONE']);
        $this->createUnit($asset, ['hin' => null, 'serial_number' => 'SN-TWO']);

        $resolver = new AssetUnitGoogleSheetUnitResolver;
        [$unit, $error] = $resolver->resolve([
            AssetUnitGoogleSheetColumnRegistry::HEADER_HIN => 'ABC 123',
            AssetUnitGoogleSheetColumnRegistry::HEADER_SERIAL => 'SN-TWO',
        ], 2);

        $this->assertNull($error);
        $this->assertNotNull($unit);
        $this->assertTrue($hinUnit->is($unit));
    }

    #[Test]
    public function it_falls_back_to_serial_when_hin_is_missing(): void
    {
        $asset = $this->createAsset();
        $serialUnit = $this->createUnit($asset, ['hin' => null, 'serial_number' => 'SER-999']);

        $resolver = new AssetUnitGoogleSheetUnitResolver;
        [$unit, $error] = $resolver->resolve([
            AssetUnitGoogleSheetColumnRegistry::HEADER_SERIAL => 'SER/999',
        ], 3);

        $this->assertNull($error);
        $this->assertNotNull($unit);
        $this->assertTrue($serialUnit->is($unit));
    }

    #[Test]
    public function it_accepts_legacy_hin_header(): void
    {
        $asset = $this->createAsset();
        $unit = $this->createUnit($asset, ['hin' => 'HIN-LEGACY', 'serial_number' => null]);

        $resolver = new AssetUnitGoogleSheetUnitResolver;
        [$resolved, $error] = $resolver->resolve(['HIN' => 'HIN-LEGACY'], 4);

        $this->assertNull($error);
        $this->assertTrue($unit->is($resolved));
    }

    #[Test]
    public function it_reports_ambiguous_hin_matches(): void
    {
        $asset = $this->createAsset();
        $this->createUnit($asset, ['hin' => 'DUP-1']);
        $this->createUnit($asset, ['hin' => 'DUP-1']);

        $resolver = new AssetUnitGoogleSheetUnitResolver;
        [$unit, $error] = $resolver->resolve([
            AssetUnitGoogleSheetColumnRegistry::HEADER_HIN => 'DUP-1',
        ], 5);

        $this->assertNull($unit);
        $this->assertStringContainsString('Multiple units match HID', $error ?? '');
    }

    private function createAsset(): Asset
    {
        return Asset::query()->create([
            'type' => AssetType::Boat->value,
            'display_name' => 'Test boat',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUnit(Asset $asset, array $attributes = []): AssetUnit
    {
        return AssetUnit::query()->create(array_merge([
            'asset_id' => $asset->id,
            'status' => 1,
        ], $attributes));
    }
}
