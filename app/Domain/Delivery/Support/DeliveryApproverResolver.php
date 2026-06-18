<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Support;

use App\Domain\Location\Models\Location;
use App\Domain\User\Models\User;

class DeliveryApproverResolver
{
    public static function forLocation(?Location $location): ?User
    {
        if (! $location) {
            return null;
        }

        $approverId = $location->delivery_approver_user_id ?? $location->manager_user_id;

        if (! $approverId) {
            return null;
        }

        if ($location->relationLoaded('deliveryApprover') && (int) $location->delivery_approver_user_id === (int) $approverId) {
            return $location->deliveryApprover;
        }

        if ($location->relationLoaded('managerUser') && (int) $location->manager_user_id === (int) $approverId) {
            return $location->managerUser;
        }

        return User::query()->find($approverId);
    }

    public static function userCanApprove(User $user, ?Location $location): bool
    {
        if (! $location) {
            return false;
        }

        $approver = self::forLocation($location);

        return $approver !== null && (int) $approver->id === (int) $user->id;
    }

    public static function currentUserCanApprove(?Location $location): bool
    {
        $userId = current_tenant_user_id();

        if ($userId === null || ! $location) {
            return false;
        }

        if (current_tenant_role_slug() === 'admin') {
            return true;
        }

        $user = User::query()->find($userId);

        return $user !== null && self::userCanApprove($user, $location);
    }

    /**
     * Locations where the given user is the effective delivery approver
     * (dedicated approver, or manager when no dedicated approver is set).
     */
    public static function scopeEffectiveApprover($query, int $userId): void
    {
        $query->where(function ($q) use ($userId) {
            $q->where('delivery_approver_user_id', $userId)
                ->orWhere(function ($q) use ($userId) {
                    $q->whereNull('delivery_approver_user_id')
                        ->where('manager_user_id', $userId);
                });
        });
    }

    /**
     * @return list<array{id: int, display_name: string}>
     */
    public static function distinctApproverOptions(iterable $locations): array
    {
        $byId = [];

        foreach ($locations as $location) {
            $approver = self::forLocation($location);
            if ($approver === null) {
                continue;
            }

            $byId[(int) $approver->id] = [
                'id' => (int) $approver->id,
                'display_name' => (string) ($approver->display_name ?: "User #{$approver->id}"),
            ];
        }

        $options = array_values($byId);
        usort($options, fn (array $a, array $b) => strcasecmp($a['display_name'], $b['display_name']));

        return $options;
    }
}
