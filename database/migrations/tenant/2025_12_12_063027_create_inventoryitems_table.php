<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();

            // General item info
            $table->unsignedInteger('type')->default(1); // 1=boat, 2=part, 3=accessory, 4=service
            $table->string('sku')->nullable()->unique();
            $table->string('display_name');
            $table->string('slug')->unique();
            $table->boolean('inactive')->default(false);

            // Boat-specific attributes
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('year')->nullable();
            $table->string('length')->nullable();
            $table->string('beam')->nullable();
            $table->string('tube_diameter')->nullable();
            $table->unsignedInteger('tubes_compartment')->nullable();
            $table->unsignedInteger('persons')->nullable();
            $table->string('weight')->nullable(); // store as string to keep "1,213 lbs"
            $table->unsignedInteger('minimum_power')->nullable(); // HP
            $table->unsignedInteger('maximum_power')->nullable(); // HP
            $table->string('engine_shaft')->nullable(); // e.g., "1L"
            $table->string('fuel_tank')->nullable(); // e.g., "28 Gal"
            $table->string('water_tank')->nullable(); // e.g., "12 Gal"
            $table->string('category')->nullable();

            // Generic attributes for parts, accessories, or flexible use
            $table->json('attributes')->nullable();

            // Media
            $table->json('photos')->nullable();
            $table->json('videos')->nullable();

            // Pricing defaults (per model)
            $table->decimal('default_cost', 12, 2)->nullable();
            $table->decimal('default_price', 12, 2)->nullable();

            // Text description
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
