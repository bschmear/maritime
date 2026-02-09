<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_units', function (Blueprint $table) {
            $table->id();

            /**
             * --- Parent Asset ---
             */
            $table->foreignId('asset_id')
                ->constrained()
                ->cascadeOnDelete();

            /**
             * --- Identification ---
             */
            $table->string('serial_number')->nullable()->index();
            $table->string('hin')->nullable()->unique(); // boats
            $table->string('sku')->nullable()->index(); // accessories / parts

            /**
             * --- Status & Condition ---
             */
            $table->unsignedTinyInteger('condition')->default(1);
            $table->unsignedTinyInteger('status')->default(1);
            $table->boolean('inactive')->default(false);

            /**
             * --- Ownership / Inventory Classification ---
             */
            $table->boolean('is_customer_owned')->default(false);
            $table->boolean('is_consignment')->default(false);

            /**
             * --- Usage Tracking ---
             */
            $table->unsignedInteger('engine_hours')->nullable();
            $table->timestamp('last_service_at')->nullable();

            /**
             * --- Warranty ---
             */
            $table->date('warranty_expires_at')->nullable();

            /**
             * --- Pricing (per unit) ---
             */
            $table->decimal('cost', 12, 2)->nullable();
            $table->decimal('asking_price', 12, 2)->nullable();
            $table->decimal('sold_price', 12, 2)->nullable();
            $table->json('price_history')->nullable();

            /**
             * --- Ownership / Assignment ---
             */
            $table->unsignedBigInteger('subsidiary_id')->nullable()->index();

            $table->foreignId('location_id')->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->unsignedBigInteger('customer_id')->nullable()->index();

            $table->foreignId('vendor_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /**
             * --- Lifecycle ---
             */
            $table->timestamp('in_service_at')->nullable();
            $table->timestamp('out_of_service_at')->nullable();
            $table->timestamp('sold_at')->nullable();

            /**
             * --- Flexible Data ---
             */
            $table->json('attributes')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            /**
             * --- Indexes ---
             */
            $table->index(['asset_id', 'status']);
            $table->index(['asset_id', 'inactive']);
            $table->index(['is_customer_owned']);
            $table->index(['is_consignment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_units');
    }
};
