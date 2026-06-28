<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\InventoryCatalog\Models\InventoryBoatMake;
use App\Domain\InventoryCatalog\Models\InventoryBoatType;
use App\Support\ManufacturerDetailsCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManufacturerDetailsCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.inventory' => array_merge(
                config('database.connections.sqlite'),
                ['database' => ':memory:']
            ),
        ]);
        DB::purge('inventory');

        Schema::connection('inventory')->create('boat_type', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('display_name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::connection('inventory')->create('boat_make', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->string('slug')->unique();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('boat_type_id')->nullable();
            $table->timestamps();
        });

        Schema::connection('inventory')->create('boat_make_boat_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boat_make_id')->constrained('boat_make')->cascadeOnDelete();
            $table->foreignId('boat_type_id')->constrained('boat_type')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['boat_make_id', 'boat_type_id']);
        });

        InventoryBoatType::query()->insert([
            ['slug' => 'power-rib', 'display_name' => 'Rigid Inflatable', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'power-tender', 'display_name' => 'Tender (Power)', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'power-inflatable', 'display_name' => 'Inflatable', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_for_slug_returns_curated_inflatable_brand_details(): void
    {
        $details = ManufacturerDetailsCatalog::forSlug('zodiac');

        $this->assertNotNull($details);
        $this->assertSame('https://www.zodiac-nautic.com', $details['url']);
        $this->assertStringContainsString('inflatable', strtolower($details['description']));
        $this->assertContains('power-rib', $details['boat_type_keys']);
    }

    public function test_inventory_payload_skips_description_when_existing_and_not_overwriting(): void
    {
        $payload = ManufacturerDetailsCatalog::inventoryPayload('highfield', false, 'Existing copy');

        $this->assertSame('https://www.highfieldboats.com', $payload['website_url']);
        $this->assertArrayNotHasKey('description', $payload);
    }

    public function test_inventory_payload_overwrites_description_when_requested(): void
    {
        $payload = ManufacturerDetailsCatalog::inventoryPayload('highfield', true, 'Existing copy');

        $this->assertArrayHasKey('description', $payload);
        $this->assertStringContainsString('RIB', $payload['description']);
    }

    public function test_sync_boat_types_for_make_attaches_many_categories_and_primary(): void
    {
        $make = InventoryBoatMake::query()->create([
            'display_name' => 'Zodiac',
            'slug' => 'zodiac',
            'active' => true,
        ]);

        $count = ManufacturerDetailsCatalog::syncBoatTypesForMake($make);

        $this->assertGreaterThan(1, $count);
        $make->refresh();
        $this->assertSame(
            InventoryBoatType::query()->where('slug', 'power-inflatable')->value('id'),
            $make->boat_type_id
        );
        $this->assertTrue($make->boatTypes()->where('slug', 'power-rib')->exists());
        $this->assertTrue($make->boatTypes()->where('slug', 'power-tender')->exists());
        $this->assertTrue(
            (bool) $make->boatTypes()->where('boat_type.slug', 'power-inflatable')->first()?->pivot?->is_primary
        );
    }
}
