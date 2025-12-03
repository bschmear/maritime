<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class MigrateTenantDatabase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected TenantWithDatabase $tenant;

    public function __construct(TenantWithDatabase $tenant)
    {
        $this->tenant = $tenant;
    }

    public function handle(): void
    {
        \Log::info('MigrateTenantDatabase: Starting', [
            'tenant_id' => $this->tenant->id,
        ]);

        $this->tenant->run(function () {
            $schemaName = $this->tenant->getTenantKey();

            \Log::info('MigrateTenantDatabase: Inside tenant context', [
                'tenant_id' => $this->tenant->id,
                'schema' => $schemaName,
                'config_search_path' => config('database.connections.tenant.search_path'),
            ]);

            Artisan::call('migrate', [
                '--force' => true,
                '--path' => database_path('migrations/tenant'),
                '--realpath' => true,
                '--database' => 'tenant',
            ]);

            \Log::info('Tenant migrations completed', [
                'tenant_id' => $this->tenant->id,
            ]);
        });
    }
}
