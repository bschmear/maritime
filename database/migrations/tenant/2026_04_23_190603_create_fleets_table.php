<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
            $table->enum('fuel_type', ['diesel', 'gasoline', 'electric', 'hybrid', 'propane', 'other'])->nullable();

            // Relationships
            $table->foreignId('subsidiary_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            // Maintenance
            $table->date('last_maintenance_at')->nullable();
            $table->date('next_maintenance_due_at')->nullable();
            $table->integer('maintenance_interval_days')->nullable();

            // Status
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');

            // Vehicle Info
            $table->string('vin')->nullable();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->year('year')->nullable();

            // Capacity / Specs
            $table->integer('weight_capacity')->nullable();
            $table->enum('weight_unit', ['lbs', 'kg'])->default('lbs');

            $table->integer('towing_capacity')->nullable();
            $table->integer('payload_capacity')->nullable();

            $table->integer('gvwr')->nullable(); // Gross Vehicle Weight Rating
            $table->integer('axle_count')->nullable();

            $table->json('specs')->nullable();

            // Usage
            $table->integer('mileage')->nullable();
            $table->integer('hours')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('fleets');
    }
};
