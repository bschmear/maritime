<?php

namespace App\Enums\Communication;

enum CommunicationType: string
{
    case Call = 'call';
    case Email = 'email';
    case Text = 'text';
    case Meeting = 'meeting';
    case SurveySubmission = 'survey_submission';

    public function id(): int
    {
        return match ($this) {
            self::Call => 1,
            self::Email => 2,
            self::Text => 3,
            self::Meeting => 4,
            self::SurveySubmission => 5,
        };
    }

    public static function fromId(int $id): ?self
    {
        return match ($id) {
            1 => self::Call,
            2 => self::Email,
            3 => self::Text,
            4 => self::Meeting,
            5 => self::SurveySubmission,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Call',
            self::Email => 'Email',
            self::Text => 'Text',
            self::Meeting => 'Meeting',
            self::SurveySubmission => 'Survey Submission',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Call => 'blue',
            self::Email => 'gray',
            self::Text => 'green',
            self::Meeting => 'purple',
            self::SurveySubmission => 'yellow',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Call => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::Email => 'bg-gray-200 dark:text-white dark:bg-gray-900',
            self::Text => 'bg-green-200 dark:text-white dark:bg-green-900',
            self::Meeting => 'bg-purple-200 dark:text-white dark:bg-purple-900',
            self::SurveySubmission => 'bg-yellow-200 dark:text-white dark:bg-yellow-900',
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
