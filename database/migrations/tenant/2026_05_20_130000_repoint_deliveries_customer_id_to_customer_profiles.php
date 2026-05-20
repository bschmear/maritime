<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * After {@see 2026_04_07_100001_migrate_customers_to_customer_profiles}, PostgreSQL
 * retargets any existing FK on renamed {@code customers} to {@code customers_legacy}.
 * {@code deliveries.customer_id} must reference {@code customer_profiles} (same ids as pre-migration customers).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('deliveries') || ! Schema::hasTable('customer_profiles')) {
            return;
        }

        $this->dropDeliveriesCustomerIdForeignKeys();

        try {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->foreign('customer_id')
                    ->references('id')
                    ->on('customer_profiles')
                    ->cascadeOnDelete();
            });
        } catch (\Throwable $e) {
            if (! $this->isDuplicateConstraintException($e)) {
                throw $e;
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('deliveries')) {
            return;
        }

        $this->dropDeliveriesCustomerIdForeignKeys();

        if (! Schema::hasTable('customers_legacy')) {
            return;
        }

        try {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->foreign('customer_id')
                    ->references('id')
                    ->on('customers_legacy')
                    ->cascadeOnDelete();
            });
        } catch (\Throwable $e) {
            if (! $this->isDuplicateConstraintException($e)) {
                throw $e;
            }
        }
    }

    /**
     * Drop every foreign key on {@code deliveries.customer_id} (PostgreSQL constraint names vary).
     */
    private function dropDeliveriesCustomerIdForeignKeys(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $rows = DB::select(
                'select tc.constraint_name
                 from information_schema.table_constraints tc
                 join information_schema.key_column_usage kcu
                     on tc.constraint_catalog = kcu.constraint_catalog
                     and tc.constraint_schema = kcu.constraint_schema
                     and tc.constraint_name = kcu.constraint_name
                 where tc.table_schema = current_schema()
                   and tc.table_name = ?
                   and tc.constraint_type = \'FOREIGN KEY\'
                   and kcu.column_name = ?',
                ['deliveries', 'customer_id'],
            );

            foreach ($rows as $row) {
                $name = $row->constraint_name ?? null;
                if (! is_string($name) || $name === '') {
                    continue;
                }
                $q = '"'.str_replace('"', '""', $name).'"';
                DB::statement("alter table deliveries drop constraint if exists {$q}");
            }

            return;
        }

        Schema::table('deliveries', function (Blueprint $table) {
            try {
                $table->dropForeign(['customer_id']);
            } catch (\Throwable) {
                //
            }
        });
    }

    private function isDuplicateConstraintException(\Throwable $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, 'already exists')
            || str_contains($msg, '[42710]') // duplicate_object (e.g. constraint)
            || str_contains($msg, 'SQLSTATE[42710]');
    }
};
