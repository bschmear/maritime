<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'inventory';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('assets')) {
            return;
        }

        Schema::connection($this->connection)->create('assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('type');
            $table->string('display_name');
            $table->string('slug')->nullable();
            $table->boolean('inactive')->default(false);
            $table->foreignId('make_id')->nullable()->constrained('boat_make')->nullOnDelete();
            $table->string('model')->nullable();
            $table->string('year')->nullable();
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
            $table->json('attributes')->nullable();
            $table->text('description')->nullable();
            $table->decimal('default_cost', 12, 2)->nullable();
            $table->decimal('default_price', 12, 2)->nullable();
            $table->boolean('has_variants')->default(false);
            $table->timestamps();

            $table->unique(['make_id', 'slug']);
            $table->index(['type']);
            $table->index(['inactive']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('assets');
    }
};
