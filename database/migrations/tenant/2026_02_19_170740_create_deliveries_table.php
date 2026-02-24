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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')
                  ->unique();
            $table->unsignedBigInteger('sequence')->unique();

            // Ownership
            $table->foreignId('customer_id'); // ->constrained()->cascadeOnDelete();
            $table->foreignId('asset_unit_id'); // ->constrained()->cascadeOnDelete();

            // Optional relationships
            $table->foreignId('work_order_id')->nullable(); // ->constrained()->nullOnDelete();

            // Relationships
            $table->unsignedBigInteger('subsidiary_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();

            // Scheduling
            $table->dateTime('scheduled_at')->index();
            $table->dateTime('estimated_arrival_at')->nullable();
            $table->dateTime('delivered_at')->nullable();

            // Status tracking
            $table->string('status')->default('scheduled')->index();
            // scheduled, en_route, delivered, cancelled, rescheduled

            // Personnel
            $table->foreignId('technician_id')->nullable(); // ->constrained('users')->nullOnDelete();

            // Delivery receipt fields
            $table->string('recipient_name')->nullable();
            $table->text('signature_path')->nullable();
            $table->dateTime('signed_at')->nullable();


            $table->string('signed_ip')->nullable();
            $table->string('signed_user_agent')->nullable();
            $table->string('signature_file')->nullable();
            $table->string('signature_hash')->nullable();

            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();

            // Geo
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();


            // Notes
            $table->text('internal_notes')->nullable();
            $table->text('customer_notes')->nullable();

            $table->softDeletes();

            $table->timestamps();

            $table->index(['customer_id', 'asset_unit_id', 'status']);
            $table->index(['customer_id', 'asset_unit_id', 'scheduled_at']);
            $table->index(['customer_id', 'asset_unit_id', 'delivered_at']);
            $table->index(['customer_id', 'asset_unit_id', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
