<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_opportunity', function (Blueprint $table) {
            $table->dropUnique(['opportunity_id', 'asset_id']);
        });

        Schema::table('asset_opportunity', function (Blueprint $table) {
            if (! Schema::hasColumn('asset_opportunity', 'asset_variant_id')) {
                $table->foreignId('asset_variant_id')
                    ->nullable()
                    ->constrained('asset_variants')
                    ->nullOnDelete();
                $table->index(['asset_variant_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('asset_opportunity', function (Blueprint $table) {
            if (Schema::hasColumn('asset_opportunity', 'asset_variant_id')) {
                $table->dropConstrainedForeignId('asset_variant_id');
            }
        });

        Schema::table('asset_opportunity', function (Blueprint $table) {
            $table->unique(['opportunity_id', 'asset_id']);
        });
    }
};
