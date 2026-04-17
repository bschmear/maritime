<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delivery_items')) {
            return;
        }

        Schema::create('delivery_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('delivery_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('type')->nullable();

            $table->nullableMorphs('itemable');

            $table->foreignId('asset_variant_id')
                ->nullable()
                ->constrained('asset_variants')
                ->nullOnDelete();

            $table->foreignId('asset_unit_id')
                ->nullable()
                ->constrained('asset_units')
                ->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);

            $table->decimal('subtotal', 12, 2)->default(0);

            $table->boolean('taxable')->default(true);
            $table->decimal('tax_rate', 6, 3)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();

            $table->decimal('total', 12, 2)->nullable();

            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['delivery_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_items');
    }
};
