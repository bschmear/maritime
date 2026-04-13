<?php

namespace App\Enums\Invoice;

use App\Enums\Entity\Concerns\ResolvesStringEnumFromIdOrValue;

enum Status: string
{
    use ResolvesStringEnumFromIdOrValue;

    case Draft = 'draft';
    case Issued = 'issued';        // sent to customer
    case Viewed = 'viewed';        // optional, if you track opens
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Issued => 'Issued',
            self::Viewed => 'Viewed',
            self::PartiallyPaid => 'Partially Paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
            self::Cancelled => 'Cancelled',
            self::Void => 'Void',
        };
    }
}