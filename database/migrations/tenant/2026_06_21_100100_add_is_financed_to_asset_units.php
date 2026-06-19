<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('asset_units') || Schema::hasColumn('asset_units', 'is_financed')) {
            return;
        }

        Schema::table('asset_units', function (Blueprint $table) {
            $table->boolean('is_financed')->default(false)->after('is_consignment')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('asset_units') || ! Schema::hasColumn('asset_units', 'is_financed')) {
            return;
        }

        Schema::table('asset_units', function (Blueprint $table) {
            $table->dropColumn('is_financed');
        });
    }
};
