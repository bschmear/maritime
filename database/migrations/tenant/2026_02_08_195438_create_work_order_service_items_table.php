<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_service_items', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->unsignedBigInteger('work_order_id');
            $table->unsignedBigInteger('service_item_id')->nullable();

            // Snapshot fields (do not rely on service_item changing later)
            $table->string('display_name');
            $table->text('description')->nullable();

            // Quantity & time
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->decimal('actual_hours', 8, 2)->nullable();

            // Pricing
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->decimal('total_price', 10, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->nullable();

            // Billing flags
            $table->boolean('billable')->default(true);
            $table->boolean('warranty')->default(false);

            // Ordering / grouping
            $table->unsignedInteger('sort_order')->default(0);

            // Meta
            $table->json('attributes')->nullable();
            $table->boolean('inactive')->default(false);

            $table->timestamps();

            // Indexes
            $table->index('work_order_id');
            $table->index('service_item_id');
            $table->index(['work_order_id', 'inactive']);

            $table->foreign('work_order_id')->references('id')->on('work_orders');
            $table->foreign('service_item_id')->references('id')->on('service_items');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_service_items');
    }
};
