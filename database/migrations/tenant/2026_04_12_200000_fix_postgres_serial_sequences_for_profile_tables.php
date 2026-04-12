<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk inserts with explicit `id` values (e.g. migrate_leads_to_lead_profiles) leave PostgreSQL
 * sequences behind; the next DEFAULT nextval() can reuse an existing primary key and fail with 23505.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['lead_profiles', 'contacts', 'customer_profiles'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $max = (int) (DB::table($table)->max('id') ?? 0);
            if ($max < 1) {
                continue;
            }

            $row = DB::selectOne(
                'SELECT pg_get_serial_sequence(?, \'id\') AS seq',
                [$table]
            );

            if (! $row || empty($row->seq)) {
                continue;
            }

            // is_called = true so the next INSERT gets max(id) + 1
            DB::statement('SELECT setval(?, ?, true)', [$row->seq, $max]);
        }
    }

    public function down(): void
    {
        // Non-destructive fix; no rollback
    }
};
