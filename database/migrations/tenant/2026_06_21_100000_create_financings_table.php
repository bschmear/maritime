<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('financings')) {
            return;
        }

        Schema::create('financings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('sequence')->unique();

            $table->foreignId('asset_unit_id')
                ->nullable()
                ->constrained('asset_units')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('vendor_id')
                ->nullable()
                ->constrained('vendors')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('dealer_name')->nullable();
            $table->string('dealer_cin')->nullable();

            $table->enum('status', ['active', 'paid_off'])->default('active')->index();

            $table->decimal('principal_amount', 12, 2)->default(0);
            $table->decimal('current_balance', 12, 2)->default(0);
            $table->decimal('annual_interest_rate', 8, 4)->nullable();
            $table->unsignedSmallInteger('loan_term_months')->nullable();

            $table->date('financed_at')->nullable();
            $table->date('interest_start_date')->nullable();
            $table->date('next_payment_date')->nullable();
            $table->decimal('monthly_payment_amount', 12, 2)->nullable();

            $table->string('lender_status')->nullable();
            $table->unsignedInteger('aging_days')->nullable();
            $table->decimal('curtailment_current_due', 12, 2)->nullable();
            $table->decimal('past_due_curtailment', 12, 2)->nullable();

            $table->string('supplier_name')->nullable();
            $table->string('supplier_cin')->nullable();
            $table->string('lender_invoice_number')->nullable();
            $table->string('model_year')->nullable();
            $table->string('model_number')->nullable();
            $table->string('serial_vin')->nullable();

            $table->unsignedInteger('days_alert_threshold')->nullable();
            $table->decimal('interest_alert_threshold', 12, 2)->nullable();
            $table->timestamp('alert_notified_at')->nullable();
            $table->timestamp('last_imported_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('asset_unit_id');
            $table->index('vendor_id');
            $table->index('serial_vin');
            $table->index(['status', 'asset_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financings');
    }
};
