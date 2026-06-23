<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\AssetUnit\Support\InvoiceImport\InvoiceExistingUnitDetector;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceExistingUnitDetectorTest extends TestCase
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

        Schema::connection('tenant')->create('asset_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->string('hin')->nullable();
            $table->string('serial_number')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->unsignedTinyInteger('condition')->default(1);
            $table->unsignedTinyInteger('status')->default(4);
            $table->timestamps();
        });
    }

    #[Test]
    public function it_flags_rows_with_existing_hin_or_serial_and_excludes_them(): void
    {
        AssetUnit::query()->create([
            'asset_id' => 1,
            'hin' => 'XMO50167H324',
            'serial_number' => null,
            'cost' => 1000,
            'condition' => 1,
            'status' => 4,
        ]);

        AssetUnit::query()->create([
            'asset_id' => 1,
            'hin' => null,
            'serial_number' => '00046J425',
            'cost' => 2000,
            'condition' => 1,
            'status' => 4,
        ]);

        $rows = [
            [
                'row_index' => 0,
                'hin' => 'xmo50167h324',
                'serial_number' => null,
                'include' => true,
            ],
            [
                'row_index' => 1,
                'hin' => null,
                'serial_number' => '00046J425',
                'include' => true,
            ],
            [
                'row_index' => 2,
                'hin' => 'NEW-HIN-12345678',
                'serial_number' => null,
                'include' => true,
            ],
        ];

        $result = (new InvoiceExistingUnitDetector)->apply($rows);

        $this->assertTrue($result[0]['already_exists']);
        $this->assertFalse($result[0]['include']);
        $this->assertSame('hin', $result[0]['existing_match_field']);

        $this->assertTrue($result[1]['already_exists']);
        $this->assertFalse($result[1]['include']);
        $this->assertSame('serial_number', $result[1]['existing_match_field']);

        $this->assertFalse($result[2]['already_exists']);
        $this->assertTrue($result[2]['include']);
    }
}
