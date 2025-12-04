<?php

namespace App\Enums\Entity;

enum Status: string
{
    case Active          = 'active';
    case Inactive        = 'inactive';
    case PendingApproval = 'pending';
    case Suspended       = 'suspended';
    case Archived        = 'archived';

    public function id(): int
    {
        return match ($this) {
            self::Active          => 1,
            self::Inactive        => 2,
            self::PendingApproval => 3,
            self::Suspended       => 4,
            self::Archived        => 5,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Active          => 'Active',
            self::Inactive        => 'Inactive',
            self::PendingApproval => 'Pending Approval',
            self::Suspended       => 'Suspended',
            self::Archived        => 'Archived',
        };
    }

    public static function options(): array
    {
        return array_map(fn(self $case) => [
            'id'    => $case->id(),
            'value' => $case->value,
            'name'  => $case->label(),
        ], self::cases());
    }
}
