<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetOption\Models\AssetOption;
use App\Domain\AssetOption\Models\AssetOptionAssignment;
use App\Domain\AssetOption\Models\AssetOptionMakeAssignment;
use App\Domain\AssetOption\Models\AssetOptionValue;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\BoatMake\Models\BoatMake;
use App\Services\AssetOptionResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetOptionResolverTest extends TestCase
{
    private AssetOptionResolver $resolver;

    private Asset $asset;

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

        Schema::connection('tenant')->create('boat_make', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('make_id')->nullable();
            $table->string('display_name');
            $table->string('slug')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('asset_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id');
            $table->string('display_name')->nullable();
            $table->string('name')->nullable();
            $table->uuid('public_uuid')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('asset_options', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('input_type');
            $table->boolean('is_required')->default(false);
            $table->boolean('allow_multiple')->default(false);
            $table->unsignedInteger('min_select')->nullable();
            $table->unsignedInteger('max_select')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('is_global')->default(false);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('asset_option_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('option_id');
            $table->string('label');
            $table->string('value')->nullable();
            $table->string('color_hex')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('asset_option_make_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('option_id');
            $table->unsignedBigInteger('make_id');
            $table->decimal('cost_override', 10, 2)->nullable();
            $table->decimal('price_override', 10, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('asset_option_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('option_id');
            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->decimal('cost_override', 10, 2)->nullable();
            $table->decimal('price_override', 10, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $make = BoatMake::query()->create(['display_name' => 'Test Make', 'active' => true]);
        $this->asset = Asset::query()->create([
            'make_id' => $make->id,
            'display_name' => 'Test Model',
            'active' => true,
        ]);

        $this->resolver = app(AssetOptionResolver::class);
    }

    #[Test]
    public function resolve_excludes_global_options(): void
    {
        $assigned = $this->createOption('Hull color', false);
        $global = $this->createOption('Global add-on', true);

        AssetOptionMakeAssignment::query()->create([
            'option_id' => $assigned->id,
            'make_id' => $this->asset->make_id,
            'active' => true,
        ]);

        $resolved = $this->resolver->resolve($this->asset, null);

        $this->assertCount(1, $resolved);
        $this->assertSame($assigned->id, $resolved->first()['option_id']);
    }

    #[Test]
    public function resolve_global_returns_only_global_options(): void
    {
        $assigned = $this->createOption('Assigned', false);
        $global = $this->createOption('Global', true);

        AssetOptionMakeAssignment::query()->create([
            'option_id' => $assigned->id,
            'make_id' => $this->asset->make_id,
            'active' => true,
        ]);

        $globals = $this->resolver->resolveGlobal();

        $this->assertCount(1, $globals);
        $this->assertSame($global->id, $globals->first()['option_id']);
        $this->assertTrue($globals->first()['is_global']);
    }

    #[Test]
    public function resolve_by_ids_merges_assigned_and_global(): void
    {
        $assigned = $this->createOption('Assigned', false);
        $global = $this->createOption('Global', true);

        AssetOptionMakeAssignment::query()->create([
            'option_id' => $assigned->id,
            'make_id' => $this->asset->make_id,
            'active' => true,
        ]);

        $resolved = $this->resolver->resolveByIds($this->asset, null, [(int) $assigned->id, (int) $global->id]);

        $this->assertCount(2, $resolved);
        $ids = $resolved->pluck('option_id')->all();
        $this->assertContains($assigned->id, $ids);
        $this->assertContains($global->id, $ids);
    }

    #[Test]
    public function resolve_by_ids_honors_variant_assignment(): void
    {
        $variant = AssetVariant::query()->create([
            'asset_id' => $this->asset->id,
            'display_name' => 'Sport',
            'active' => true,
        ]);

        $option = $this->createOption('Package', false);

        AssetOptionAssignment::query()->create([
            'option_id' => $option->id,
            'asset_id' => $this->asset->id,
            'variant_id' => $variant->id,
            'price_override' => '999.00',
            'active' => true,
        ]);

        $resolved = $this->resolver->resolveByIds($this->asset, $variant, [(int) $option->id]);

        $this->assertSame('999.00', $resolved->first()['values'][0]['price']);
    }

    private function createOption(string $name, bool $isGlobal): AssetOption
    {
        $option = AssetOption::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'input_type' => 'select',
            'active' => true,
            'is_global' => $isGlobal,
        ]);

        AssetOptionValue::query()->create([
            'option_id' => $option->id,
            'label' => 'A',
            'value' => 'a',
            'price' => '100.00',
            'sort_order' => 0,
            'active' => true,
        ]);

        return $option;
    }
}
