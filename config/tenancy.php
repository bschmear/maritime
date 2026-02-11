<?php
declare(strict_types=1);
use Stancl\Tenancy\Database\Models\Domain;
use App\Models\Tenant;
return [
    'tenant_model' => Tenant::class,
    'id_generator' => Stancl\Tenancy\UUIDGenerator::class,
    'domain_model' => Domain::class,
    'central_domains' => [
        '127.0.0.1',
        'localhost',
        config('app.domain', 'localhost'),
    ],
    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        App\Tenancy\Bootstrappers\PostgresSearchPathBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    ],
    'database' => [
        'central_connection' => env('DB_CONNECTION', 'pgsql'),
        'template_tenant_connection' => 'pgsql',
        'prefix' => 'tenant',
        'suffix' => '',
        'managers' => [
            'pgsql' => Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLSchemaManager::class,
        ],
        'auto_create_database' => true
    ],
    'cache' => [
        'tag_base' => 'tenant',
    ],
    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => ['local', 'public'],
        'root_override' => [
            'local' => '%storage_path%/app/',
            'public' => '%storage_path%/app/public/',
        ],
        'suffix_storage_path' => true,
        'asset_helper_tenancy' => false,
    ],
    'redis' => [
        'prefix_base' => 'tenant',
        'prefixed_connections' => [],
    ],
    'features' => [],
    'routes' => true,
    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],
    'seeder_parameters' => [
        '--class' => 'DatabaseSeeder',
        
    ],
];
