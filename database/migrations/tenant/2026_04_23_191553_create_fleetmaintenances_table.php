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
        Schema::create('maintenance_types', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->string('category')->nullable();
            $table->string('applies_to', 20)->default('all');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('fleet_maintenance_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fleet_id')
                ->constrained('fleets')
                ->cascadeOnDelete();

            $table->date('performed_at');
            $table->foreignId('type_id')
                ->nullable()
                ->constrained('maintenance_types')
                ->nullOnDelete();
            $table->decimal('cost', 10, 2)->nullable();

            $table->integer('mileage')->nullable();
            $table->integer('hours')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fleet_maintenance_logs');
        Schema::dropIfExists('maintenance_types');
    }
};
