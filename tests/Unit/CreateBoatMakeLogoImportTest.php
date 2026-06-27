<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\BoatMake\Actions\CreateBoatMake;
use App\Domain\BoatMake\Models\BoatMake;
use App\Domain\InventoryCatalog\Models\InventoryBoatMake;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreateBoatMakeLogoImportTest extends TestCase
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
            'database.connections.inventory' => array_merge(
                config('database.connections.sqlite'),
                ['database' => ':memory:']
            ),
        ]);
        DB::purge('tenant');
        DB::purge('inventory');

        Schema::connection('tenant')->create('boat_make', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->string('slug')->nullable();
            $table->boolean('is_custom')->default(false);
            $table->boolean('use_default_logo')->default(true);
            $table->string('default_brand_image', 512)->nullable();
            $table->unsignedBigInteger('custom_logo_id')->nullable();
            $table->boolean('active')->default(true);
            $table->json('asset_types')->nullable();
            $table->string('brand_key')->nullable();
            $table->timestamps();
        });

        Schema::connection('inventory')->create('boat_make', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->string('slug')->unique();
            $table->boolean('active')->default(true);
            $table->string('logo_url', 512)->nullable();
            $table->timestamps();
        });

        InventoryBoatMake::query()->create([
            'display_name' => 'Zodiac',
            'slug' => 'zodiac',
            'logo_url' => 'https://cdn.example.com/public/inventory/boat_makes/zodiac.webp',
        ]);
    }

    public function test_create_from_catalog_brand_key_sets_default_logo_fields(): void
    {
        $result = app(CreateBoatMake::class)([
            'display_name' => 'Zodiac',
            'asset_types' => [1],
            'brand_key' => 'zodiac',
            'is_custom' => false,
            'active' => true,
        ]);

        $this->assertTrue($result['success']);
        $record = $result['record'];
        $this->assertInstanceOf(BoatMake::class, $record);
        $this->assertTrue($record->use_default_logo);
        $this->assertSame(
            'https://cdn.example.com/public/inventory/boat_makes/zodiac.webp',
            $record->default_brand_image
        );
    }
}
