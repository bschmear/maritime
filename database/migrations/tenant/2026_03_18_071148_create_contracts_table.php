<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();

            // Tenant linkage
            $table->foreignId('account_settings_id')->index();

            // Link to customer/contact
            $table->foreignId('contact_id')->index();

            // Optional link to originating estimate
            $table->unsignedBigInteger('estimate_id')->nullable()->index();
            $table->foreign('estimate_id')
                  ->references('id')
                  ->on('estimates')
                  ->onDelete('set null');

            // Core contract info
            $table->string('contract_number')->nullable()->unique();
            $table->string('status')->default('draft')->index();
            $table->string('payment_status')->default('pending')->index();

            // Amount and currency
            $table->decimal('total_amount', 10, 2);
            $table->string('currency')->default('USD');

            // Terms and details
            $table->text('payment_terms')->nullable();
            $table->text('delivery_terms')->nullable();
            $table->text('notes')->nullable();

            // Signature info
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_by_name')->nullable();
            $table->string('signed_by_email')->nullable();

            // Document / e-signature references
            $table->string('document_url')->nullable();
            $table->string('docusign_envelope_id')->nullable();

            $table->string('billing_address_line1')->nullable();
            $table->string('billing_address_line2')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_state')->nullable();
            $table->string('billing_postal')->nullable();
            $table->string('billing_country')->nullable();
            $table->decimal('billing_latitude', 10, 7)->nullable();
            $table->decimal('billing_longitude', 10, 7)->nullable();

            $table->boolean('signature_required')->default(true);
        
            $table->foreignId('paper_signature_document_id')
                ->nullable()
                ->constrained('documents')
                ->nullOnDelete();
        
            $table->timestamp('signed_at')->nullable();
        
            $table->string('signed_name')->nullable();
            $table->string('signed_email')->nullable();
        
            $table->string('signed_ip')->nullable();
            $table->string('signed_user_agent')->nullable();
        
            $table->string('signature_file')->nullable();
        
            $table->string('signature_hash')->nullable()
                ->comment('Hash of contract data at time of signing');


            // Metadata / provider-specific info
            $table->json('meta')->nullable();

            // Timestamps & soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['account_settings_id', 'status']);
            $table->index(['account_settings_id', 'payment_status']);
        });

        Schema::table('account_settings', function (Blueprint $table) {
            $table->text('default_payment_terms')
                ->nullable()
                ->default('Payment is due as specified in the contract. Please remit promptly.')
                ->after('service_ticket_ack_text');

            $table->text('default_delivery_terms')
                ->nullable()
                ->default('Delivery will be scheduled according to contract terms. Customer will be notified in advance.')
                ->after('default_payment_terms');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropColumn('default_payment_terms');
            $table->dropColumn('default_delivery_terms');
        });
    }
};
