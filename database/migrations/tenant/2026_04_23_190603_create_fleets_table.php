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
        Schema::create('fleets', function (Blueprint $table) {
            $table->id();

            // Core
            $table->string('display_name')->nullable();
            $table->enum('type', ['truck', 'trailer']);
            $table->string('license_plate')->nullable();

            // Specs
            $table->string('size')->nullable();

            $table->foreignId('subsidiary_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            $table->date('last_maintenance_at')->nullable();
            $table->date('next_maintenance_due_at')->nullable();
            $table->integer('maintenance_interval_days')->nullable();

            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');

            $table->string('vin')->nullable();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->year('year')->nullable();

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
        Schema::dropIfExists('fleets');
    }
};
