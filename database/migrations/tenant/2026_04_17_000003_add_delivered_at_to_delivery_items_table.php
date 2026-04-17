<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_items', 'delivered_at')) {
                $table->dateTime('delivered_at')->nullable()->after('position');
            }

            if (! Schema::hasColumn('delivery_items', 'delivered_by_user_id')) {
                $table->foreignId('delivered_by_user_id')
                    ->nullable()
                    ->after('delivered_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('delivery_items', 'serial_number_snapshot')) {
                // Captured at sync time from asset_units.serial_number so the printed doc stays stable.
                $table->string('serial_number_snapshot')->nullable()->after('delivered_by_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_items', 'serial_number_snapshot')) {
                $table->dropColumn('serial_number_snapshot');
            }
            if (Schema::hasColumn('delivery_items', 'delivered_by_user_id')) {
                $table->dropConstrainedForeignId('delivered_by_user_id');
            }
            if (Schema::hasColumn('delivery_items', 'delivered_at')) {
                $table->dropColumn('delivered_at');
            }
        });
    }
};
