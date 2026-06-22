<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('asset_units') || ! Schema::hasColumn('asset_units', 'vendor_id')) {
            return;
        }

        Schema::table('asset_units', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->foreign('vendor_id')
                ->references('id')
                ->on('vendors')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('asset_units') || ! Schema::hasColumn('asset_units', 'vendor_id')) {
            return;
        }

        Schema::table('asset_units', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->foreign('vendor_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }
};
