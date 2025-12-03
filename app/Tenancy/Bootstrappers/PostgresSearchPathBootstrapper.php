<?php

namespace App\Tenancy\Bootstrappers;

use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

class PostgresSearchPathBootstrapper implements TenancyBootstrapper
{
    public function bootstrap(Tenant $tenant)
    {
        // Get the FULL schema name with prefix (e.g., "tenant" + UUID)
        $schemaName = 'tenant' . $tenant->getTenantKey();

        \Log::info('PostgresSearchPathBootstrapper: Setting search_path', [
            'schema' => $schemaName,
            'tenant_id' => $tenant->id,
        ]);

        // Update the tenant connection config
        $config = config('database.connections.tenant');
        $config['search_path'] = $schemaName;
        config(['database.connections.tenant' => $config]);

        // Purge to force fresh connection with new config
        DB::purge('tenant');

        \Log::info('PostgresSearchPathBootstrapper: Config updated', [
            'full_config' => config('database.connections.tenant'),
        ]);
    }

    public function revert()
    {
        config(['database.connections.tenant.search_path' => 'public']);
        DB::purge('tenant');
    }
}
