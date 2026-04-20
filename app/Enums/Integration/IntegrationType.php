<?php

namespace App\Enums\Integration;

enum IntegrationType: int
{
    case MailChimp = 1;
    case QuickBooks = 2;

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
            self::QuickBooks => 'QuickBooks Online',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MailChimp => 'Online platform for sending professional e-mails to mass recipients.',
            self::QuickBooks => 'Accounting platform — sync customers with contacts and leads in Maritime.',
        };
    }

    public function route(): string
    {
        return match ($this) {
            self::MailChimp => 'get',
            self::QuickBooks => 'get',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::MailChimp => 'mailchimp',
            self::QuickBooks => 'account_balance_wallet',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::MailChimp => 'email',
            self::QuickBooks => 'accounting',
        };
    }

    public function requiresOAuth(): bool
    {
        return match ($this) {
            self::MailChimp => true,
            self::QuickBooks => true,
        };
    }

    public function slug(): string
    {
        return match ($this) {
            self::MailChimp => 'mailchimp',
            self::QuickBooks => 'quickbooks',
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
