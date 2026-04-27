<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base inventory brand row. Nullable FKs to boat_type / hull_type / hull_material are added in
 * 2026_04_27_100001_add_catalog_lookups_to_inventory_boat_make.php (runs after those lookup tables exist).
 */
return new class extends Migration
{
    protected $connection = 'inventory';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('boat_make')) {
            return;
        }

        Schema::connection($this->connection)->create('boat_make', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->string('slug')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('boat_make');
    }
};
