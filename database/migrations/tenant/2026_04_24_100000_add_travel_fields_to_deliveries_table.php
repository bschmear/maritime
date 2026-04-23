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
            if (! Schema::hasColumn('deliveries', 'time_to_leave_by')) {
                $table->timestamp('time_to_leave_by')->nullable()->after('estimated_arrival_at');
            }
            if (! Schema::hasColumn('deliveries', 'estimated_travel_duration_seconds')) {
                $table->unsignedInteger('estimated_travel_duration_seconds')->nullable()->after('time_to_leave_by');
            }
            if (! Schema::hasColumn('deliveries', 'en_route_at')) {
                $table->timestamp('en_route_at')->nullable()->after('estimated_travel_duration_seconds');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('deliveries', 'en_route_at')) {
                $table->dropColumn('en_route_at');
            }
            if (Schema::hasColumn('deliveries', 'estimated_travel_duration_seconds')) {
                $table->dropColumn('estimated_travel_duration_seconds');
            }
            if (Schema::hasColumn('deliveries', 'time_to_leave_by')) {
                $table->dropColumn('time_to_leave_by');
            }
        });
    }
};
