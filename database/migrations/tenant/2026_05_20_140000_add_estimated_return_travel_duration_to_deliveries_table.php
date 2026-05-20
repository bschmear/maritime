<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('deliveries')) {
            return;
        }

        if (! Schema::hasColumn('deliveries', 'estimated_return_travel_duration_seconds')) {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->unsignedInteger('estimated_return_travel_duration_seconds')
                    ->nullable()
                    ->after('estimated_travel_duration_seconds');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('deliveries')) {
            return;
        }

        if (Schema::hasColumn('deliveries', 'estimated_return_travel_duration_seconds')) {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->dropColumn('estimated_return_travel_duration_seconds');
            });
        }
    }
};
