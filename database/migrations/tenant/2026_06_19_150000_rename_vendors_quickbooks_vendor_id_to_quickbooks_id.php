<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendors')) {
            return;
        }

        if (Schema::hasColumn('vendors', 'quickbooks_id')) {
            return;
        }

        Schema::table('vendors', function (Blueprint $table) {
            $table->string('quickbooks_id', 64)->nullable()->unique();
        });

        if (Schema::hasColumn('vendors', 'quickbooks_vendor_id')) {
            DB::table('vendors')
                ->whereNotNull('quickbooks_vendor_id')
                ->update(['quickbooks_id' => DB::raw('quickbooks_vendor_id')]);

            Schema::table('vendors', function (Blueprint $table) {
                $table->dropUnique(['quickbooks_vendor_id']);
                $table->dropColumn('quickbooks_vendor_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('vendors')) {
            return;
        }

        if (! Schema::hasColumn('vendors', 'quickbooks_id')) {
            return;
        }

        if (! Schema::hasColumn('vendors', 'quickbooks_vendor_id')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('quickbooks_vendor_id', 64)->nullable()->unique()->after('vendor_code');
            });
        }

        DB::table('vendors')
            ->whereNotNull('quickbooks_id')
            ->update(['quickbooks_vendor_id' => DB::raw('quickbooks_id')]);

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropUnique(['quickbooks_id']);
            $table->dropColumn('quickbooks_id');
        });
    }
};
