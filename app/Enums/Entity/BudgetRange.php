<?php

namespace App\Enums\Entity;

enum BudgetRange: string
{
    case Under10k      = 'under_10k';
    case TenTo25k      = '10k_25k';
    case Twenty5To50k  = '25k_50k';
    case FiftyTo100k   = '50k_100k';
    case HundredTo250k = '100k_250k';
    case Over250k      = '250k_plus';

    public function id(): int
    {
        return match ($this) {
            self::Under10k      => 1,
            self::TenTo25k      => 2,
            self::Twenty5To50k  => 3,
            self::FiftyTo100k   => 4,
            self::HundredTo250k => 5,
            self::Over250k      => 6,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Under10k      => 'Under $10,000',
            self::TenTo25k      => '$10,000 – $25,000',
            self::Twenty5To50k  => '$25,000 – $50,000',
            self::FiftyTo100k   => '$50,000 – $100,000',
            self::HundredTo250k => '$100,000 – $250,000',
            self::Over250k      => '$250,000+',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Under10k      => 'gray',
            self::TenTo25k      => 'blue',
            self::Twenty5To50k  => 'green',
            self::FiftyTo100k   => 'orange',
            self::HundredTo250k => 'purple',
            self::Over250k      => 'red',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Under10k      => 'bg-gray-200 dark:text-white dark:bg-gray-900',
            self::TenTo25k      => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::Twenty5To50k  => 'bg-green-200 dark:text-white dark:bg-green-900',
            self::FiftyTo100k   => 'bg-orange-200 dark:text-white dark:bg-orange-900',
            self::HundredTo250k => 'bg-purple-200 dark:text-white dark:bg-purple-900',
            self::Over250k      => 'bg-red-200 dark:text-white dark:bg-red-900',
        };
    }

    public static function options(): array
    {
        return array_map(fn(self $case) => [
            'id'      => $case->id(),
            'value'   => $case->value,
            'name'    => $case->label(),
            'color'   => $case->color(),
            'bgClass' => $case->bgClass(),
        ], self::cases());
    }
}
