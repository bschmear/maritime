<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\AssetOption\Actions\DeleteAssetOption;
use App\Domain\AssetOption\Models\AssetOption;
use App\Domain\AssetOption\Models\AssetOptionValue;
use App\Domain\AssetOption\Models\EstimateSelectedOption;
use App\Domain\AssetOption\Support\AssetOptionLineUsageGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeleteAssetOptionTest extends TestCase
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

        Schema::connection('tenant')->create('asset_options', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('input_type');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('asset_option_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('option_id');
            $table->string('label');
            $table->string('value')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('transaction_line_item_selected_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_line_item_id')->nullable();
            $table->unsignedBigInteger('option_id');
            $table->unsignedBigInteger('option_value_id');
            $table->string('option_name')->nullable();
            $table->string('value_label')->nullable();
            $table->timestamps();
        });
    }

    public function test_deletes_unused_option(): void
    {
        $option = AssetOption::query()->create([
            'name' => 'Unused option',
            'slug' => 'unused-option',
            'input_type' => 'toggle',
            'active' => true,
        ]);

        $result = (new DeleteAssetOption)($option->id);

        $this->assertTrue($result['success']);
        $this->assertDatabaseMissing('asset_options', ['id' => $option->id], 'tenant');
    }

    public function test_blocks_delete_when_used_on_line_item_and_offers_inactive(): void
    {
        $option = AssetOption::query()->create([
            'name' => 'Used option',
            'slug' => 'used-option',
            'input_type' => 'toggle',
            'active' => true,
        ]);

        $value = AssetOptionValue::query()->create([
            'option_id' => $option->id,
            'label' => 'On',
            'value' => 'on',
        ]);

        EstimateSelectedOption::query()->create([
            'transaction_line_item_id' => 1,
            'option_id' => $option->id,
            'option_value_id' => $value->id,
            'option_name' => $option->name,
            'value_label' => $value->label,
        ]);

        $result = (new DeleteAssetOption)($option->id);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['offer_inactive']);
        $this->assertSame(AssetOptionLineUsageGuard::MESSAGE, $result['message']);
        $this->assertDatabaseHas('asset_options', ['id' => $option->id], 'tenant');
    }
}
