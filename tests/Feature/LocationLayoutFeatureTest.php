<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Location\Models\Location;
use App\Domain\Location\Models\LocationLayout;
use App\Domain\Location\Models\LocationLayoutUnit;
use App\Enums\Inventory\AssetType;
use App\Http\Controllers\Tenant\AssetUnitController;
use App\Http\Controllers\Tenant\LocationLayoutController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LocationLayoutFeatureTest extends TestCase
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

        Schema::connection('tenant')->create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('type');
            $table->string('display_name')->nullable();
            $table->string('slug')->nullable();
            $table->integer('length')->nullable();
            $table->integer('width')->nullable();
            $table->string('beam')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('asset_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable();
            $table->unsignedTinyInteger('status')->nullable();
            $table->string('hin')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('location_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->integer('width_ft');
            $table->integer('height_ft');
            $table->integer('grid_size')->default(1);
            $table->integer('scale')->default(10);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('tenant')->create('location_layout_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_layout_id')->constrained('location_layouts')->cascadeOnDelete();
            $table->foreignId('asset_unit_id')->constrained('asset_units')->cascadeOnDelete();
            $table->boolean('include_in_layout')->default(false);
            $table->decimal('x', 8, 2)->default(0);
            $table->decimal('y', 8, 2)->default(0);
            $table->unsignedSmallInteger('rotation')->default(0);
            $table->integer('z_index')->default(0);
            $table->string('name')->nullable();
            $table->decimal('length_ft', 8, 2)->nullable();
            $table->decimal('width_ft', 8, 2)->nullable();
            $table->string('color')->nullable();
            $table->timestamps();
            $table->unique(['location_layout_id', 'asset_unit_id']);
        });
    }

    public function test_sync_layout_updates_layout_and_placement_rows(): void
    {
        $location = Location::query()->create(['display_name' => 'Main yard']);

        $layout = LocationLayout::query()->create([
            'location_id' => $location->id,
            'name' => 'Default',
            'width_ft' => 60,
            'height_ft' => 40,
            'grid_size' => 1,
            'scale' => 10,
            'meta' => [],
        ]);

        $asset = Asset::query()->create([
            'type' => AssetType::Boat->value,
            'display_name' => 'Test boat',
            'length' => 6096,
            'width' => 2438,
        ]);

        $unit = AssetUnit::query()->create([
            'asset_id' => $asset->id,
            'location_id' => $location->id,
            'status' => 1,
            'hin' => 'ABC123',
        ]);

        $placement = LocationLayoutUnit::query()->create([
            'location_layout_id' => $layout->id,
            'asset_unit_id' => $unit->id,
            'include_in_layout' => false,
            'x' => 0,
            'y' => 0,
            'rotation' => 0,
            'z_index' => 0,
            'length_ft' => 20,
            'width_ft' => 8,
            'color' => '#3B82F6',
        ]);

        $controller = app(LocationLayoutController::class);
        $response = $controller->syncLayout(
            Request::create("/locations/{$location->id}/layouts/{$layout->id}/sync", 'PUT', [
                'width_ft' => 80,
                'height_ft' => 50,
                'perimeter' => [
                    ['x' => 0, 'y' => 0],
                    ['x' => 80, 'y' => 0],
                    ['x' => 80, 'y' => 50],
                    ['x' => 0, 'y' => 50],
                ],
                'fixtures' => [],
                'items' => [
                    [
                        'placement_id' => $placement->id,
                        'include_in_layout' => true,
                        'x' => 12.5,
                        'y' => 6.25,
                        'rotation' => 90,
                        'z_index' => 2,
                        'name' => 'Slip A',
                        'length_ft' => 20,
                        'width_ft' => 8,
                    ],
                ],
            ]),
            $location,
            $layout,
        );

        $this->assertSame(200, $response->getStatusCode());

        $layout->refresh();
        $placement->refresh();

        $this->assertSame(80, (int) $layout->width_ft);
        $this->assertSame(50, (int) $layout->height_ft);
        $this->assertCount(4, $layout->meta['perimeter'] ?? []);

        $this->assertTrue($placement->include_in_layout);
        $this->assertSame(12.5, (float) $placement->x);
        $this->assertSame(6.25, (float) $placement->y);
        $this->assertSame(90, (int) $placement->rotation);
        $this->assertSame('Slip A', $placement->name);
    }

    public function test_transfer_location_updates_asset_unit_location_id(): void
    {
        $from = Location::query()->create(['display_name' => 'Yard A']);
        $to = Location::query()->create(['display_name' => 'Yard B']);

        $asset = Asset::query()->create([
            'type' => AssetType::Boat->value,
            'display_name' => 'Transfer boat',
        ]);

        $unit = AssetUnit::query()->create([
            'asset_id' => $asset->id,
            'location_id' => $from->id,
            'status' => 1,
        ]);

        $controller = app(AssetUnitController::class);
        $response = $controller->transferLocation(
            Request::create("/asset-units/{$unit->id}/transfer-location", 'PATCH', [
                'location_id' => $to->id,
            ]),
            $unit,
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($to->id, $unit->fresh()->location_id);
    }

    public function test_update_layout_footprint_updates_asset_dimensions(): void
    {
        $asset = Asset::query()->create([
            'type' => AssetType::Boat->value,
            'display_name' => 'Sized boat',
            'length' => 1000,
            'width' => 500,
        ]);

        $unit = AssetUnit::query()->create([
            'asset_id' => $asset->id,
            'status' => 1,
        ]);

        $controller = app(AssetUnitController::class);
        $response = $controller->updateLayoutFootprint(
            Request::create("/asset-units/{$unit->id}/layout-footprint", 'PATCH', [
                'length_ft' => 24,
                'width_ft' => 10,
            ]),
            $unit,
        );

        $this->assertSame(200, $response->getStatusCode());
        $asset->refresh();
        $this->assertSame(7315, (int) $asset->length);
        $this->assertSame(3048, (int) $asset->width);
    }

    public function test_grouped_for_print_returns_on_layout_placements_by_type(): void
    {
        $location = Location::query()->create(['display_name' => 'Main yard']);

        $layout = LocationLayout::query()->create([
            'location_id' => $location->id,
            'name' => 'Warehouse A',
            'width_ft' => 60,
            'height_ft' => 40,
            'grid_size' => 1,
            'scale' => 10,
            'meta' => [],
        ]);

        $boatAsset = Asset::query()->create([
            'type' => AssetType::Boat->value,
            'display_name' => 'Boat one',
        ]);
        $engineAsset = Asset::query()->create([
            'type' => AssetType::Engine->value,
            'display_name' => 'Engine one',
        ]);

        $boatUnit = AssetUnit::query()->create([
            'asset_id' => $boatAsset->id,
            'location_id' => $location->id,
            'status' => 1,
        ]);
        $engineUnit = AssetUnit::query()->create([
            'asset_id' => $engineAsset->id,
            'location_id' => $location->id,
            'status' => 1,
        ]);

        LocationLayoutUnit::query()->create([
            'location_layout_id' => $layout->id,
            'asset_unit_id' => $boatUnit->id,
            'include_in_layout' => true,
            'x' => 2,
            'y' => 3,
            'length_ft' => 22,
            'width_ft' => 8,
        ]);
        LocationLayoutUnit::query()->create([
            'location_layout_id' => $layout->id,
            'asset_unit_id' => $engineUnit->id,
            'include_in_layout' => false,
            'x' => 10,
            'y' => 4,
            'length_ft' => 4,
            'width_ft' => 2,
        ]);

        $grouped = \App\Domain\Location\Support\LocationUnitsPayload::groupedForPrint($layout, $location);

        $this->assertCount(1, $grouped['boats']);
        $this->assertCount(1, $grouped['engines']);
        $this->assertSame('Boat one', $grouped['boats'][0]['display_name']);
        $this->assertSame('Engine one', $grouped['engines'][0]['display_name']);
        $this->assertTrue($grouped['boats'][0]['include_in_layout']);
        $this->assertFalse($grouped['engines'][0]['include_in_layout']);
    }
}
