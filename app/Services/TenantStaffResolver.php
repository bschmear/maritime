<?php

namespace App\Services;

use App\Domain\User\Models\User as TenantUser;
use App\Models\User as WebUser;

final class TenantStaffResolver
{
    public static function tenantStaffForWebUser(?WebUser $webUser): ?TenantUser
    {
        if ($webUser === null) {
            return null;
        }

        $central = auth()->user();
        if ($central instanceof WebUser && $central->id === $webUser->id) {
            $tenantUserId = current_tenant_user_id();

            return $tenantUserId !== null
                ? TenantUser::query()->find($tenantUserId)
                : null;
        }

        return TenantUser::query()->where('email', $webUser->email)->first();
    }
}
