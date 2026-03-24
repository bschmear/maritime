<?php

namespace App\Enums\Communication;

enum Outcome: string
{
    case Connected = 'connected';
    case NoAnswer = 'no_answer';
    case LeftVoicemail = 'left_voicemail';
    case NotInterested = 'not_interested';
    case Other = 'other';

    public function id(): int
    {
        return match ($this) {
            self::Connected => 1,
            self::NoAnswer => 2,
            self::LeftVoicemail => 3,
            self::NotInterested => 4,
            self::Other => 5,
        };
    }

    public static function fromId(int $id): ?self
    {
        return match ($id) {
            1 => self::Connected,
            2 => self::NoAnswer,
            3 => self::LeftVoicemail,
            4 => self::NotInterested,
            5 => self::Other,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Connected => 'Connected',
            self::NoAnswer => 'No Answer',
            self::LeftVoicemail => 'Left Voicemail',
            self::NotInterested => 'Not Interested',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Connected => 'green',
            self::NoAnswer => 'gray',
            self::LeftVoicemail => 'blue',
            self::NotInterested => 'red',
            self::Other => 'yellow',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Connected => 'bg-green-200 dark:text-white dark:bg-green-900',
            self::NoAnswer => 'bg-gray-200 dark:text-white dark:bg-gray-900',
            self::LeftVoicemail => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::NotInterested => 'bg-red-200 dark:text-white dark:bg-red-900',
            self::Other => 'bg-yellow-200 dark:text-white dark:bg-yellow-900',
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
