<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_feature_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('opportunity_feature_requests', 'addon_staff_decisions')) {
                $table->json('addon_staff_decisions')->nullable()->after('addon_selections');
            }
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_feature_requests', function (Blueprint $table) {
            if (Schema::hasColumn('opportunity_feature_requests', 'addon_staff_decisions')) {
                $table->dropColumn('addon_staff_decisions');
            }
        });
    }
};
