<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\AssetOption\Models\AssetOption;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetOptionToggleValueTest extends TestCase
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
            $table->boolean('is_required')->default(false);
            $table->boolean('allow_multiple')->default(false);
            $table->unsignedInteger('min_select')->nullable();
            $table->unsignedInteger('max_select')->nullable();
            $table->boolean('active')->default(true);
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
    }

    #[Test]
    public function it_creates_implicit_on_value_for_toggle_options(): void
    {
        $option = AssetOption::query()->create([
            'name' => 'Bimini',
            'slug' => 'bimini',
            'input_type' => 'toggle',
            'active' => true,
        ]);

        $value = $option->ensureToggleOnValue();

        $this->assertSame('on', $value->value);
        $this->assertSame('On', $value->label);
        $this->assertSame($value->id, $option->ensureToggleOnValue()->id);
    }
}
