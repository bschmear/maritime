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
        Schema::create('boat_show_event_assets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('boat_show_event_id')
                ->constrained('boat_show_events')
                ->cascadeOnDelete();

            $table->foreignId('asset_id')
                ->constrained('assets')
                ->cascadeOnDelete();

            $table->foreignId('asset_unit_id')
                ->nullable()
                ->constrained('asset_units')
                ->nullOnDelete();

            $table->boolean('include_in_layout')->default(false);

            // Floor-plan placement (feet / degrees)
            $table->decimal('x', 8, 2)->default(0);
            $table->decimal('y', 8, 2)->default(0);

            $table->unsignedSmallInteger('rotation')->default(0);
            $table->integer('z_index')->default(0);

            $table->string('name')->nullable();
            $table->decimal('length_ft', 8, 2)->nullable();
            $table->decimal('width_ft', 8, 2)->nullable();
            $table->string('color')->nullable();

            $table->timestamps();

            $table->unique(['boat_show_event_id', 'asset_id']);
            $table->index(['boat_show_event_id']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boat_show_event_assets');
    }
};
