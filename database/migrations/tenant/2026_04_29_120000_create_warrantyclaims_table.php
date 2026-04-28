<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warrantyclaims', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('vendor_id')
                ->nullable()
                ->after('id')
                ->constrained('vendors')
                ->nullOnDelete();
            $table->foreignId('work_order_id')
                ->nullable()
                ->constrained('work_orders')
                ->nullOnDelete();
            $table->foreignId('invoice_id')
                ->nullable()
                ->constrained('invoices')
                ->nullOnDelete();

            $table->string('claim_number')->nullable()->after('invoice_id');

            $table->string('status', 32)->default('draft')->after('claim_number');

            $table->decimal('total_amount', 10, 2)->default(0)->after('status');

            $table->timestamp('submitted_at')->nullable()->after('total_amount');
            $table->timestamp('approved_at')->nullable()->after('submitted_at');
            $table->timestamp('paid_at')->nullable()->after('approved_at');
            $table->timestamp('voided_at')->nullable()->after('paid_at');

            $table->text('rejection_reason')->nullable()->after('voided_at');
            $table->text('notes')->nullable()->after('rejection_reason');
            $table->timestamps();
        });

        Schema::create('warranty_claim_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warranty_claim_id')
                ->constrained('warrantyclaims')
                ->cascadeOnDelete();
            $table->foreignId('work_order_service_item_id')
                ->nullable()
                ->constrained('work_order_service_items')
                ->nullOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price', 10, 2);
            $table->decimal('cost', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_claim_line_items');

        Schema::dropIfExists('warrantyclaims');
    }
};
