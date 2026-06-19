<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Support\AssetLayoutFootprint;
use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Support\LengthMillimeters;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AssetLayoutFootprintTest extends TestCase
{
    public function test_default_for_uses_asset_length_and_width_in_millimeters(): void
    {
        $asset = new Asset([
            'length' => (int) LengthMillimeters::fromImperial(20, 0),
            'width' => (int) LengthMillimeters::fromImperial(8, 0),
        ]);

        $footprint = AssetLayoutFootprint::defaultFor($asset);

        $this->assertEqualsWithDelta(20.0, $footprint['length_ft'], 0.01);
        $this->assertEqualsWithDelta(8.0, $footprint['width_ft'], 0.01);
    }

    public function test_default_for_prefers_variant_dimensions_over_asset(): void
    {
        $asset = new Asset([
            'length' => (int) LengthMillimeters::fromImperial(20, 0),
            'width' => (int) LengthMillimeters::fromImperial(8, 0),
        ]);

        $variant = new AssetVariant([
            'length' => (int) LengthMillimeters::fromImperial(24, 0),
            'width' => (int) LengthMillimeters::fromImperial(10, 0),
        ]);

        $unit = new AssetUnit;
        $unit->setRelation('assetVariant', $variant);

        $footprint = AssetLayoutFootprint::defaultFor($asset, $unit);

        $this->assertEqualsWithDelta(24.0, $footprint['length_ft'], 0.01);
        $this->assertEqualsWithDelta(10.0, $footprint['width_ft'], 0.01);
    }

    public function test_resolve_length_width_falls_back_to_legacy_beam_string(): void
    {
        $asset = new Asset([
            'length' => 6096,
            'width' => null,
            'beam' => "8'6\"",
        ]);

        [$lengthMm, $widthMm] = AssetLayoutFootprint::resolveLengthWidthMillimeters($asset);

        $this->assertSame(6096, $lengthMm);
        $this->assertSame(LengthMillimeters::fromImperial(8, 6), $widthMm);
    }

    public function test_unit_short_label_falls_back_to_asset_hin(): void
    {
        $asset = new Asset([
            'hin' => 'ABC12345',
        ]);

        $unit = new AssetUnit([
            'id' => 7,
        ]);

        $this->assertSame('Hull: ABC12345', AssetLayoutFootprint::unitShortLabel($unit, $asset));
    }

    public function test_apply_footprint_feet_converts_feet_to_millimeters_on_asset(): void
    {
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
            $table->unsignedTinyInteger('type')->default(1);
            $table->integer('length')->nullable();
            $table->integer('width')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('asset_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->timestamps();
        });

        $asset = Asset::query()->create(['type' => 1, 'length' => 1000, 'width' => 500]);
        $unit = AssetUnit::query()->create(['asset_id' => $asset->id]);

        AssetLayoutFootprint::applyFootprintFeet($asset, $unit, 20.0, 8.0);

        $asset->refresh();
        $this->assertSame(6096, (int) $asset->length);
        $this->assertSame(2438, (int) $asset->width);
    }
}
