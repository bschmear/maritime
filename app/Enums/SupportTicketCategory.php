<?php

namespace App\Enums;

enum SupportTicketCategory: int
{
    case General = 1;
    case Billing = 2;
    case Technical = 3;
    case Feature = 4;

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Billing => 'Billing',
            self::Technical => 'Technical',
            self::Feature => 'Feature Request',
        };
    }
}
