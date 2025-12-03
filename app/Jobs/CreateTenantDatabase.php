<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class CreateTenantDatabase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected TenantWithDatabase $tenant;

    public function __construct(TenantWithDatabase $tenant)
    {
        $this->tenant = $tenant;
    }

    public function handle(): void
    {
        $schemaName = 'tenant' . $this->tenant->getTenantKey();

        \Log::info('CreateTenantDatabase: Starting', [
            'tenant_id' => $this->tenant->id,
            'schema_name' => $schemaName,
            'central_connection' => config('tenancy.database.central_connection'),
        ]);

        try {
            // CRITICAL: Use the central connection (pgsql), NOT tenant connection
            $connection = DB::connection(config('tenancy.database.central_connection', 'pgsql'));

            \Log::info('CreateTenantDatabase: Connection established', [
                'connection_name' => $connection->getName(),
                'database' => $connection->getDatabaseName(),
            ]);

            // Create the schema
            $result = $connection->statement("CREATE SCHEMA IF NOT EXISTS \"{$schemaName}\"");

            \Log::info('CreateTenantDatabase: CREATE SCHEMA executed', [
                'schema_name' => $schemaName,
                'result' => $result,
            ]);

            // Verify schema was created - query the actual database
            $exists = $connection->select(
                "SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?",
                [$schemaName]
            );

            \Log::info('CreateTenantDatabase: Schema verification', [
                'schema_name' => $schemaName,
                'exists' => !empty($exists),
                'query_result' => $exists,
            ]);

            if (empty($exists)) {
                throw new \Exception("Schema was not created in database!");
            }

        } catch (\Exception $e) {
            \Log::error('CreateTenantDatabase: Failed to create schema', [
                'tenant_id' => $this->tenant->id,
                'schema_name' => $schemaName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
