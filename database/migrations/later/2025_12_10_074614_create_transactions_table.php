<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // Who created this transaction (user ID in central users table)
            $table->unsignedBigInteger('created_by')->nullable();

            // Quick human-readable name for listing (e.g. "Order #1001")
            $table->string('display_name')->nullable();

            // Sales rep handling the transaction (user ID)
            $table->unsignedBigInteger('sales_rep')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();

            // Transaction details
            $table->string('transaction_number')->nullable()->unique();
            $table->dateTime('transaction_date')->nullable();

            // Monetary values
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->nullable();

            // Payment details
            $table->string('payment_method')->nullable();   // credit, cash, loan, finance, ACH
            $table->string('payment_status')->default('pending'); // paid, unpaid, refunded, partial

            // Related entities (optional)
            // $table->unsignedBigInteger('entity_id')->nullable();
            // $table->string('entity_type')->nullable();

            // Notes & metadata
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
