<?php

namespace App\Enums\Surveys;

enum Type: string
{
    case Feedback = 'feedback';
    case Lead = 'lead';
    case FollowUp = 'followup';
    case Custom = 'custom';

    public function id(): int
    {
        return match ($this) {
            self::Feedback => 1,
            self::Lead => 2,
            self::FollowUp => 3,
            self::Custom => 4,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Feedback => 'Feedback',
            self::Lead => 'Lead',
            self::FollowUp => 'Follow Up',
            self::Custom => 'Custom',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Feedback => 'blue',
            self::Lead => 'purple',
            self::FollowUp => 'teal',
            self::Custom => 'slate',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Feedback => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::Lead => 'bg-purple-200 dark:text-white dark:bg-purple-900',
            self::FollowUp => 'bg-teal-200 dark:text-white dark:bg-teal-900',
            self::Custom => 'bg-slate-200 dark:text-white dark:bg-slate-800',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id' => $case->id(),
            'value' => $case->value,
            'name' => $case->label(),
            'label' => $case->label(),
            'color' => $case->color(),
            'bgClass' => $case->bgClass(),
        ], self::cases());
    }
}
