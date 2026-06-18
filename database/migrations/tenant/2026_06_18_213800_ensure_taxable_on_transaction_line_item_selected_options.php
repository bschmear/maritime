<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The 2026_05_09 migration that adds taxable can run before the table exists
 * (2026_05_11 cutover creates it without taxable). Re-apply safely on all tenants.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transaction_line_item_selected_options')) {
            return;
        }
        if (Schema::hasColumn('transaction_line_item_selected_options', 'taxable')) {
            return;
        }

        Schema::table('transaction_line_item_selected_options', function (Blueprint $table) {
            $table->boolean('taxable')->default(true)->after('price');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('transaction_line_item_selected_options')) {
            return;
        }
        if (! Schema::hasColumn('transaction_line_item_selected_options', 'taxable')) {
            return;
        }

        Schema::table('transaction_line_item_selected_options', function (Blueprint $table) {
            $table->dropColumn('taxable');
        });
    }
};
