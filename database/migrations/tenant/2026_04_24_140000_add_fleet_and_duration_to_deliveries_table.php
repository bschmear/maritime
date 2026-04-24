<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('deliveries', 'fleet_truck_id')) {
                $table->foreignId('fleet_truck_id')
                    ->nullable()
                    ->after('location_id')
                    ->constrained('fleets')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('deliveries', 'fleet_trailer_id')) {
                $table->foreignId('fleet_trailer_id')
                    ->nullable()
                    ->after('fleet_truck_id')
                    ->constrained('fleets')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('deliveries', 'delivery_duration_minutes')) {
                $table->unsignedSmallInteger('delivery_duration_minutes')
                    ->nullable()
                    ->after('estimated_travel_duration_seconds');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('deliveries', 'delivery_duration_minutes')) {
                $table->dropColumn('delivery_duration_minutes');
            }
            if (Schema::hasColumn('deliveries', 'fleet_trailer_id')) {
                $table->dropConstrainedForeignId('fleet_trailer_id');
            }
            if (Schema::hasColumn('deliveries', 'fleet_truck_id')) {
                $table->dropConstrainedForeignId('fleet_truck_id');
            }
        });
    }
};
