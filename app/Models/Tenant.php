<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * Get the name that should be used for the tenant's schema.
     * This is called by PostgreSQLSchemaManager.
     */
    public function getTenantKey()
    {
        return $this->id;
    }

    /**
     * Get the name for the tenant's database/schema with the configured prefix.
     * PostgreSQLSchemaManager uses this to create the schema name.
     */
    public function getTenantKeyName(): string
    {
        $prefix = config('tenancy.database.prefix', 'tenant');
        return $prefix . $this->getTenantKey();
    }
}
