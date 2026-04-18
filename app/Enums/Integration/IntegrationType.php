<?php

namespace App\Enums\Integration;

enum IntegrationType: int
{
    case MailChimp = 1;

    public function isEnabled(): bool
    {
        return match ($this) {
            default => true,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::MailChimp => 'MailChimp',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MailChimp => 'Online platform for sending professional e-mails to mass recipients.',
        };
    }

    public function route(): string
    {
        return match ($this) {
            self::MailChimp => 'get',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::MailChimp => 'mailchimp',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::MailChimp => 'email',
        };
    }

    public function requiresOAuth(): bool
    {
        return match ($this) {
            self::MailChimp => true,
        };
    }

    public function slug(): string
    {
        return match ($this) {
            self::MailChimp => 'mailchimp',
        };
    }

    public static function options(): array
    {
        return array_map(fn ($case) => [
            'id' => $case->value,
            'name' => $case->label(),
            'slug' => $case->slug(),
            'description' => $case->description(),
            'icon' => $case->icon(),
            'route' => $case->route(),
            'category' => $case->category(),
            'requires_oauth' => $case->requiresOAuth(),
        ], array_filter(self::cases(), fn ($case) => $case->isEnabled()));
    }

    public static function byCategory(): array
    {
        $grouped = [];
        foreach (self::cases() as $case) {
            $category = $case->category();
            if (! isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = [
                'id' => $case->value,
                'name' => $case->label(),
                'slug' => $case->slug(),
                'description' => $case->description(),
                'icon' => $case->icon(),
                'route' => $case->route(),
                'requires_oauth' => $case->requiresOAuth(),
            ];
        }

        return $grouped;
    }
}
