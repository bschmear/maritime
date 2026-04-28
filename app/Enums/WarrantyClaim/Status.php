<?php

declare(strict_types=1);

namespace App\Enums\WarrantyClaim;

use App\Enums\Entity\Concerns\ResolvesStringEnumFromIdOrValue;

/**
 * Values must match the tenant {@code warrantyclaims.status} column.
 */
enum Status: string
{
    use ResolvesStringEnumFromIdOrValue;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';
    case Voided = 'voided';

    public function id(): int
    {
        return match ($this) {
            self::Draft => 1,
            self::Submitted => 2,
            self::Approved => 3,
            self::Rejected => 4,
            self::Paid => 5,
            self::Voided => 6,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Paid => 'Paid',
            self::Voided => 'Voided',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Paid || $this === self::Voided;
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'blue',
            self::Approved => 'indigo',
            self::Rejected => 'red',
            self::Paid => 'green',
            self::Voided => 'zinc',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
            self::Submitted => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
            self::Approved => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200',
            self::Rejected => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
            self::Paid => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
            self::Voided => 'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200',
        };
    }

    /**
     * @return list<array{id: int, value: string, name: string, color: string, bgClass: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => [
                'id' => $case->id(),
                'value' => $case->value,
                'name' => $case->label(),
                'color' => $case->color(),
                'bgClass' => $case->bgClass(),
            ],
            self::cases()
        );
    }
}
