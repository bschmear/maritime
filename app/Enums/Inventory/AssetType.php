<?php

namespace App\Enums\Inventory;

enum AssetType: int
{
    case Boat        = 1;
    case Engine      = 2;
    case Trailer     = 3;
    case Other       = 4;

    public function id(): int
    {
        return $this->value;
    }

    /**
     * High-level asset category for filtering & UI grouping
     */
    public function category(): string
    {
        return 'asset';
    }

    public function label(): string
    {
        return match ($this) {
            self::Boat        => 'Boat',
            self::Engine      => 'Engine',
            self::Trailer     => 'Trailer',
            self::Other       => 'Other',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id'       => $case->value,
            'value'    => $case->value,
            'name'     => $case->label(),
            'category' => $case->category(),
        ], self::cases());
    }

    /**
     * Helper for controller-level filtering
     */
    public static function valuesByCategory(string $category): array
    {
        return array_values(
            array_map(
                fn (self $case) => $case->value,
                array_filter(
                    self::cases(),
                    fn (self $case) => $case->category() === $category
                )
            )
        );
    }
}