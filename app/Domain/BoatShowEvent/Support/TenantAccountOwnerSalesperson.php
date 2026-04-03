<?php

declare(strict_types=1);

namespace App\Domain\BoatShowEvent\Support;

use App\Domain\User\Models\User as TenantUser;
use App\Models\Account;
use Illuminate\Support\Facades\Log;

/**
 * Resolves the tenant CRM {@see TenantUser} id for opportunity ownership from the central account owner.
 */
final class TenantAccountOwnerSalesperson
{
    /**
     * @return array{user: TenantUser, account: Account}
     */
    public static function resolve(): array
    {
        $tenant = tenant();
        if (! $tenant) {
            throw new \RuntimeException('No tenant context.');
        }

        $account = Account::query()
            ->where('tenant_id', $tenant->id)
            ->with('owner')
            ->first();

        if (! $account) {
            throw new \RuntimeException('No account found for tenant.');
        }

        $owner = $account->owner;
        if (! $owner || ! $owner->email) {
            Log::warning('Boat show lead: account has no owner email; falling back to first tenant user.', [
                'account_id' => $account->id,
            ]);

            return self::fallbackFirstTenantUser($account);
        }

        $tenantUser = TenantUser::query()->where('email', $owner->email)->first();

        if ($tenantUser) {
            return ['user' => $tenantUser, 'account' => $account];
        }

        Log::warning('Boat show lead: no tenant user matching owner email; falling back to first tenant user.', [
            'account_id' => $account->id,
            'owner_email' => $owner->email,
        ]);

        return self::fallbackFirstTenantUser($account);
    }

    /**
     * @return array{user: TenantUser, account: Account}
     */
    private static function fallbackFirstTenantUser(Account $account): array
    {
        $user = TenantUser::query()->orderBy('id')->first();

        if (! $user) {
            throw new \RuntimeException('No tenant users exist; cannot assign opportunity owner.');
        }

        return ['user' => $user, 'account' => $account];
    }
}
