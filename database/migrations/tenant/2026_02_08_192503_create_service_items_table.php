<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_items', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('display_name');
            $table->string('code', 50)->nullable()->index(); // optional internal code
            $table->text('description')->nullable();

            // Classification
            $table->unsignedTinyInteger('billing_type')->default(1);
            // 1 = hourly | 2 = flat | 3 = quantity

            // Pricing
            $table->decimal('default_rate', 10, 2)->nullable(); // hourly or flat
            $table->decimal('default_cost', 10, 2)->nullable(); // internal cost
            $table->boolean('taxable')->default(false);

            // Behavior flags
            $table->boolean('billable')->default(true);
            $table->boolean('warranty_eligible')->default(false);
            $table->boolean('inactive')->default(false);

            // Scope
            $table->unsignedBigInteger('subsidiary_id')->nullable();
            // null = global service item, otherwise subsidiary-specific

            // Meta
            $table->json('attributes')->nullable(); // future custom fields
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['inactive', 'display_name']);
            $table->index(['billing_type']);
            // $table->index(['subsidiary_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_items');
    }
};
