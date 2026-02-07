<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();

            // Human-friendly identifier
            $table->string('work_order_number')->unique();

            // Relationships
            $table->unsignedBigInteger('subsidiary_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('inventory_unit_id')->nullable(); // boat/unit
            $table->unsignedBigInteger('assigned_user_id')->nullable(); // technician
            $table->unsignedBigInteger('location_id')->nullable();

            // Status & priority
            $table->unsignedSmallInteger('status')->default(0);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->unsignedSmallInteger('type')->default(0);

            // Dates
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            // Descriptions
            $table->string('summary')->nullable();
            $table->text('description')->nullable();
            $table->text('internal_notes')->nullable();

            // Operational flags
            $table->boolean('customer_approved')->default(false);
            $table->boolean('warranty')->default(false);
            $table->boolean('inactive')->default(false);

            // Audit
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['status', 'priority']);
            $table->index(['assigned_user_id']);
            $table->index(['customer_id']);
            $table->index(['subsidiary_id']);
            $table->index(['inventory_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
