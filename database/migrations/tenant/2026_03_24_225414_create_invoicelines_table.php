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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
        
            $table->unsignedBigInteger('invoice_id')->index();
        
            // Optional back-reference
            $table->unsignedBigInteger('transaction_item_id')->nullable();
        
            // Snapshot data
            $table->string('name');
            $table->text('description')->nullable();
        
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
        
            $table->decimal('subtotal', 12, 2)->default(0);
        
            $table->boolean('taxable')->default(true);
            $table->decimal('tax_rate', 6, 3)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();
        
            $table->decimal('total', 12, 2)->default(0);
        
            $table->integer('position')->default(0);
        
            // Keep polymorphic reference if needed
            $table->string('itemable_type')->nullable();
            $table->unsignedBigInteger('itemable_id')->nullable();
        
            $table->timestamps();
        
            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
