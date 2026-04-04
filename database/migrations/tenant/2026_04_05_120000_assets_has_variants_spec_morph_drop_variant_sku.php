<?php

use App\Domain\Asset\Models\Asset;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->boolean('has_variants')->default(false);
        });

        if (Schema::hasColumn('asset_variants', 'sku')) {
            Schema::table('asset_variants', function (Blueprint $table) {
                $table->dropColumn('sku');
            });
        }

        Schema::table('asset_spec_values', function (Blueprint $table) {
            $table->nullableMorphs('specable');
        });

        $assetClass = Asset::class;

        DB::table('asset_spec_values')->orderBy('id')->chunkById(100, function ($rows) use ($assetClass): void {
            foreach ($rows as $row) {
                DB::table('asset_spec_values')->where('id', $row->id)->update([
                    'specable_type' => $assetClass,
                    'specable_id' => $row->asset_id,
                ]);
            }
        });

        Schema::table('asset_spec_values', function (Blueprint $table) {
            $table->dropForeign(['asset_id']);
        });

        Schema::table('asset_spec_values', function (Blueprint $table) {
            $table->dropUnique(['asset_id', 'asset_spec_definition_id']);
        });

        Schema::table('asset_spec_values', function (Blueprint $table) {
            $table->dropColumn('asset_id');
        });

        Schema::table('asset_spec_values', function (Blueprint $table) {
            $table->unique(
                ['specable_type', 'specable_id', 'asset_spec_definition_id'],
                'asset_spec_values_specable_definition_unique'
            );
        });

        $variantAssetIds = DB::table('asset_variants')->distinct()->pluck('asset_id');
        foreach ($variantAssetIds as $aid) {
            DB::table('assets')->where('id', $aid)->update(['has_variants' => true]);
        }
    }

    public function down(): void
    {
        throw new \RuntimeException('This migration cannot be reversed safely (polymorphic spec values + has_variants).');
    }
};
