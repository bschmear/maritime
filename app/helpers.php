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

if (! function_exists('current_tenant_user_id')) {
    /**
     * Tenant `users.id` for the authenticated central user, or null when not in tenant context.
     *
     * Use for created_by_id, assigned_user_id, and other FKs to the tenant users table —
     * not auth()->id(), which is the central (public) users table.
     */
    function current_tenant_user_id(): ?int
    {
        $id = current_tenant_profile()?->id;

        return $id !== null ? (int) $id : null;
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

if (! function_exists('format_phone_number')) {
    /**
     * Format a phone string as (XXX) XXX-XXXX (US-style, first 10 digits).
     * Matches resources/js/Utils/formatPhoneNumber.js.
     */
    function format_phone_number(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }

        $numbers = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($numbers) === 11 && str_starts_with($numbers, '1')) {
            $numbers = substr($numbers, 1);
        }

        $length = strlen($numbers);

        if ($length <= 3) {
            return $numbers;
        }

        if ($length <= 6) {
            return sprintf('(%s) %s', substr($numbers, 0, 3), substr($numbers, 3));
        }

        return sprintf(
            '(%s) %s-%s',
            substr($numbers, 0, 3),
            substr($numbers, 3, 3),
            substr($numbers, 6, 10)
        );
    }
}
