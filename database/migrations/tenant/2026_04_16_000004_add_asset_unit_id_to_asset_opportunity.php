<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('asset_opportunity')) {
            return;
        }

        Schema::table('asset_opportunity', function (Blueprint $table) {
            if (! Schema::hasColumn('asset_opportunity', 'asset_unit_id')) {
                $table->foreignId('asset_unit_id')
                    ->nullable()
                    ->constrained('asset_units')
                    ->nullOnDelete();
                $table->index(['asset_unit_id']);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('asset_opportunity')) {
            return;
        }

        Schema::table('asset_opportunity', function (Blueprint $table) {
            if (Schema::hasColumn('asset_opportunity', 'asset_unit_id')) {
                $table->dropConstrainedForeignId('asset_unit_id');
            }
        });
    }
};
