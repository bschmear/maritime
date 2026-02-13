<?php

namespace App\Enums\ServiceTicket;

enum SignatureMethod: int
{
    case Digital      = 1;
    case Paper        = 2;
    case Verbal       = 3;
    case EmailApproval = 4;

    public function label(): string
    {
        return match ($this) {
            self::Digital       => 'Digital',
            self::Paper         => 'Paper',
            self::Verbal        => 'Verbal',
            self::EmailApproval => 'Email Approval',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn(self $case) => [
                'id'   => $case->value,
                'name' => $case->label(),
            ],
            self::cases()
        );
    }
}