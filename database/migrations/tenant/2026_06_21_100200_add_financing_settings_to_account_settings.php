<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('account_settings')) {
            return;
        }

        Schema::table('account_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('account_settings', 'financing_max_days_in_inventory')) {
                $table->unsignedInteger('financing_max_days_in_inventory')->nullable()->after('consignment_terms');
            }
            if (! Schema::hasColumn('account_settings', 'financing_interest_alert_amount')) {
                $table->decimal('financing_interest_alert_amount', 12, 2)->nullable()->after('financing_max_days_in_inventory');
            }
            if (! Schema::hasColumn('account_settings', 'financing_csv_column_map')) {
                $table->json('financing_csv_column_map')->nullable()->after('financing_interest_alert_amount');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('account_settings')) {
            return;
        }

        Schema::table('account_settings', function (Blueprint $table) {
            $cols = ['financing_max_days_in_inventory', 'financing_interest_alert_amount', 'financing_csv_column_map'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('account_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
