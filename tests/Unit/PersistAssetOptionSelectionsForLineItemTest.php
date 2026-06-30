<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetOption\Models\AssetOption;
use App\Domain\AssetOption\Models\AssetOptionMakeAssignment;
use App\Domain\AssetOption\Models\AssetOptionValue;
use App\Domain\AssetOption\Models\EstimateSelectedOption;
use App\Domain\AssetOption\Services\PersistAssetOptionSelectionsForLineItem;
use App\Domain\BoatMake\Models\BoatMake;
use App\Domain\Estimate\Models\EstimateLineItem;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersistAssetOptionSelectionsForLineItemTest extends TestCase
{
    private Asset $asset;

    private AssetOption $assignedOption;

    private AssetOption $globalOption;

    private AssetOptionValue $assignedValue;

    private AssetOptionValue $globalValue;

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
            $table->decimal('cost', 10, 2)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('asset_option_make_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('option_id');
            $table->unsignedBigInteger('make_id');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('asset_option_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('option_id');
            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('transaction_line_items', function (Blueprint $table) {
            $table->id();
            $table->string('itemable_type')->nullable();
            $table->unsignedBigInteger('itemable_id')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('asset_options_fill_mode')->nullable();
            $table->json('customer_offered_option_ids')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('transaction_line_item_selected_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('estimate_id')->nullable();
            $table->unsignedBigInteger('transaction_line_item_id');
            $table->unsignedBigInteger('option_id');
            $table->unsignedBigInteger('option_value_id');
            $table->string('option_name');
            $table->string('value_label');
            $table->decimal('cost', 10, 2)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('taxable')->default(true);
            $table->timestamps();
        });

        $make = BoatMake::query()->create(['display_name' => 'Achilles', 'active' => true]);
        $this->asset = Asset::query()->create([
            'make_id' => $make->id,
            'display_name' => 'Model',
            'active' => true,
        ]);

        $this->assignedOption = $this->createOption('Assigned option', false);
        $this->globalOption = $this->createOption('All Achilles Boats Option', true);

        AssetOptionMakeAssignment::query()->create([
            'option_id' => $this->assignedOption->id,
            'make_id' => $make->id,
            'active' => true,
        ]);

        $this->assignedValue = AssetOptionValue::query()->where('option_id', $this->assignedOption->id)->first();
        $this->globalValue = AssetOptionValue::query()->where('option_id', $this->globalOption->id)->first();
    }

    #[Test]
    public function staff_mode_allows_global_selections_even_when_not_in_customer_offered_ids(): void
    {
        $lineItem = EstimateLineItem::query()->create([
            'itemable_type' => Asset::class,
            'itemable_id' => $this->asset->id,
            'position' => 0,
            'asset_options_fill_mode' => 'staff',
        ]);

        app(PersistAssetOptionSelectionsForLineItem::class)(
            $lineItem,
            [
                'itemable_type' => Asset::class,
                'itemable_id' => $this->asset->id,
                'asset_options_fill_mode' => 'staff',
                'customer_offered_option_ids' => [(int) $this->assignedOption->id],
            ],
            [
                ['option_id' => $this->assignedOption->id, 'option_value_id' => $this->assignedValue->id],
                ['option_id' => $this->globalOption->id, 'option_value_id' => $this->globalValue->id],
            ],
        );

        $this->assertSame(2, EstimateSelectedOption::query()->count());
    }

    #[Test]
    public function customer_mode_rejects_selections_not_in_customer_offered_ids(): void
    {
        $lineItem = EstimateLineItem::query()->create([
            'itemable_type' => Asset::class,
            'itemable_id' => $this->asset->id,
            'position' => 0,
            'asset_options_fill_mode' => 'customer',
        ]);

        $this->expectException(ValidationException::class);

        try {
            app(PersistAssetOptionSelectionsForLineItem::class)(
                $lineItem,
                [
                    'itemable_type' => Asset::class,
                    'itemable_id' => $this->asset->id,
                    'asset_options_fill_mode' => 'customer',
                    'customer_offered_option_ids' => [(int) $this->assignedOption->id],
                ],
                [
                    ['option_id' => $this->globalOption->id, 'option_value_id' => $this->globalValue->id],
                ],
            );
        } catch (ValidationException $e) {
            $this->assertStringContainsString('not offered on this line', $e->getMessage());

            throw $e;
        }
    }

    #[Test]
    public function it_persists_custom_zero_price_when_catalog_has_default(): void
    {
        $lineItem = EstimateLineItem::query()->create([
            'itemable_type' => Asset::class,
            'itemable_id' => $this->asset->id,
            'position' => 0,
            'asset_options_fill_mode' => 'staff',
        ]);

        app(PersistAssetOptionSelectionsForLineItem::class)(
            $lineItem,
            [
                'itemable_type' => Asset::class,
                'itemable_id' => $this->asset->id,
                'asset_options_fill_mode' => 'staff',
            ],
            [
                [
                    'option_id' => $this->assignedOption->id,
                    'option_value_id' => $this->assignedValue->id,
                    'price' => 0,
                ],
            ],
        );

        $row = EstimateSelectedOption::query()->first();
        $this->assertNotNull($row);
        $this->assertSame('0.00', (string) $row->price);
    }

    #[Test]
    public function it_persists_custom_price_over_catalog_default(): void
    {
        $lineItem = EstimateLineItem::query()->create([
            'itemable_type' => Asset::class,
            'itemable_id' => $this->asset->id,
            'position' => 0,
            'asset_options_fill_mode' => 'staff',
        ]);

        app(PersistAssetOptionSelectionsForLineItem::class)(
            $lineItem,
            [
                'itemable_type' => Asset::class,
                'itemable_id' => $this->asset->id,
                'asset_options_fill_mode' => 'staff',
            ],
            [
                [
                    'option_id' => $this->assignedOption->id,
                    'option_value_id' => $this->assignedValue->id,
                    'price' => 550,
                ],
            ],
        );

        $row = EstimateSelectedOption::query()->first();
        $this->assertNotNull($row);
        $this->assertSame('550.00', (string) $row->price);
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
            'label' => 'Choice 1',
            'value' => 'choice-1',
            'price' => '100.00',
            'sort_order' => 0,
            'active' => true,
        ]);

        return $option;
    }
}
