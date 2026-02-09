<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();

            // Core identity
            $table->unsignedTinyInteger('type'); // enum AssetType
            $table->string('display_name');
            $table->string('slug')->nullable();
            $table->boolean('inactive')->default(false);

            // Manufacturer / model info
            $table->unsignedBigInteger('make_id')->nullable();
            $table->string('model')->nullable();
            $table->string('year')->nullable();

            // Specifications (type-dependent)
            $table->string('length')->nullable();
            $table->string('beam')->nullable();
            $table->unsignedInteger('persons')->nullable();
            $table->unsignedInteger('minimum_power')->nullable();
            $table->unsignedInteger('maximum_power')->nullable();
            $table->string('fuel_tank')->nullable();
            $table->string('engine_shaft')->nullable();
            $table->string('water_tank')->nullable();
            $table->string('category')->nullable();
            $table->text('engine_details')->nullable();

            // Pricing defaults (model-level)
            $table->decimal('default_cost', 12, 2)->nullable();
            $table->decimal('default_price', 12, 2)->nullable();

            // Flexible extension
            $table->json('attributes')->nullable();

            // Description
            $table->text('description')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['type']);
            $table->index(['inactive']);
            $table->index(['make_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
