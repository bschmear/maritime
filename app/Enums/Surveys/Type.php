<?php

namespace App\Enums\Surveys;

enum Type: string
{
    case Feedback = 'feedback';
    case Lead     = 'lead';
    case FollowUp = 'followup';

    public function id(): int
    {
        return match ($this) {
            self::Feedback => 1,
            self::Lead     => 2,
            self::FollowUp => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Feedback => 'Feedback',
            self::Lead     => 'Lead',
            self::FollowUp => 'Follow Up',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Feedback => 'blue',
            self::Lead     => 'purple',
            self::FollowUp => 'teal',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Feedback => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::Lead     => 'bg-purple-200 dark:text-white dark:bg-purple-900',
            self::FollowUp => 'bg-teal-200 dark:text-white dark:bg-teal-900',
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
