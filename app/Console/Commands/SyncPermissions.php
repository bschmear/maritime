<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PermissionGenerator;
use Illuminate\Console\Command;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync
                            {--all-tenants : Run inside each tenant database (Stancl tenancy)}
                            {--tenants=* : Tenant id(s) when using --all-tenants; omit to include every tenant}
                            {--catalog-only : Only upsert rows in permissions; do not change role_permissions}';

    protected $description = 'Upsert CRUD permissions from RecordType, then apply default role sets. Use --all-tenants for every tenant.';

    public function handle(PermissionGenerator $generator): int
    {
        if ($this->option('all-tenants')) {
            $tenantIds = array_values(array_filter((array) $this->option('tenants')));
            $forTenants = $tenantIds !== [] ? $tenantIds : null;

            $failed = false;
            tenancy()->runForMultiple($forTenants, function () use ($generator, &$failed): void {
                $label = tenancy()->tenant?->getTenantKey() ?? '?';
                $this->line("--- Tenant {$label} ---");
                if ($this->syncTenant($generator) === self::FAILURE) {
                    $failed = true;
                }
            });

            return $failed ? self::FAILURE : self::SUCCESS;
        }

        if (! tenancy()->initialized) {
            $this->comment('Tip: tenancy is not initialized on this connection. For production, use --all-tenants, or tenancy()->initialize($tenant) before calling this command.');
        }

        return $this->syncTenant($generator);
    }

    private function syncTenant(PermissionGenerator $generator): int
    {
        try {
            $stats = $generator->sync();
            $this->info("Permissions synced: {$stats['created']} created, {$stats['existing']} already present.");

            if (! $this->option('catalog-only')) {
                $generator->assignDefaultRolePermissions();
                $this->info('Default role permissions applied (admin, manager, employee, guest).');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
