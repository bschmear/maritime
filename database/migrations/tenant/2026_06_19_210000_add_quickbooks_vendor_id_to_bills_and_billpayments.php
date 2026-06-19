<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bills') && ! Schema::hasColumn('bills', 'quickbooks_vendor_id')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->string('quickbooks_vendor_id', 64)->nullable()->after('vendor_id');
                $table->index('quickbooks_vendor_id');
            });
        }

        if (Schema::hasTable('billpayments') && ! Schema::hasColumn('billpayments', 'quickbooks_vendor_id')) {
            Schema::table('billpayments', function (Blueprint $table) {
                $table->string('quickbooks_vendor_id', 64)->nullable()->after('vendor_id');
                $table->index('quickbooks_vendor_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bills') && Schema::hasColumn('bills', 'quickbooks_vendor_id')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->dropIndex(['quickbooks_vendor_id']);
                $table->dropColumn('quickbooks_vendor_id');
            });
        }

        if (Schema::hasTable('billpayments') && Schema::hasColumn('billpayments', 'quickbooks_vendor_id')) {
            Schema::table('billpayments', function (Blueprint $table) {
                $table->dropIndex(['quickbooks_vendor_id']);
                $table->dropColumn('quickbooks_vendor_id');
            });
        }
    }
};
