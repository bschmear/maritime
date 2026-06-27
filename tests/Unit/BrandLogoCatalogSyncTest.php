<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\BoatMake\Support\BrandLogoCatalogSync;
use App\Domain\InventoryCatalog\Models\InventoryBoatMake;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BrandLogoCatalogSyncTest extends TestCase
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

        Schema::connection('inventory')->create('boat_make', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->string('slug')->unique();
            $table->boolean('active')->default(true);
            $table->string('logo_url', 512)->nullable();
            $table->timestamps();
        });
    }

    public function test_import_defaults_returns_catalog_logo_url(): void
    {
        InventoryBoatMake::query()->create([
            'display_name' => 'Highfield',
            'slug' => 'highfield',
            'logo_url' => 'https://cdn.example.com/public/inventory/boat_makes/highfield.webp',
        ]);

        $defaults = BrandLogoCatalogSync::importDefaults('highfield');

        $this->assertTrue($defaults['use_default_logo']);
        $this->assertSame(
            'https://cdn.example.com/public/inventory/boat_makes/highfield.webp',
            $defaults['default_brand_image']
        );
    }

    public function test_import_defaults_without_catalog_logo(): void
    {
        InventoryBoatMake::query()->create([
            'display_name' => 'Unknown',
            'slug' => 'unknown',
        ]);

        $defaults = BrandLogoCatalogSync::importDefaults('unknown');

        $this->assertFalse($defaults['use_default_logo']);
        $this->assertNull($defaults['default_brand_image']);
    }
}
