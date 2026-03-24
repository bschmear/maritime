<?php

namespace App\Enums\Communication;

enum NextActionType: string
{
    case None = 'none';
    case FollowUp = 'follow_up';
    case Meeting = 'meeting';

    public function id(): int
    {
        return match ($this) {
            self::None => 1,
            self::FollowUp => 2,
            self::Meeting => 3,
        };
    }

    public static function fromId(int $id): ?self
    {
        return match ($id) {
            1 => self::None,
            2 => self::FollowUp,
            3 => self::Meeting,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::FollowUp => 'Follow-Up',
            self::Meeting => 'Meeting',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::FollowUp => 'blue',
            self::Meeting => 'green',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::None => 'bg-gray-200 dark:text-white dark:bg-gray-900',
            self::FollowUp => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::Meeting => 'bg-green-200 dark:text-white dark:bg-green-900',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id' => $case->id(),
            'value' => $case->value,
            'name' => $case->label(),
            'color' => $case->color(),
            'bgClass' => $case->bgClass(),
        ], self::cases());
    }

    public static function selectOptions(): array
    {
        return array_map(fn (self $case) => [
            $case->id() => $case->label(),
        ], self::cases());
    }
}
