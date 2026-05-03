<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixPostgresMigrationsSequence extends Command
{
    protected $signature = 'db:fix-migrations-sequence
                            {--database=tenant : Database connection name}
                            {--all-tenants : Run for each tenant schema (stancl PostgreSQL search_path)}
                            {--tenants=* : Tenant id(s); default all tenants when using --all-tenants}';

    protected $description = 'Sync PostgreSQL migrations.id sequence when inserts fail with duplicate key on migrations_pkey';

    public function handle(): int
    {
        $name = (string) $this->option('database');
        $config = config("database.connections.{$name}");

        if (($config['driver'] ?? null) !== 'pgsql') {
            $this->error('Only pgsql connections are supported.');

            return self::FAILURE;
        }

        if ($this->option('all-tenants')) {
            $ids = $this->option('tenants');
            $forTenants = count($ids) ? $ids : null;
            $failed = false;

            tenancy()->runForMultiple($forTenants, function () use ($name, &$failed) {
                if ($this->syncOnce($name) === self::FAILURE) {
                    $failed = true;
                }
            });

            return $failed ? self::FAILURE : self::SUCCESS;
        }

        return $this->syncOnce($name);
    }

    private function syncOnce(string $connectionName): int
    {
        $conn = DB::connection($connectionName);

        try {
            $row = $conn->selectOne(
                <<<'SQL'
                SELECT setval(
                    pg_get_serial_sequence('migrations', 'id'),
                    CASE WHEN EXISTS (SELECT 1 FROM migrations)
                         THEN (SELECT MAX(id) FROM migrations)
                         ELSE 1 END,
                    EXISTS (SELECT 1 FROM migrations)
                ) AS current_sequence_value
                SQL
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $value = $row->current_sequence_value ?? null;
        $tenantLabel = tenancy()->initialized ? 'tenant '.tenancy()->tenant->getTenantKey().', ' : '';
        $this->info("{$tenantLabel}connection [{$connectionName}]: setval returned {$value}.");

        return self::SUCCESS;
    }
}
