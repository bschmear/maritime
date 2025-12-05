<?php

namespace App\Enums\Entity;

enum RoleType: string
{
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case USER = 'user';
    case VIEWER = 'viewer';

    public function label(): string
    {
        return match($this) {
            self::ADMIN => 'Administrator',
            self::MANAGER => 'Manager',
            self::USER => 'User',
            self::VIEWER => 'Viewer',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::ADMIN => 'Full system access with all permissions',
            self::MANAGER => 'Can manage team members and most data',
            self::USER => 'Standard user with basic access',
            self::VIEWER => 'Read-only access to data',
        };
    }
}