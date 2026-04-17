<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('deliveries', 'transaction_id')) {
                $table->foreignId('transaction_id')
                    ->nullable()
                    ->after('work_order_id')
                    ->constrained('transactions')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('deliveries', 'delivery_location_id')) {
                $table->foreignId('delivery_location_id')
                    ->nullable()
                    ->after('location_id')
                    ->constrained('delivery_locations')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('deliveries', 'delivery_to_type')) {
                // contact_address | delivery_location | custom
                $table->string('delivery_to_type')->default('custom')->after('delivery_location_id');
            }

            if (! Schema::hasColumn('deliveries', 'contact_address_id')) {
                // Optional reference to which contact address was used (snapshot still stored on deliveries.*).
                $table->unsignedBigInteger('contact_address_id')->nullable()->after('delivery_to_type');
            }
        });

        // Relax the legacy NOT NULL on asset_unit_id so a delivery can own its assets via delivery_items.
        if (Schema::hasColumn('deliveries', 'asset_unit_id')) {
            try {
                Schema::table('deliveries', function (Blueprint $table) {
                    $table->unsignedBigInteger('asset_unit_id')->nullable()->change();
                });
            } catch (\Throwable $e) {
                // doctrine/dbal may not be installed on some envs -- ignore if we can't alter in-place.
            }
        }
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('deliveries', 'contact_address_id')) {
                $table->dropColumn('contact_address_id');
            }
            if (Schema::hasColumn('deliveries', 'delivery_to_type')) {
                $table->dropColumn('delivery_to_type');
            }
            if (Schema::hasColumn('deliveries', 'delivery_location_id')) {
                $table->dropConstrainedForeignId('delivery_location_id');
            }
            if (Schema::hasColumn('deliveries', 'transaction_id')) {
                $table->dropConstrainedForeignId('transaction_id');
            }
        });
    }
};
