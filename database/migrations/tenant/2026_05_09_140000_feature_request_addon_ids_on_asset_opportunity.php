<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_opportunity', function (Blueprint $table) {
            if (! Schema::hasColumn('asset_opportunity', 'feature_request_addon_ids')) {
                $table->json('feature_request_addon_ids')->nullable()->after('feature_request_completed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('asset_opportunity', function (Blueprint $table) {
            if (Schema::hasColumn('asset_opportunity', 'feature_request_addon_ids')) {
                $table->dropColumn('feature_request_addon_ids');
            }
        });
    }
};
