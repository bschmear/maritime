<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asset_spec_definitions', function (Blueprint $table) {
            $table->id();

            $table->string('key')->unique(); // overall_length
            $table->string('label');         // Overall Length
            $table->string('group')->nullable(); // dimensions, engine, capacity
            $table->string('type'); // number, text, select, boolean

            $table->string('unit')->nullable();          // current/legacy unit
            $table->string('unit_imperial')->nullable(); // ft, lb, hp, deg, in
            $table->string('unit_metric')->nullable();   // m, kg, kW, deg, cm

            $table->json('options')->nullable(); // for select fields

            $table->boolean('is_filterable')->default(false); // marketplace filters
            $table->boolean('is_visible')->default(false);     // always show in UI
            $table->boolean('is_required')->default(false);

            $table->integer('position')->default(0);

            $table->json('asset_types')->nullable(); // e.g., [1,2] referencing AssetType enum
            $table->boolean('use_metric')->default(false);    // UI toggle

            $table->timestamps();
        });
        Schema::create('asset_spec_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('asset_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('asset_spec_definition_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('value_number', 12, 4)->nullable();
            $table->string('value_text')->nullable();
            $table->boolean('value_boolean')->nullable();

            $table->string('unit')->nullable(); // override if needed

            $table->timestamps();

            $table->unique(['asset_id', 'asset_spec_definition_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_spec_definitions');
    }
};
