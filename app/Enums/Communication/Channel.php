<?php

namespace App\Enums\Communication;

enum Channel: string
{
    case Phone = 'phone';
    case Zoom = 'zoom';
    case WhatsApp = 'whatsapp';
    case LinkedIn = 'linkedin';
    case SMS = 'sms';
    case Email = 'email';
    case InPerson = 'in_person';
    case Other = 'other';

    public function id(): int
    {
        return match ($this) {
            self::Phone => 1,
            self::Zoom => 2,
            self::WhatsApp => 3,
            self::LinkedIn => 4,
            self::SMS => 5,
            self::Email => 6,
            self::InPerson => 7,
            self::Other => 8,
        };
    }

    public static function fromId(int $id): ?self
    {
        return match ($id) {
            1 => self::Phone,
            2 => self::Zoom,
            3 => self::WhatsApp,
            4 => self::LinkedIn,
            5 => self::SMS,
            6 => self::Email,
            7 => self::InPerson,
            8 => self::Other,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Phone',
            self::Zoom => 'Zoom',
            self::WhatsApp => 'WhatsApp',
            self::LinkedIn => 'LinkedIn',
            self::SMS => 'SMS',
            self::Email => 'Email',
            self::InPerson => 'In Person',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Phone => 'blue',
            self::Zoom => 'blue',
            self::WhatsApp => 'green',
            self::LinkedIn => 'blue',
            self::SMS => 'green',
            self::Email => 'gray',
            self::InPerson => 'purple',
            self::Other => 'yellow',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Phone => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::Zoom => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::WhatsApp => 'bg-green-200 dark:text-white dark:bg-green-900',
            self::LinkedIn => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::SMS => 'bg-green-200 dark:text-white dark:bg-green-900',
            self::Email => 'bg-gray-200 dark:text-white dark:bg-gray-900',
            self::InPerson => 'bg-purple-200 dark:text-white dark:bg-purple-900',
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
