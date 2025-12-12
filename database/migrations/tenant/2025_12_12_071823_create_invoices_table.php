<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // Core fields
            $table->string('invoice_number')->unique();
            $table->string('customer');
            $table->string('reference')->nullable(); // Reference of the invoice
            $table->string('terms')->nullable();     // Object / payment terms
            $table->boolean('vat_applicable')->default(false);

            // Payment & currency
            $table->unsignedInteger('payment_condition')->nullable();
            $table->unsignedInteger('currency')->default(1);

            // Dates
            $table->date('issue_date');
            $table->date('due_date');
            $table->date('delivery_date')->nullable();

            // Additional info
            $table->text('additional_info')->nullable();
            $table->json('attachments')->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
