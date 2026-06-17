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
}
