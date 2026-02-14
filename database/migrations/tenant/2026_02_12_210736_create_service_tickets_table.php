<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Service Tickets Table
        |--------------------------------------------------------------------------
        */
        Schema::create('service_tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('service_ticket_number')->unique();
            $table->string('display_name')->nullable();

            // Relationships
            $table->foreignId('subsidiary_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('asset_unit_id')->nullable();

            // Operational Info
            $table->boolean('expedite')->default(false);
            $table->date('pickup_delivery_requested_at')->nullable();
            $table->integer('status')->default(1); // 1=Open, 2=Diagnosing, etc.

            // Repair Description
            $table->text('repair_description')->nullable();
            $table->text('internal_notes')->nullable();

            // Estimate Section (Customer-Facing)
            $table->decimal('estimated_labor_hours', 8, 2)->nullable();
            $table->decimal('estimated_labor_amount', 12, 2)->nullable();
            $table->decimal('estimated_parts_amount', 12, 2)->nullable();

            $table->decimal('estimated_subtotal', 12, 2)->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->decimal('estimated_tax', 12, 2)->nullable();
            $table->decimal('estimated_total', 12, 2)->nullable();

            // Authorization / Signature
            $table->text('customer_signature')->nullable(); // base64 or file path
            $table->unsignedTinyInteger('signature_method')->nullable()
                ->comment('1 = Digital, 2 = Paper, 3 = Verbal, 4 = Email approval');
            $table->boolean('approved')->default(false);
            $table->foreignId('paper_signature_document_id')
                ->nullable()
                ->constrained('documents')
                ->nullOnDelete();

            $table->timestamp('signed_at')->nullable();
            $table->string('signed_ip')->nullable();
            $table->string('signed_user_agent')->nullable();
            $table->string('signature_file')->nullable();
            $table->string('signature_hash')->nullable()
                ->comment('Hash of ticket data at time of signing');

            $table->boolean('requires_reauthorization')->default(false);

            $table->decimal('revised_estimated_total', 12, 2)->nullable();

            $table->text('reauthorization_signature')->nullable();
            $table->timestamp('reauthorized_at')->nullable();
            $table->string('reauthorized_ip')->nullable();
            $table->string('reauthorized_user_agent')->nullable();


            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Service Ticket Service Items Table
        |--------------------------------------------------------------------------
        */
        Schema::create('service_ticket_service_items', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('service_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_item_id')->nullable()->constrained()->nullOnDelete();

            // Snapshot fields
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();

            // Quantity & Time
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('estimated_hours', 8, 2)->nullable();

            // Pricing
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->decimal('total_price', 12, 2)->default(0); // auto-calc in model
            $table->decimal('total_cost', 12, 2)->nullable();

            // Billing
            $table->unsignedTinyInteger('billing_type')->default(1);
            $table->boolean('billable')->default(true);
            $table->boolean('warranty')->default(false);

            $table->unsignedInteger('sort_order')->default(0);
            $table->json('attributes')->nullable();
            $table->boolean('inactive')->default(false);

            $table->timestamps();

            $table->index('service_ticket_id');
            $table->index('service_item_id');
            $table->index(['service_ticket_id', 'inactive']);
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('service_ticket_id')
                ->nullable()
                ->after('id')
                ->constrained('service_tickets')
                ->cascadeOnDelete();

            $table->index('service_ticket_id');
        });

        Schema::table('account_settings', function (Blueprint $table) {
            $table->decimal('estimate_threshold_percent', 5, 2)
                ->default(20.00)
                ->after('settings');

            $table->text('service_ticket_ack_text')
                ->nullable()
                ->after('estimate_threshold_percent')
                ->comment('Customer acknowledgement text for service tickets/estimates');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['service_ticket_id']);
            $table->dropIndex(['service_ticket_id']);
            $table->dropColumn('service_ticket_id');
        });
        Schema::dropIfExists('service_ticket_service_items');
        Schema::dropIfExists('service_tickets');
        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropColumn('estimate_threshold_percent');
            $table->dropColumn('service_ticket_ack_text');
        });
    }
};
