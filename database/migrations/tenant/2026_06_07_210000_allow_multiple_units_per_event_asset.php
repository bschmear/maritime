<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boat_show_event_assets', function (Blueprint $table) {
            $table->dropUnique(['boat_show_event_id', 'asset_id']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX boat_show_event_assets_event_unit_unique
             ON boat_show_event_assets (boat_show_event_id, asset_unit_id)
             WHERE asset_unit_id IS NOT NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX boat_show_event_assets_event_asset_no_unit_unique
             ON boat_show_event_assets (boat_show_event_id, asset_id)
             WHERE asset_unit_id IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS boat_show_event_assets_event_unit_unique');
        DB::statement('DROP INDEX IF EXISTS boat_show_event_assets_event_asset_no_unit_unique');

        Schema::table('boat_show_event_assets', function (Blueprint $table) {
            $table->unique(['boat_show_event_id', 'asset_id']);
        });
    }
};
