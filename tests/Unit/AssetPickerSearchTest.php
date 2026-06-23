<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\BoatMake\Models\BoatMake;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AssetPickerSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('asset_variants');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('boat_make');

        Schema::create('boat_make', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->nullable();
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('make_id')->nullable();
            $table->string('display_name')->nullable();
            $table->string('slug')->nullable();
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->boolean('has_variants')->default(false);
            $table->timestamps();
        });

        Schema::create('asset_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id');
            $table->string('name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('key')->nullable();
            $table->uuid('public_uuid')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('asset_variants');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('boat_make');

        parent::tearDown();
    }

    public function test_search_matches_variant_display_name(): void
    {
        $make = BoatMake::query()->create(['display_name' => 'Boston Whaler']);
        $asset = Asset::query()->create([
            'make_id' => $make->id,
            'display_name' => 'Outrage',
            'model' => 'Outrage',
            'year' => 2024,
            'has_variants' => true,
        ]);
        AssetVariant::query()->create([
            'asset_id' => $asset->id,
            'name' => '250 Vantage',
            'display_name' => '250 Vantage',
        ]);

        $other = Asset::query()->create([
            'display_name' => 'Unrelated Model',
            'year' => 2020,
        ]);

        $ids = Asset::query()
            ->whereMatchesPickerSearch('250 Vantage')
            ->pluck('id')
            ->all();

        $this->assertSame([$asset->id], $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_search_still_matches_make_and_model(): void
    {
        $make = BoatMake::query()->create(['display_name' => 'Zodiac']);
        $asset = Asset::query()->create([
            'make_id' => $make->id,
            'display_name' => 'MilPro 870',
            'model' => 'MilPro 870',
            'year' => 2023,
        ]);

        $this->assertTrue(
            Asset::query()->whereMatchesPickerSearch('Zodiac')->whereKey($asset->id)->exists()
        );
        $this->assertTrue(
            Asset::query()->whereMatchesPickerSearch('MilPro')->whereKey($asset->id)->exists()
        );
    }
}
