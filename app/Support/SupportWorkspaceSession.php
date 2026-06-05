<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Account;
use App\Models\User;
use Illuminate\Http\Request;

final class SupportWorkspaceSession
{
    public const SESSION_KEY = 'support_workspace';

    public static function grant(Account $account, User $user): void
    {
        session([
            self::SESSION_KEY => [
                'account_id' => $account->id,
                'tenant_id' => $account->tenant_id,
                'account_name' => $account->name,
                'user_id' => $user->id,
                'started_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function current(Request $request): ?array
    {
        $session = $request->session()->get(self::SESSION_KEY);

        return is_array($session) ? $session : null;
    }

    public static function allows(Request $request, Account $account, User $user): bool
    {
        if (! $user->is_support || ! $account->allow_support_access) {
            return false;
        }

        $session = self::current($request);
        if ($session === null) {
            return false;
        }

        return (int) ($session['account_id'] ?? 0) === $account->id
            && (int) ($session['user_id'] ?? 0) === $user->id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function bannerForTenant(Request $request): ?array
    {
        if (! tenancy()->initialized) {
            return null;
        }

        $user = $request->user('web');
        $tenant = tenant();
        if (! $user instanceof User || $tenant === null) {
            return null;
        }

        $session = self::current($request);
        if ($session === null || (string) ($session['tenant_id'] ?? '') !== (string) $tenant->id) {
            return null;
        }

        if ((int) ($session['user_id'] ?? 0) !== $user->id) {
            return null;
        }

        return [
            'account_name' => (string) ($session['account_name'] ?? 'this workspace'),
            'exit_url' => route('tenant.support.exit'),
        ];
    }
}
