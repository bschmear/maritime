<?php

namespace App\Services;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class WorkspaceNavCache
{
    private const TTL_SECONDS = 1800;

    public static function key(int $userId): string
    {
        return 'workspace_nav:user:'.$userId;
    }

    /**
     * @return array<int, array{id: int, name: string, domain: string}>
     */
    public static function get(User $user): array
    {
        return Cache::remember(
            self::key($user->id),
            now()->addSeconds(self::TTL_SECONDS),
            fn () => self::build($user),
        );
    }

    /**
     * Rebuild and store (used after login).
     *
     * @return array<int, array{id: int, name: string, domain: string}>
     */
    public static function put(User $user): array
    {
        $payload = self::build($user);
        Cache::put(self::key($user->id), $payload, now()->addSeconds(self::TTL_SECONDS));

        return $payload;
    }

    public static function forgetUser(User|int $user): void
    {
        $id = $user instanceof User ? $user->id : $user;
        Cache::forget(self::key($id));
    }

    public static function forgetForAccount(Account $account): void
    {
        $account->loadMissing('users');

        $userIds = $account->users->pluck('id');
        if ($account->owner_id) {
            $userIds = $userIds->push($account->owner_id);
        }

        foreach ($userIds->unique()->filter() as $userId) {
            self::forgetUser((int) $userId);
        }
    }

    /**
     * @return array<int, array{id: int, name: string, domain: string}>
     */
    public static function build(User $user): array
    {
        return $user->accounts()
            ->with(['owner', 'domains'])
            ->orderBy('name')
            ->get()
            ->filter(function (Account $account) {
                if (! $account->tenant_id) {
                    return false;
                }

                if ($account->domains->isEmpty()) {
                    return false;
                }

                return $account->hasActiveSubscription();
            })
            ->values()
            ->map(function (Account $account) {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'domain' => $account->domains->first()->domain,
                ];
            })
            ->all();
    }
}
