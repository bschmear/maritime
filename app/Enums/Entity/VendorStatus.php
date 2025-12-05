<?php

namespace App\Enums\Entity;

enum VendorStatus: string
{    
    case Active      = 'active';
    case Inactive    = 'inactive';
    case Partner     = 'partner';
    case Preferred   = 'preferred';
    case Blacklisted = 'blacklisted';

    public function id(): int
    {
        return match ($this) {
            self::Active            => 1,
            self::Inactive          => 2,
            self::Partner           => 3,
            self::Preferred         => 4,
            self::Blacklisted       => 5,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Active          => 'Active',
            self::Inactive        => 'Inactive',
            self::Partner         => 'Partner',
            self::Preferred       => 'Preferred',
            self::Blacklisted     => 'Blacklisted',
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
