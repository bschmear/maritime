<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Role\Models\Role;
use App\Domain\User\Models\User as TenantUser;
use App\Models\Tenant;
use App\Models\User as CentralUser;

final class ProvisionSupportTenantUser
{
    public function ensure(CentralUser $user, Tenant $tenant): void
    {
        tenancy()->initialize($tenant);

        $existing = TenantUser::query()->where('email', $user->email)->first();
        if ($existing !== null) {
            return;
        }

        $adminRoleId = Role::query()->where('slug', 'admin')->value('id');

        TenantUser::query()->create([
            'display_name' => $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'current_role' => $adminRoleId,
        ]);
    }
}
