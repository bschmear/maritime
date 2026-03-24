<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {

            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('sequence')->unique();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->string('status')->default('active')->index();
            // active, won, lost, cancelled

            /*
            |--------------------------------------------------------------------------
            | Relationships
            |--------------------------------------------------------------------------
            */

            $table->foreignId('estimate_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('opportunity_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('subsidiary_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('location_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Deal Snapshot (VERY IMPORTANT)
            |--------------------------------------------------------------------------
            */

            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('billing_address_line1')->nullable();
            $table->string('billing_address_line2')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_state')->nullable();
            $table->string('billing_postal')->nullable();
            $table->string('billing_country')->nullable();
            $table->decimal('billing_latitude', 10, 7)->nullable();
            $table->decimal('billing_longitude', 10, 7)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Deal Details
            |--------------------------------------------------------------------------
            */

            $table->string('title')->nullable(); // e.g. "2021 Sea Ray Sale"
            $table->boolean('needs_contract')->default(true);
            $table->boolean('needs_delivery')->default(false);

            $table->decimal('subtotal', 12, 2)->nullable();
            $table->decimal('tax_total', 12, 2)->nullable();
            $table->decimal('total', 12, 2)->nullable();

            $table->decimal('tax_rate', 6, 3)->nullable(); // e.g. 5.500 (%)
            $table->string('tax_jurisdiction')->nullable(); // e.g. "CA", "NY", "FL", etc.
            $table->decimal('discount_total', 12, 2)->nullable();
            $table->decimal('fees_total', 12, 2)->nullable();

            $table->string('currency', 3)->default('USD');

            /*
            |--------------------------------------------------------------------------
            | Important Dates
            |--------------------------------------------------------------------------
            */

            $table->timestamp('closed_at')->nullable(); // when won/lost
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Loss Tracking (VERY useful for reporting)
            |--------------------------------------------------------------------------
            */

            $table->string('loss_reason_category')->nullable();
            $table->text('loss_reason')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Notes
            |--------------------------------------------------------------------------
            */

            $table->text('notes')->nullable();

            /*
            |--------------------------------------------------------------------------
            | System
            |--------------------------------------------------------------------------
            */

            $table->timestamps();
            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(['customer_id', 'status']);
            $table->index(['estimate_id']);
            $table->index(['opportunity_id']);
        });

        Schema::create('transaction_items', function (Blueprint $table) {

            $table->id();

            $table->foreignId('transaction_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('type')->nullable();

            $table->nullableMorphs('itemable');
            $table->foreignId('inventory_unit_id')->nullable();
            $table->foreignId('asset_unit_id')->nullable();

            $table->string('name');
            $table->text('description')->nullable();

            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0)->after('unit_price');

            $table->decimal('subtotal', 12, 2)->default(0);

            $table->boolean('taxable')->default(true);
            $table->decimal('tax_rate', 6, 3)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();

            $table->decimal('total', 12, 2)->nullable();

            $table->unsignedInteger('position')->default(0);

            $table->foreignId('estimate_item_id')
                ->nullable()
                ->constrained('estimate_line_items')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['transaction_id', 'position']);
        });

        Schema::create('transaction_item_addon', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_item_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('addon_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name')->nullable(); // For custom typed-in add-ons
            $table->decimal('price', 12, 2)->nullable(); // Override price or set for custom
            $table->unsignedInteger('quantity')->default(1);
            $table->boolean('taxable')->default(true);
            $table->decimal('tax_rate', 6, 3)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // e.g., color, electronics, other configs

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_item_addon');
        Schema::dropIfExists('transaction_items');
        Schema::dropIfExists('transactions');
    }
};
