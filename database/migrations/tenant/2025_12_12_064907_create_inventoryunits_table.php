<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_units', function (Blueprint $table) {
            $table->id();

            // Relation to master inventory item
            $table->foreignId('inventory_item_id')
                ->constrained()
                ->cascadeOnDelete();

            /**
             * --- Identification ---
             * Boats may have hull IDs or serials.
             * Non-boats (parts/accessories) may have SKU or batch numbers.
             */
            $table->string('serial_number')->nullable();
            $table->string('hull_id')->nullable(); // boats only
            $table->string('sku')->nullable();     // non-boat inventory
            $table->string('batch_number')->nullable();

            /**
             * --- Quantity Handling ---
             * For parts/accessories you may have 10 identical items instead of individual units.
             */
            $table->unsignedInteger('quantity')->default(1); // flexible for both boats (1) and stock items

            /**
             * --- Condition Tracking ---
             */
            $table->unsignedInteger('condition')->default(1);

            /**
             * --- Status ---
             */
            $table->unsignedInteger('status')->default(1);

            /**
             * --- Item-specific details ---
             */
            $table->unsignedInteger('engine_hours')->nullable(); // use integer instead of string

            /**
             * --- Pricing Information per Unit ---
             */
            $table->decimal('cost', 12, 2)->nullable();
            $table->decimal('asking_price', 12, 2)->nullable();
            $table->json('price_history')->nullable();

            /**
             * --- Vendor / Consignment Info ---
             */
            $table->foreignId('vendor_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('owner_name')->nullable();

            /**
             * --- Location Assignment ---
             */
            $table->foreignId('location_id')->nullable()
                ->constrained()->nullOnDelete();

            /**
             * --- Flags / Notes ---
             */
            $table->boolean('inactive')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_units');
    }
};
