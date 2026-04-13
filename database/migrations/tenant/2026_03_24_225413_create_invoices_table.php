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
        
            // Relationships
            $table->unsignedBigInteger('transaction_id')->nullable()->index();
            $table->unsignedBigInteger('contract_id')->nullable()->index();
            $table->foreignId('contact_id')
                ->constrained('contacts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        
            // Identity
            $table->uuid('uuid')->unique();
            $table->bigInteger('sequence')->unique(); // INV-1001 style
        
            // Status
            $table->enum('status', [
                'draft',
                'sent',
                'viewed',
                'partial',
                'paid',
                'void'
            ])->default('draft')->index();
        
            // Financials (snapshot)
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('fees_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
        
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('amount_due', 12, 2)->default(0);
        
            $table->string('currency', 3)->default('USD');
        
            // Terms
            $table->string('payment_term')->default('due_on_receipt'); // reuse your enum idea
            $table->timestamp('due_at')->nullable();
        
            // Customer snapshot (DO THIS - super important)
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
        
            // Billing snapshot
            $table->string('billing_address_line1')->nullable();
            $table->string('billing_address_line2')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_state')->nullable();
            $table->string('billing_postal')->nullable();
            $table->string('billing_country')->nullable();
        
            // Meta / notes
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
        
            // Timestamps
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
        
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
