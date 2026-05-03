<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_options', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('input_type');
            $table->boolean('is_required')->default(false);
            $table->boolean('allow_multiple')->default(false);
            $table->unsignedInteger('min_select')->nullable();
            $table->unsignedInteger('max_select')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('asset_option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_id')
                ->constrained('asset_options')
                ->cascadeOnDelete();
            $table->string('label');
            $table->string('value')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('color_hex')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('asset_option_make_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_id')
                ->constrained('asset_options')
                ->cascadeOnDelete();
            $table->foreignId('make_id')
                ->constrained('boat_make')
                ->cascadeOnDelete();
            $table->decimal('cost_override', 10, 2)->nullable();
            $table->decimal('price_override', 10, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['option_id', 'make_id']);
        });

        Schema::create('asset_option_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_id')
                ->constrained('asset_options')
                ->cascadeOnDelete();
            $table->foreignId('asset_id')
                ->constrained('assets')
                ->cascadeOnDelete();
            $table->foreignId('variant_id')
                ->nullable()
                ->constrained('asset_variants')
                ->cascadeOnDelete();
            $table->decimal('cost_override', 10, 2)->nullable();
            $table->decimal('price_override', 10, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['option_id', 'asset_id', 'variant_id'], 'asset_option_assignments_option_asset_variant_unique');
        });

        Schema::create('estimate_selected_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('estimate_line_item_id')
                ->nullable()
                ->constrained('estimate_line_items')
                ->cascadeOnDelete();
            $table->foreignId('option_id')
                ->constrained('asset_options')
                ->cascadeOnDelete();
            $table->foreignId('option_value_id')
                ->constrained('asset_option_values')
                ->cascadeOnDelete();
            $table->string('option_name');
            $table->string('value_label');
            $table->decimal('cost', 10, 2)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(
                ['estimate_id', 'estimate_line_item_id', 'option_id', 'option_value_id'],
                'estimate_selected_options_line_option_value_unique'
            );
        });

        Schema::create('customer_asset_spec_sheet_option_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_asset_spec_sheet_share_id')
                ->constrained('customer_asset_spec_sheet_shares')
                ->cascadeOnDelete();
            $table->foreignId('option_id')
                ->constrained('asset_options')
                ->cascadeOnDelete();
            $table->foreignId('option_value_id')
                ->constrained('asset_option_values')
                ->cascadeOnDelete();
            $table->string('option_name');
            $table->string('value_label');
            $table->decimal('cost', 10, 2)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(
                ['customer_asset_spec_sheet_share_id', 'option_id', 'option_value_id'],
                'spec_sheet_selections_share_option_value_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_asset_spec_sheet_option_selections');
        Schema::dropIfExists('estimate_selected_options');
        Schema::dropIfExists('asset_option_assignments');
        Schema::dropIfExists('asset_option_make_assignments');
        Schema::dropIfExists('asset_option_values');
        Schema::dropIfExists('asset_options');
    }
};
