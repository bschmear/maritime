<?php

use App\Domain\Role\Models\Role;
use App\Domain\User\Models\UserProfile;
use App\Enums\RecordType;
use App\Tenancy\CurrentTenantProfile;

if (! function_exists('current_tenant_profile')) {
    /**
     * Tenant `users` row for the authenticated central user (matched by email).
     */
    function current_tenant_profile(): ?UserProfile
    {
        return app(CurrentTenantProfile::class)->profile();
    }
}

if (! function_exists('current_tenant_role')) {
    /**
     * Role model from the tenant database for the current user.
     */
    function current_tenant_role(): ?Role
    {
        return app(CurrentTenantProfile::class)->role();
    }
}

if (! function_exists('current_tenant_role_slug')) {
    function current_tenant_role_slug(): ?string
    {
        return app(CurrentTenantProfile::class)->roleSlug();
    }
}

if (! function_exists('tenant_can_access_record_type')) {
    function tenant_can_access_record_type(RecordType $type): bool
    {
        return app(CurrentTenantProfile::class)->canAccessRecordType($type);
    }
}
