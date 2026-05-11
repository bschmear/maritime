<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Greenfield cutover: replaces estimate_line_items, transaction_items, contract_line_items,
 * and opportunity pivot lines with a single polymorphic transaction_line_items table.
 *
 * No row-level data migration (tenant data may be cleared).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoice_items', 'transaction_item_id')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->renameColumn('transaction_item_id', 'transaction_line_item_id');
            });
        }

        Schema::dropIfExists('estimate_customer_option_signoffs');
        Schema::dropIfExists('estimate_selected_options');
        Schema::dropIfExists('estimate_line_item_addon');
        Schema::dropIfExists('transaction_item_addon');
        Schema::dropIfExists('transaction_items');
        Schema::dropIfExists('estimate_line_items');
        Schema::dropIfExists('contract_line_items');

        // Opportunity pivot lines remain until the Opportunity domain is migrated to morph parents.

        Schema::create('transaction_line_items', function (Blueprint $table) {
            $table->id();

            $table->nullableMorphs('parent');
            $table->nullableMorphs('itemable');

            $table->string('type')->nullable();

            $table->string('name')->nullable();
            $table->text('description')->nullable();

            $table->decimal('quantity', 10, 2)->default(1);

            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);

            $table->decimal('line_total', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->nullable();
            $table->decimal('total', 12, 2)->nullable();

            $table->unsignedInteger('position')->default(0);

            $table->foreignId('asset_variant_id')->nullable()->constrained('asset_variants')->nullOnDelete();
            $table->foreignId('asset_unit_id')->nullable()->constrained('asset_units')->nullOnDelete();
            $table->foreignId('inventory_unit_id')->nullable()->index();

            $table->boolean('taxable')->default(true);
            $table->decimal('tax_rate', 6, 3)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();

            $table->string('asset_options_fill_mode', 32)->default('staff');
            $table->timestamp('customer_asset_options_completed_at')->nullable();
            $table->string('customer_asset_options_signer_name')->nullable();
            $table->string('customer_asset_options_signer_ip', 45)->nullable();

            $table->foreignId('source_transaction_line_item_id')->nullable()->index();

            $table->timestamps();

            $table->index(['parent_type', 'parent_id', 'position'], 'tli_parent_position_idx');
        });

        Schema::table('transaction_line_items', function (Blueprint $table) {
            $table->foreign('source_transaction_line_item_id')
                ->references('id')
                ->on('transaction_line_items')
                ->nullOnDelete();
        });

        Schema::create('transaction_line_item_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_line_item_id')
                ->constrained('transaction_line_items')
                ->cascadeOnDelete();
            $table->foreignId('addon_id')->nullable()->constrained('addons')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('taxable')->default(true);
            $table->decimal('tax_rate', 6, 3)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('transaction_line_item_selected_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->nullable()->constrained('estimates')->cascadeOnDelete();
            $table->foreignId('transaction_line_item_id')
                ->constrained('transaction_line_items')
                ->cascadeOnDelete();
            $table->foreignId('option_id')->constrained('asset_options')->cascadeOnDelete();
            $table->foreignId('option_value_id')->constrained('asset_option_values')->cascadeOnDelete();
            $table->string('option_name');
            $table->string('value_label');
            $table->decimal('cost', 10, 2)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(
                ['transaction_line_item_id', 'option_id', 'option_value_id'],
                'tli_sel_line_opt_val_unique'
            );
        });

        Schema::create('estimate_customer_option_signoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->constrained('estimates')->cascadeOnDelete();
            $table->foreignId('transaction_line_item_id')->constrained('transaction_line_items')->cascadeOnDelete();
            $table->string('signer_name');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimate_customer_option_signoffs');
        Schema::dropIfExists('transaction_line_item_selected_options');
        Schema::dropIfExists('transaction_line_item_addons');
        Schema::dropIfExists('transaction_line_items');

        if (Schema::hasColumn('invoice_items', 'transaction_line_item_id')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->renameColumn('transaction_line_item_id', 'transaction_item_id');
            });
        }

        // Legacy tables are not recreated here; run full migrate:fresh to restore prior schema.
    }
};
