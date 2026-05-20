<?php

namespace App\Services;

use App\Models\Account;

class WorkspacePlanCache
{
    public const SESSION_KEY = 'workspace_plan';

    /**
     * @return array{id: int|null, name: string|null, ticket_support_access: bool, seat_limit: int, account_id: int}|null
     */
    public static function get(): ?array
    {
        $plan = session(self::SESSION_KEY);

        return is_array($plan) ? $plan : null;
    }

    /**
     * @return array{id: int|null, name: string|null, ticket_support_access: bool, seat_limit: int, account_id: int}
     */
    public static function putForAccount(Account $account): array
    {
        $plan = $account->currentPlan();

        $payload = [
            'account_id' => $account->id,
            'id' => $plan?->id,
            'name' => $plan?->name,
            'ticket_support_access' => (bool) ($plan?->ticket_support_access ?? false),
            'seat_limit' => $plan?->seat_limit ?? 1,
        ];

        session([self::SESSION_KEY => $payload]);

        return $payload;
    }

    /**
     * Refresh when missing or when the user switched to a different workspace.
     */
    public static function ensureForAccount(Account $account): array
    {
        $cached = self::get();

        if ($cached === null || ($cached['account_id'] ?? null) !== $account->id) {
            return self::putForAccount($account);
        }

        return $cached;
    }

    public static function hasTicketSupportAccess(): bool
    {
        $cached = self::get();

        if ($cached !== null) {
            return (bool) ($cached['ticket_support_access'] ?? false);
        }

        return false;
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
