<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Map legacy deal statuses to pipeline values.
     */
    public function up(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        DB::table('transactions')->where('status', 'active')->update(['status' => 'processing']);
        DB::table('transactions')->where('status', 'won')->update(['status' => 'completed']);
        DB::table('transactions')->where('status', 'lost')->update(['status' => 'failed']);

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE transactions ALTER COLUMN status SET DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE transactions ALTER COLUMN status SET DEFAULT 'active'");
        }

        DB::table('transactions')->where('status', 'processing')->update(['status' => 'active']);
        DB::table('transactions')->where('status', 'completed')->update(['status' => 'won']);
        DB::table('transactions')->where('status', 'failed')->update(['status' => 'lost']);
    }
};
