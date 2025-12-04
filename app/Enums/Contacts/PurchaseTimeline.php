<?php

namespace App\Enums\Contacts;

enum PurchaseTimeline: string
{
    case Immediate   = 'immediate';   // Ready to buy now
    case ZeroToThreeMonths = '0-3_months';
    case ThreeToSixMonths  = '3-6_months';
    case SixToTwelveMonths = '6-12_months';
    case MoreThanYear      = '12+_months';

    public function id(): int
    {
        return match ($this) {
            self::Immediate        => 1,
            self::ZeroToThreeMonths => 2,
            self::ThreeToSixMonths  => 3,
            self::SixToTwelveMonths => 4,
            self::MoreThanYear      => 5,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Immediate        => 'Immediate',
            self::ZeroToThreeMonths => '0-3 Months',
            self::ThreeToSixMonths  => '3-6 Months',
            self::SixToTwelveMonths => '6-12 Months',
            self::MoreThanYear      => '12+ Months',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Immediate        => 'green',
            self::ZeroToThreeMonths => 'teal',
            self::ThreeToSixMonths  => 'blue',
            self::SixToTwelveMonths => 'orange',
            self::MoreThanYear      => 'gray',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Immediate        => 'bg-green-200 dark:text-white dark:bg-green-900',
            self::ZeroToThreeMonths => 'bg-teal-200 dark:text-white dark:bg-teal-900',
            self::ThreeToSixMonths  => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::SixToTwelveMonths => 'bg-orange-200 dark:text-white dark:bg-orange-900',
            self::MoreThanYear      => 'bg-gray-200 dark:text-white dark:bg-gray-900',
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
