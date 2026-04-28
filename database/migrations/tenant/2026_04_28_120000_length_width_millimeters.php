<?php

use App\Support\LengthMillimeters;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['assets', 'asset_variants'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'length') || ! Schema::hasColumn($table, 'width')) {
                continue;
            }

            Schema::table($table, function (Blueprint $b) {
                $b->unsignedInteger('_length_mm_mig')->nullable();
                $b->unsignedInteger('_width_mm_mig')->nullable();
            });

            $idCol = 'id';
            $rows = DB::table($table)->select($idCol, 'length', 'width')->get();
            foreach ($rows as $row) {
                $len = LengthMillimeters::fromLegacyString($row->length);
                $wid = LengthMillimeters::fromLegacyString($row->width);
                DB::table($table)->where($idCol, $row->{$idCol})
                    ->update(['_length_mm_mig' => $len, '_width_mm_mig' => $wid]);
            }

            Schema::table($table, function (Blueprint $b) {
                $b->dropColumn(['length', 'width']);
            });
            Schema::table($table, function (Blueprint $b) {
                $b->renameColumn('_length_mm_mig', 'length');
                $b->renameColumn('_width_mm_mig', 'width');
            });
        }
    }

    public function down(): void
    {
        // Irreversible without losing precision; string columns are not restored.
    }
};
