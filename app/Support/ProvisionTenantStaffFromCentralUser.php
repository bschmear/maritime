<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Role\Models\Role;
use App\Domain\User\Models\User as TenantUser;
use App\Models\Account;
use App\Models\User as CentralUser;
use App\Support\Central\TenantAccountCache;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

final class ProvisionTenantStaffFromCentralUser
{
    public function ensure(CentralUser $user): ?TenantUser
    {
        if (! tenancy()->initialized) {
            return null;
        }

        $existing = TenantUser::query()
            ->whereRaw('LOWER(email) = ?', [strtolower((string) $user->email)])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $tenant = tenant();
        if ($tenant === null) {
            return null;
        }

        $account = TenantAccountCache::findByTenantId($tenant->id);
        if ($account === null) {
            return null;
        }

        $accountRole = $this->resolveAccountRole($user, $account);
        if ($accountRole === null) {
            return null;
        }

        $tenantRoleId = $this->tenantRoleIdForAccountRole($accountRole);

        try {
            $tenantUser = TenantUser::query()->create([
                'display_name' => $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => strtolower((string) $user->email),
                'current_role' => $tenantRoleId,
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->syncUsersIdSequence();
            $tenantUser = TenantUser::query()->create([
                'display_name' => $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => strtolower((string) $user->email),
                'current_role' => $tenantRoleId,
            ]);
        }

        Log::info('Provisioned tenant staff profile from central user', [
            'central_user_id' => $user->id,
            'tenant_id' => $tenant->getTenantKey(),
            'tenant_user_id' => $tenantUser->id,
            'email' => $tenantUser->email,
        ]);

        return $tenantUser;
    }

    private function syncUsersIdSequence(): void
    {
        TenantUser::query()->getConnection()->statement(
            "SELECT setval(pg_get_serial_sequence('users', 'id'), COALESCE((SELECT MAX(id) FROM users), 0) + 1, false)"
        );
    }

    private function resolveAccountRole(CentralUser $user, Account $account): ?string
    {
        if ((int) $account->owner_id === (int) $user->id) {
            return 'admin';
        }

        $pivotRole = $account->users()->where('users.id', $user->id)->first()?->pivot?->role;
        if (filled($pivotRole)) {
            return (string) $pivotRole;
        }

        if (SupportWorkspaceSession::allows(request(), $account, $user)) {
            return 'admin';
        }

        return null;
    }

    private function tenantRoleIdForAccountRole(string $accountRole): ?int
    {
        $roleMapping = [
            'admin' => 'admin',
            'manager' => 'manager',
            'employee' => 'employee',
            'guest' => 'guest',
            'member' => 'employee',
            'user' => 'employee',
            'editor' => 'employee',
            'viewer' => 'guest',
        ];

        $tenantRoleSlug = $roleMapping[strtolower($accountRole)] ?? 'employee';

        return Role::query()->where('slug', $tenantRoleSlug)->value('id');
    }
}
