<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('width')->nullable()->after('beam');
            $table->unsignedSmallInteger('hull_type')->nullable();
            $table->unsignedSmallInteger('hull_material')->nullable();
            $table->unsignedSmallInteger('boat_type')->nullable();
        });

        DB::statement('UPDATE assets SET width = beam WHERE width IS NULL AND beam IS NOT NULL');

        Schema::table('asset_variants', function (Blueprint $table) {
            $table->string('length')->nullable()->after('display_name');
            $table->string('width')->nullable()->after('length');
        });

        $definitionIds = DB::table('asset_spec_definitions')
            ->whereIn('key', ['overall_length', 'overall_width'])
            ->pluck('id');

        if ($definitionIds->isNotEmpty()) {
            DB::table('asset_spec_values')
                ->whereIn('asset_spec_definition_id', $definitionIds)
                ->delete();

            DB::table('asset_spec_definitions')
                ->whereIn('key', ['overall_length', 'overall_width'])
                ->delete();
        }
    }

    public function down(): void
    {
        Schema::table('asset_variants', function (Blueprint $table) {
            $table->dropColumn(['length', 'width']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['width', 'hull_type', 'hull_material', 'boat_type']);
        });
    }
};
