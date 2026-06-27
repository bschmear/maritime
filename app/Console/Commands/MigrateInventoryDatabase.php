<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateInventoryDatabase extends Command
{
    protected $signature = 'inventory:migrate
                            {--fresh : Drop all inventory tables and re-run migrations}
                            {--force : Force the operation in production}';

    protected $description = 'Run migrations against the inventory database (INVENTORY_DATABASE)';

    public function handle(): int
    {
        $database = (string) config('database.connections.inventory.database');

        $this->info("Inventory connection: {$database}");

        try {
            DB::connection('inventory')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Could not connect to the inventory database.');
            $this->line($e->getMessage());
            $this->newLine();
            $this->line('Ensure the database exists and INVENTORY_* settings in .env are correct.');
            $this->line('Example: createdb maritime_inventory');

            return self::FAILURE;
        }

        $options = [
            '--database' => 'inventory',
            '--path' => 'database/migrations/inventory',
        ];

        if ($this->option('force')) {
            $options['--force'] = true;
        }

        if ($this->option('fresh')) {
            if (! $this->option('force') && $this->laravel->environment('production')) {
                $this->error('Refusing to run inventory:migrate --fresh in production without --force.');

                return self::FAILURE;
            }

            if (! $this->confirm("Drop all tables in `{$database}` and re-run inventory migrations?", false)) {
                $this->warn('Aborted.');

                return self::SUCCESS;
            }

            $exitCode = $this->call('migrate:fresh', $options);
        } else {
            $exitCode = $this->call('migrate', $options);
        }

        if ($exitCode !== self::SUCCESS) {
            return $exitCode;
        }

        $count = DB::connection('inventory')->table('migrations')->count();
        $this->newLine();
        $this->info("Inventory migrations recorded: {$count} (table: {$database}.public.migrations)");

        return self::SUCCESS;
    }
}
