<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_items')) {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'asset_variant_id')) {
                $table->foreignId('asset_variant_id')
                    ->nullable()
                    ->after('itemable_id')
                    ->constrained('asset_variants')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoice_items')) {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_items', 'asset_variant_id')) {
                $table->dropConstrainedForeignId('asset_variant_id');
            }
        });
    }
};
