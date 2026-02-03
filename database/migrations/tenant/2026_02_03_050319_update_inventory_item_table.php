<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            // Rename 'make' column to 'boat_make_id'
            $table->renameColumn('make', 'boat_make_id');
        });

        // Convert column type to bigint with explicit casting
        DB::statement('ALTER TABLE inventory_items ALTER COLUMN boat_make_id TYPE bigint USING boat_make_id::bigint');

        Schema::table('inventory_items', function (Blueprint $table) {
            // Add foreign key
            $table->foreign('boat_make_id')
                  ->references('id')
                  ->on('boat_make')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['boat_make_id']);
        });

        // Convert column back to string
        DB::statement('ALTER TABLE inventory_items ALTER COLUMN boat_make_id TYPE varchar USING boat_make_id::varchar');

        Schema::table('inventory_items', function (Blueprint $table) {
            // Rename column back
            $table->renameColumn('boat_make_id', 'make');
        });
    }
};