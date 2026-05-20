<?php

namespace App\Enums;

enum SupportTicketPriority: int
{
    case Low = 1;
    case Normal = 2;
    case High = 3;
    case Urgent = 4;

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::High => 'High',
            self::Urgent => 'Urgent',
        };
    }
}
