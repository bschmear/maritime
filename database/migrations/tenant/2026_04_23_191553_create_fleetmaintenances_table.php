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

        Schema::create('fleet_maintenance_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fleet_id')
                ->constrained('fleets')
                ->cascadeOnDelete();

            // Maintenance details
            $table->date('performed_at');
            $table->string('type')->nullable(); // oil change, tires, repair, etc
            $table->decimal('cost', 10, 2)->nullable();

            // Tracking usage at time of service
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
    }
};
