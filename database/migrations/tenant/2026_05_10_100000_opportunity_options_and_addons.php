<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('asset_opportunity')) {
            return;
        }

        Schema::create('opportunity_asset_selected_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_opportunity_id')
                ->constrained('asset_opportunity')
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
                ['asset_opportunity_id', 'option_id', 'option_value_id'],
                'opp_asset_sel_opt_pivot_opt_val_unique'
            );
        });

        Schema::create('opportunity_asset_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_opportunity_id')
                ->constrained('asset_opportunity')
                ->cascadeOnDelete();
            $table->foreignId('addon_id')
                ->nullable()
                ->constrained('addons')
                ->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('inventory_item_opportunity')) {
            Schema::create('opportunity_inventory_addons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_item_opportunity_id')
                    ->constrained('inventory_item_opportunity')
                    ->cascadeOnDelete();
                $table->foreignId('addon_id')
                    ->nullable()
                    ->constrained('addons')
                    ->cascadeOnDelete();
                $table->string('name')->nullable();
                $table->decimal('price', 12, 2)->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_inventory_addons');
        Schema::dropIfExists('opportunity_asset_addons');
        Schema::dropIfExists('opportunity_asset_selected_options');
    }
};
