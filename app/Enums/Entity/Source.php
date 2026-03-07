<?php

namespace App\Enums\Entity;

enum Source: string
{
    case Referral       = 'referral';
    case Website        = 'website';
    case WalkIn         = 'walk-in';
    case Ad             = 'ad';
    case BoatShow       = 'boat_show';
    case Manufacturer   = 'manufacturer';
    case Other          = 'other';

    public function id(): int
    {
        return match ($this) {
            self::Referral      => 1,
            self::Website       => 2,
            self::WalkIn        => 3,
            self::Ad            => 4,
            self::BoatShow      => 5,
            self::Manufacturer  => 6,
            self::Other         => 7,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Referral => 'Referral',
            self::Website  => 'Website',
            self::WalkIn   => 'Walk-In',
            self::Ad       => 'Ad',
            self::BoatShow       => 'Boat Show',
            self::Manufacturer       => 'Manufacturer',
            self::Other    => 'Other',
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
