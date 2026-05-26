<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('deliveries', 'customer_arrived_notified_at')) {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->timestamp('customer_arrived_notified_at')->nullable()->after('en_route_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('deliveries', 'customer_arrived_notified_at')) {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->dropColumn('customer_arrived_notified_at');
            });
        }
    }
};
