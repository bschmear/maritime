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
            $table->unsignedInteger('work_order_number')->nullable()->unique();

            // Relationships
            $table->unsignedBigInteger('subsidiary_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('inventory_item_id')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();

            // Classification
            $table->unsignedSmallInteger('status')->default(0);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->unsignedSmallInteger('type')->default(0);

            // Scheduling
            $table->timestamp('scheduled_start_at')->nullable();
            $table->timestamp('scheduled_end_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('due_at')->nullable();

            // Core details
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('customer_notes')->nullable();

            // Time & cost tracking
            $table->decimal('estimated_hours', 6, 2)->nullable();
            $table->decimal('actual_hours', 6, 2)->nullable();
            $table->decimal('labor_cost', 10, 2)->nullable()->default(0);
            $table->decimal('parts_cost', 10, 2)->nullable()->default(0);
            $table->decimal('total_cost', 10, 2)->nullable()->default(0);

            // Billing flags
            $table->boolean('billable')->default(true);
            $table->boolean('draft')->default(false);
            $table->boolean('warranty')->default(false);

            // Custom fields
            $table->json('attributes')->nullable();

            // Audit
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['status', 'priority']);
            $table->index('assigned_user_id');
            $table->index('customer_id');
            $table->index('subsidiary_id');
            $table->index('inventory_item_id');
            $table->index('due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
