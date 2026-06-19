<?php

namespace App\Services;

use App\Domain\User\Models\User as TenantUser;
use App\Models\User as WebUser;
use Illuminate\Support\Facades\Auth;

final class TenantStaffResolver
{
    public static function tenantStaffForWebUser(?WebUser $webUser): ?TenantUser
    {
        if ($webUser === null) {
            return null;
        }

        $central = auth()->user();
        if ($central instanceof WebUser && $central->id === $webUser->id) {
            return current_tenant_profile();
        }

        return TenantUser::query()->where('email', $webUser->email)->first();
    }

    /**
     * Central web user for tenant mail sandbox routing ({@see TenantMailService}).
     */
    public static function webUserForMail(?WebUser $sessionUser = null): ?WebUser
    {
        $sessionUser ??= Auth::guard('web')->user();
        if ($sessionUser instanceof WebUser) {
            return $sessionUser;
        }

        $profile = current_tenant_profile();
        $email = trim((string) ($profile?->email ?? ''));
        if ($email === '') {
            return null;
        }

        $centralConnection = (string) config('tenancy.database.central_connection', config('database.default'));

        return WebUser::on($centralConnection)->where('email', $email)->first();
    }
}
