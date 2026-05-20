<?php

namespace App\Enums;

enum SupportTicketStatus: int
{
    case Open = 1;
    case InProgress = 2;
    case WaitingOnCustomer = 3;
    case Resolved = 4;
    case Closed = 5;

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In Progress',
            self::WaitingOnCustomer => 'Waiting on Customer',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::InProgress => 'yellow',
            self::WaitingOnCustomer => 'yellow',
            self::Resolved => 'green',
            self::Closed => 'red',
        };
    }

    public function isActive(): bool
    {
        return match ($this) {
            self::Open, self::InProgress, self::WaitingOnCustomer => true,
            self::Resolved, self::Closed => false,
        };
    }
}
