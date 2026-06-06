<?php

declare(strict_types=1);

namespace App\Enums\MsoRecord;

enum Status: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case NotRequired = 'not_required';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::NotRequired => 'Not required',
        };
    }

    public function isResolved(): bool
    {
        return $this === self::Submitted || $this === self::NotRequired;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}
