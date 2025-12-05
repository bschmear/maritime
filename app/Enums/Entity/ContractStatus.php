<?php

namespace App\Enums\Entity;

enum ContractStatus: string
{
    case Active     = 'active';
    case Pending    = 'pending';
    case Expired    = 'expired';
    case Terminated = 'terminated';

    /**
     * Get the numeric ID for the contract status.
     */
    public function id(): int
    {
        return match ($this) {
            self::Active     => 1,
            self::Pending    => 2,
            self::Expired    => 3,
            self::Terminated => 4,
        };
    }

    /**
     * Get a human-readable label for the contract status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active     => 'Active',
            self::Pending    => 'Pending',
            self::Expired    => 'Expired',
            self::Terminated => 'Terminated',
        };
    }

    /**
     * Return all enum options as an array for select fields or APIs.
     */
    public static function options(): array
    {
        return array_map(fn(self $case) => [
            'id'    => $case->id(),
            'value' => $case->value,
            'name'  => $case->label(),
        ], self::cases());
    }
}
