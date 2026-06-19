<?php

declare(strict_types=1);

namespace App\Enums\Financing;

enum BillType: string
{
    case Interest = 'interest';
    case Principal = 'principal';
    case Fee = 'fee';

    public function label(): string
    {
        return match ($this) {
            self::Interest => 'Interest',
            self::Principal => 'Principal',
            self::Fee => 'Fee',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'name' => $case->label(),
        ], self::cases());
    }
}
