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
            $table->unsignedInteger('type')->default(1); // boat, part, accessory, service
            $table->string('sku')->nullable()->unique();
            $table->string('name');
            $table->string('slug')->unique();

            // Boat-specific attributes (optional)
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('year')->nullable();
            $table->string('length')->nullable();
            $table->string('engine_details')->nullable();

            // Generic attributes
            $table->json('attributes')->nullable(); // flexible for parts, accessories

            // Media
            $table->json('photos')->nullable();
            $table->json('videos')->nullable();

            // Pricing defaults (per model)
            $table->decimal('default_cost', 12, 2)->nullable();
            $table->decimal('default_price', 12, 2)->nullable();

            // Text
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
