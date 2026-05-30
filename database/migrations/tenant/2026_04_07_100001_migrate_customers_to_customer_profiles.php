<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fresh installs: retire the legacy {@code customers} table and point FKs at {@code customer_profiles}.
 * No row migration — new tenants use contacts + customer_profiles from the start.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_profiles')) {
            return;
        }

        $this->dropForeignKeysReferencingCustomers();

        Schema::dropIfExists('customers_legacy');
        Schema::dropIfExists('customers');

        $this->createForeignKeysToCustomerProfiles();
    }

    public function down(): void
    {
        throw new RuntimeException('This migration cannot be reversed safely; restore from backup if needed.');
    }

    private function dropForeignKeysReferencingCustomers(): void
    {
        $map = [
            'lead_profiles' => 'converted_customer_id',
            'leads' => 'converted_customer_id',
            'leads_legacy' => 'converted_customer_id',
            'opportunities' => 'customer_id',
            'estimates' => 'customer_id',
            'transactions' => 'customer_id',
            'portal_accesses' => 'customer_id',
            'service_tickets' => 'customer_id',
            'deliveries' => 'customer_id',
        ];

        foreach ($map as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $this->dropForeignKeyIfExists($table, $column);
        }
    }

    private function createForeignKeysToCustomerProfiles(): void
    {
        $map = [
            'lead_profiles' => ['converted_customer_id', 'nullOnDelete'],
            'opportunities' => ['customer_id', 'cascadeOnDelete'],
            'estimates' => ['customer_id', 'cascadeOnDelete'],
            'transactions' => ['customer_id', 'cascadeOnDelete'],
            'portal_accesses' => ['customer_id', 'cascadeOnDelete'],
            'service_tickets' => ['customer_id', 'cascadeOnDelete'],
            'deliveries' => ['customer_id', 'cascadeOnDelete'],
        ];

        foreach ($map as $table => [$column, $onDelete]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $this->addForeignKeyIfMissing($table, $column, $onDelete);
        }
    }

    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $rows = Schema::getConnection()->select(
                'select tc.constraint_name
                 from information_schema.table_constraints tc
                 join information_schema.key_column_usage kcu
                     on tc.constraint_catalog = kcu.constraint_catalog
                     and tc.constraint_schema = kcu.constraint_schema
                     and tc.constraint_name = kcu.constraint_name
                 where tc.table_schema = current_schema()
                   and tc.table_name = ?
                   and tc.constraint_type = ?
                   and kcu.column_name = ?',
                [$table, 'FOREIGN KEY', $column],
            );

            foreach ($rows as $row) {
                $name = $row->constraint_name ?? null;
                if (! is_string($name) || $name === '') {
                    continue;
                }
                $quoted = '"'.str_replace('"', '""', $name).'"';
                Schema::getConnection()->statement(
                    "alter table \"{$table}\" drop constraint if exists {$quoted}",
                );
            }

            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });
        } catch (Throwable) {
            //
        }
    }

    private function addForeignKeyIfMissing(string $table, string $column, string $onDelete): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $onDelete) {
                $foreign = $blueprint->foreign($column)
                    ->references('id')
                    ->on('customer_profiles');

                match ($onDelete) {
                    'nullOnDelete' => $foreign->nullOnDelete(),
                    default => $foreign->cascadeOnDelete(),
                };
            });
        } catch (Throwable $e) {
            if (! $this->isDuplicateConstraintException($e)) {
                throw $e;
            }
        }
    }

    private function isDuplicateConstraintException(Throwable $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, 'already exists')
            || str_contains($msg, '[42710]')
            || str_contains($msg, 'SQLSTATE[42710]');
    }
};
