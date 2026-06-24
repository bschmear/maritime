<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class TenantSeedDataCommand extends Command
{
    protected $signature = 'tenants:seed-data
                            {seed? : Seeder file name without .php (e.g. boat-options)}
                            {--tenant= : Tenant UUID or 1-based account number (e.g. 2)}
                            {--list : List available tenant seed files}';

    protected $description = 'Run a tenant-specific data seeder from database/seeders/tenants/{tenant-id}/{seed}.php';

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listSeedFiles();
        }

        $seed = (string) ($this->argument('seed') ?? '');
        $tenantKey = (string) ($this->option('tenant') ?? '');

        if ($seed === '' || $tenantKey === '') {
            $this->error('Provide both {seed} and --tenant=. Example: php artisan tenants:seed-data boat-options --tenant=2');
            $this->line('Use --list to see available tenant seed files.');

            return self::FAILURE;
        }

        $tenant = $this->resolveTenant($tenantKey);
        if ($tenant === null) {
            $this->error("Tenant not found for identifier: {$tenantKey}");

            return self::FAILURE;
        }

        $seedPath = $this->resolveSeedPath($tenantKey, $seed)
            ?? $this->resolveSeedPath($tenant->getTenantKey(), $seed);

        if ($seedPath === null || ! is_readable($seedPath)) {
            $this->error("Seed file not found for tenant {$tenantKey}: database/seeders/tenants/{tenant}/{$seed}.php");

            return self::FAILURE;
        }

        $this->info("Seeding {$seed} for tenant {$tenant->getTenantKey()}…");

        $failed = false;

        $tenant->run(function () use ($seedPath, &$failed): void {
            /** @var Seeder $seeder */
            $seeder = require $seedPath;
            if (! $seeder instanceof Seeder) {
                $this->error('Seed file must return an instance of '.Seeder::class);

                $failed = true;

                return;
            }

            $seeder->setContainer($this->laravel)->setCommand($this)->run();
        });

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function listSeedFiles(): int
    {
        $root = database_path('seeders/tenants');
        if (! is_dir($root)) {
            $this->warn('No tenant seed directory found at database/seeders/tenants');

            return self::SUCCESS;
        }

        $rows = [];
        foreach (File::directories($root) as $tenantDir) {
            $tenantId = basename($tenantDir);
            foreach (File::glob($tenantDir.'/*.php') as $file) {
                $rows[] = [$tenantId, basename($file, '.php')];
            }
        }

        if ($rows === []) {
            $this->warn('No tenant seed files found.');

            return self::SUCCESS;
        }

        $this->table(['Tenant folder', 'Seed'], $rows);
        $this->newLine();
        $this->line('Run: php artisan tenants:seed-data {seed} --tenant={tenant-folder}');

        return self::SUCCESS;
    }

    private function resolveTenant(string $identifier): ?Tenant
    {
        $tenant = Tenant::query()->find($identifier);
        if ($tenant !== null) {
            return $tenant;
        }

        if (ctype_digit($identifier)) {
            $index = max(0, (int) $identifier - 1);

            return Tenant::query()
                ->orderBy('created_at')
                ->orderBy('id')
                ->skip($index)
                ->first();
        }

        return null;
    }

    private function resolveSeedPath(string $tenantKey, string $seed): ?string
    {
        $path = database_path("seeders/tenants/{$tenantKey}/{$seed}.php");

        return is_readable($path) ? $path : null;
    }
}
