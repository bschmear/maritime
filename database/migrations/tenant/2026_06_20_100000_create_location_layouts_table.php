<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_layouts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('location_id')
                ->constrained('locations')
                ->cascadeOnDelete();

            $table->string('name')->nullable();

            $table->integer('width_ft');
            $table->integer('height_ft');

            $table->integer('grid_size')->default(1);
            $table->integer('scale')->default(10);

            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['location_id']);
        });

        Schema::create('location_layout_units', function (Blueprint $table) {
            $table->id();

            $table->foreignId('location_layout_id')
                ->constrained('location_layouts')
                ->cascadeOnDelete();

            $table->foreignId('asset_unit_id')
                ->constrained('asset_units')
                ->cascadeOnDelete();

            $table->boolean('include_in_layout')->default(false);

            $table->decimal('x', 8, 2)->default(0);
            $table->decimal('y', 8, 2)->default(0);

            $table->unsignedSmallInteger('rotation')->default(0);
            $table->integer('z_index')->default(0);

            $table->string('name')->nullable();
            $table->decimal('length_ft', 8, 2)->nullable();
            $table->decimal('width_ft', 8, 2)->nullable();
            $table->string('color')->nullable();

            $table->timestamps();

            $table->unique(['location_layout_id', 'asset_unit_id']);
            $table->index(['location_layout_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_layout_units');
        Schema::dropIfExists('location_layouts');
    }
};
