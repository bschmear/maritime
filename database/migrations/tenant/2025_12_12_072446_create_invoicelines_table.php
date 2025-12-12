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
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->enum('discount_type', ['percent', 'fixed'])->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable()->default(0);
            $table->decimal('tax_rate', 5, 2)->nullable()->default(0);
            $table->decimal('tax_amount', 12, 2)->nullable()->default(0);
            $table->decimal('total', 12, 2);
            $table->unsignedInteger('position')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoicelines');
    }
};
